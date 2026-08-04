<?php
// Bidirectional IMAP notes syncer.

// This relies on Apple's undocumented IMAP notes format, as documented
// in Cyrus, see <github.com/cyrusimap/cyrus-imapd/blob/master/imap/jmap_notes.c>.

// The canonical variant of the note is in the SQL database. The IMAP
// is a copy that is kept in sync. On conflict, the newest modification
// date wins. The syncer is idempotent and relies purely on Apple UUIDs.

namespace notes;

function plan($client, $account) {
  $mailbox = \imap\ensure_notes_mailbox($client);
  $exists = $client->select($mailbox);

  // Read and normalise the complete mailbox before mutating anything.
  // Apple edits notes by appending a replacement with the same UUID, so
  // several physical messages may share one Apple UUID: the newest Date
  // wins, the greater transient UID breaks ties.
  $groups = [];
  foreach($client->fetch_all($exists) as $message) {
    $note = \imap\parse_note($message['message']);
    if(!$note) continue; // not an Apple note, leave untouched

    $note['uid'] = $message['uid'];
    $group = &$groups[$note['uuid']];
    $group['uids'][] = $note['uid'];

    $current = $group['current'] ?? null;

    if(!$current
      || $note['modified_at'] > $current['modified_at']
      || ($note['modified_at'] == $current['modified_at'] && $note['uid'] > $current['uid']))
      $group['current'] = $note;

    unset($group);
  }

  $stats = [
    'imported' => 0,
    'exported' => 0,
    'updated_from_imap' => 0,
    'updated_in_imap' => 0,
    'deleted_from_imap' => 0,
    'deleted_in_imap' => 0,
    'obsolete_revisions' => 0,
    'unchanged' => 0,
  ];

  $imports = [];
  $updates = [];
  $deletes = [];
  $tombstones = [];
  $apple_ids = [];
  $appends = [];
  $expunge = [];

  foreach(\store\list_note_tombstones() as $tombstone) {
    $group = $groups[$tombstone['apple_id']] ?? null;

    if($group && $group['current']['modified_at'] <= utc_timestamp($tombstone['deleted_at'])) {
      $expunge = [...$expunge, ...$group['uids']];
      $stats['deleted_in_imap']++;
      unset($groups[$tombstone['apple_id']]);
    }

    $tombstones[] = $tombstone['apple_id'];
  }

  $local = array_column(\store\list_apple_notes(),
    column_key: null, index_key: 'apple_id');

  foreach($groups as $uuid => $group) {
    $note = $group['current'];
    $obsolete = array_diff($group['uids'], [$note['uid']]);
    $row = $local[$uuid] ?? null;

    if(!$row) {
      $imports[] = ['note' => $note, 'uuid' => $uuid];
      $stats['imported']++;
    }
    else {
      $modified = modified_at($row);

      if($note['modified_at'] > $modified) {
        $fields = \core\diff($row,
          title: $note['title'],
          content: $note['content'],
          written_at: utc_iso($note['created_at'])
        );
        $updates[] = ['row' => $row, 'note' => $note, 'fields' => $fields];
        $stats['updated_from_imap']++;
      }
      elseif($modified > $note['modified_at']) {
        $appends[] = \imap\build_note_message($account, $uuid,
          $row['title'], $row['content'], utc_timestamp($row['written_at']), $modified);
        $expunge[] = $note['uid'];
        $stats['updated_in_imap']++;
      }
      else {
        // Equal timestamps mean synchronised. Content is intentionally not
        // compared because the MD-HTML conversion is not stable.
        $stats['unchanged']++;
      }
    }

    $expunge = [...$expunge, ...$obsolete];
    $stats['obsolete_revisions'] += count($obsolete);
  }

  foreach(\store\list_notes() as $row) {
    if($row['apple_id'] !== null) continue;

    $uuid = strtoupper(generate_uuid());
    $appends[] = \imap\build_note_message($account, $uuid,
      $row['title'], $row['content'], utc_timestamp($row['written_at']), modified_at($row));
    $apple_ids[] = ['id' => $row['id'], 'uuid' => $uuid];
    $stats['exported']++;
  }

  foreach($local as $uuid => $row) {
    if(isset($groups[$uuid])) continue;

    $deletes[] = $row;
    $stats['deleted_from_imap']++;
  }

  return [
    'mailbox' => $mailbox,
    'imports' => $imports,
    'updates' => $updates,
    'deletes' => $deletes,
    'tombstones' => $tombstones,
    'apple_ids' => $apple_ids,
    'appends' => $appends,
    'expunge' => array_values(array_unique($expunge)),
    'stats' => $stats,
  ];
}

