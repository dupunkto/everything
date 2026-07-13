<?php

  $query = trim(@$_GET['q'] ?? @$_POST['q'] ?? "");

  // Query grammar: is:person / is:org (stackable — both shows both), +tag,
  // org:/phone:/email: selectors and bare terms (fuzzy against names + note).
  $terms = [];
  $kinds = [];
  foreach(preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY) as $token) {
    if($token === "is:person") { $kinds['person'] = true; continue; }
    if($token === "is:org") { $kinds['org'] = true; continue; }
    if($token[0] === "+") { $terms[] = ['tag', substr($token, 1)]; continue; }

    [$sel, $val] = array_pad(explode(":", $token, 2), 2, null);
    if($val === null) { $terms[] = ['fuzzy', $token]; continue; }        // bare term
    if(in_array($sel, ['org', 'phone', 'email'])) $terms[] = [$sel, $val];
    // An unrecognised selector (e.g. is:aaaa) is ignored, not matched.
  }

  if(!$kinds) $kinds = ['person' => true]; // default view

  $has = fn($hay, $needle) => $needle === "" || mb_stripos($hay ?? "", $needle) !== false;

  // Normalise people and orgs to a common shape so filtering, sorting and
  // grouping stay identical across both.
  $rows = [];

  if(isset($kinds['person'])) {
    $rows = array_merge($rows, array_map(fn($c) => [
      'id' => $c['id'], 'kind' => 'person',
      'sort' => $c['last_name'] ?: $c['first_name'],
      'display' => trim("{$c['first_name']} {$c['infix']} {$c['last_name']}") ?: $c['display_name'],
      'search' => [
        'fuzzy' => "{$c['first_name']} {$c['middle_name']} {$c['last_name']} {$c['note']}",
        'tag' => $c['tag_labels'], 'email' => $c['emails'], 'phone' => $c['phones'],
        'org' => $c['org_names'],
      ],
    ], \store\list_contacts()));
  }

  if(isset($kinds['org'])) {
    $rows = array_merge($rows, array_map(fn($o) => [
      'id' => $o['id'], 'kind' => 'org',
      'sort' => $o['display_name'], 'display' => $o['display_name'],
      'search' => [
        'fuzzy' => "{$o['display_name']} {$o['legal_name']} {$o['note']}",
        'tag' => $o['tag_labels'], 'email' => $o['emails'], 'phone' => $o['phones'],
        'org' => $o['display_name'],
      ],
    ], \store\list_organisations()));
  }

  $rows = array_filter($rows, function($row) use ($terms, $has) {
    foreach($terms as [$field, $needle])
      if(!$has($row['search'][$field], $needle)) return false;
    return true;
  });

  usort($rows, fn($a, $b) => strcasecmp($a['sort'], $b['sort']) ?: strcasecmp($a['display'], $b['display']));

?>
<?php $letter = null ?>
<?php foreach($rows as $row): ?>
  <?php $initial = mb_strtoupper(mb_substr($row['sort'], 0, 1)) ?: "#" ?>
  <?php if($initial !== $letter): $letter = $initial ?>
    <h3 class="contacts__letter"><?= esc_inner($letter) ?></h3>
  <?php endif ?>
  <div
    class="contact-item"
    tabindex="0"
    data-id="<?= $row['id'] ?>"
    data-kind="<?= $row['kind'] ?>"
    x-get="/contacts/detail?kind=<?= $row['kind'] ?>&id=<?= $row['id'] ?>"
    x-on="click"
    x-target="#contacts-panel"
  ><?= esc_inner($row['display']) ?></div>
<?php endforeach ?>
<?php if(!$rows): ?>
  <p class="placeholder">Nothing here.</p>
<?php endif ?>
