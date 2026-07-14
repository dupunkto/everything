<?php
  // Contact listing.

  $query = @$_GET['q'] ?? @$_POST['q'] ?? "";

  $kinds = [];
  $selectors = [];

  foreach(str_explode($query) as $token) {
    if($token == "is:person") { 
      $kinds['person'] = true;
      continue;
    }

    if($token == "is:org") {
      $kinds['org'] = true;
      continue;
    }

    if($token[0] == "+") {
      $selectors[] = ['tag', substr($token, 1)];
      continue;
    }

    [$key, $value] = array_pad(explode(":", $token, 2), 2, null);

    // Bare words should fuzzy search
    if($value === null) {
      $selectors[] = ['fuzzy', $token];
      continue;
    }

    // Unrecognised selectors should be ignored
    if(!in_array($key, ['org', 'phone', 'email'])) continue;

    $selectors[] = [$key, $value];
  }

  if(!$kinds) $kinds = ['person' => true]; // Show people by default

  $rows = []; // Used for sorting and filtering.

  if(isset($kinds['person'])) {
    $rows = array_merge($rows, array_map(fn($contact) => [
      'id' => $contact['id'],
      'kind' => 'person',
      'sort' => $contact['last_name'] ?: $contact['first_name'],
      'display' => $contact['display_name'] ?: str_implode(" ", [$contact['first_name'], $contact['infix'], $contact['last_name']]),
      'search' => [
        'fuzzy' => "{$contact['first_name']} {$contact['middle_name']} {$contact['last_name']} {$contact['note']}",
        'tag' => $contact['tag_labels'],
        'email' => $contact['emails'],
        'phone' => $contact['phone_numbers'],
        'org' => $contact['org_names'],
      ],
    ], \store\list_contacts()));
  }

  if(isset($kinds['org'])) {
    $rows = array_merge($rows, array_map(fn($organisation) => [
      'id' => $organisation['id'],
      'kind' => 'org',
      'sort' => $organisation['display_name'],
      'display' => $organisation['display_name'],
      'search' => [
        'fuzzy' => "{$organisation['display_name']} {$organisation['legal_name']} {$organisation['note']}",
        'tag' => $organisation['tag_labels'],
        'email' => $organisation['emails'],
        'phone' => $organisation['phone_numbers'],
        'org' => $organisation['display_name'],
      ],
    ], \store\list_organisations()));
  }

  $rows = array_filter($rows, function($row) use ($selectors) {
    foreach($selectors as [$field, $needle])
      if(!($needle === "" || mb_stripos($row['search'][$field] ?? "", $needle) !== false)) return false;
    return true;
  });

  usort($rows, fn($a, $b) =>
    strcasecmp($a['sort'], $b['sort']) ?:
    strcasecmp($a['display'], $b['display']));

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