function append($client, $plan) {
  foreach($plan['appends'] as $message)
    $client->append($plan['mailbox'], $message);
}

function save($plan) {
  foreach($plan['tombstones'] as $apple_id)
    \store\delete_note_tombstone($apple_id);

  foreach($plan['imports'] as ['note' => $note, 'uuid' => $uuid]) {
    $id = \store\put_note(
      $note['title'],
      $note['content'],
      utc_iso($note['created_at'])
    );
    \store\update_note_apple_id($id, $uuid);
    \store\put_audit_log('notes', $id, "Created notes/$id.", 'syncer',
      operation: 'insert', changed_at: utc_sql($note['modified_at']));
  }

  foreach($plan['updates'] as ['row' => $row, 'note' => $note, 'fields' => $fields]) {
    \store\update_note(
      $row['id'],
      $note['title'],
      $note['content'],
      utc_iso($note['created_at'])
    );
    \store\put_audit_log('notes', $row['id'],
      "Updated [" . join(", ", $fields) . "] for notes/{$row['id']}.", 'syncer',
      changed_at: utc_sql($note['modified_at']));
  }

  foreach($plan['apple_ids'] as ['id' => $id, 'uuid' => $uuid])
    \store\update_note_apple_id($id, $uuid);

  foreach($plan['deletes'] as $row) {
    \store\delete_note($row['id']);
    \store\put_audit_log('notes', $row['id'], "Deleted notes/{$row['id']}.", 'syncer',
      operation: 'delete');
  }

  return $plan['stats'];
}

function expunge($account, $plan) {
  $client = \imap\connect($account);
  try {
    $client->select($plan['mailbox']);
    $client->mark_deleted($plan['expunge']);
    $client->expunge();
  }
  finally {
    try { $client->logout(); } catch(\Throwable) {}
  }
}

function modified_at($row) {
  $last = \store\get_last_audit_log('notes', $row['id']);
  return utc_timestamp($last['changed_at'] ?? null)
    ?? utc_timestamp($row['written_at'])
    ?? time();
}

function html_to_markdown($html) {
  static $converter;
  if(!$converter) {
    $converter = new \League\HTMLToMarkdown\HtmlConverter([
      'strip_tags' => true,
      'hard_break' => true,
      'header_style' => 'atx',
      'strip_placeholder_links' => true,
      'remove_nodes' => 'script style head title meta',
    ]);

    // Apple notes emit one div per line (an empty line is <div><br></div>);
    // the stock converter would space every div out into its own paragraph.
    $converter->getEnvironment()->addConverter(
      new class implements \League\HTMLToMarkdown\Converter\ConverterInterface {
        function convert(\League\HTMLToMarkdown\ElementInterface $element): string {
          return $element->getValue() . "\n";
        }

        function getSupportedTags(): array {
          return ['div'];
        }
      }
    );
  }

  $markdown = $converter->convert($html);

  // The converter entity-encodes text for pipelines that pass HTML
  // through. The Markdown is edited as plain text and rendered in safe
  // mode, so the encoding is inverted. ENT_NOQUOTES mirrors exactly
  // what the converter encoded.
  $markdown = html_entity_decode($markdown, ENT_NOQUOTES | ENT_HTML5, "UTF-8");

  return trim(preg_replace('/\n{3,}/', "\n\n", $markdown));
}

