<?php
  // Contact listing.

  $query = $_GET['q'] ?? $_POST['q'] ?? "";

  [$tags, $terms, $selectors] = \core\parse_query($query);

  $kinds = [];
  $fields = [];

  foreach($selectors as [$key, $value]) {
    if($key == "is" && $value == "person") { $kinds['person'] = true; continue; }
    if($key == "is" && $value == "org") { $kinds['org'] = true; continue; }

    // Unrecognised selectors should be ignored
    if(in_array($key, ['org', 'phone', 'email'])) $fields[] = [$key, $value];
  }

  if(!$kinds) $kinds = ['person' => true]; // Show people by default

  $rows = []; // Used for sorting and filtering.

  if(isset($kinds['person'])) {
    $rows = array_merge($rows, array_map(fn($contact) => [
      'id' => $contact['id'],
      'kind' => 'person',
      'sort' => \contacts\contact_sort_name($contact),
      'display' => \contacts\contact_listing_name($contact),
      'tags' => str_explode($contact['tag_ids']),
      'search' => [
        'fuzzy' => str_implode(" ", [$contact['first_name'], $contact['middle_name'],
          $contact['legal_infix'], $contact['legal_name'], $contact['family_infix'],
          $contact['family_name'], $contact['note'],
          $contact['emails'], $contact['handles']]),
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
      'tags' => str_explode($organisation['tag_ids']),
      'search' => [
        'fuzzy' => str_implode(" ", [$organisation['display_name'],
          $organisation['legal_name'], $organisation['note'],
          $organisation['emails'], $organisation['handles']]),
        'email' => $organisation['emails'],
        'phone' => $organisation['phone_numbers'],
        'org' => $organisation['display_name'],
      ],
    ], \store\list_organisations()));
  }

  $rows = array_filter($rows, function($row) use ($tags, $terms, $fields) {
    if($tags && array_diff($tags, $row['tags'])) return false;
    if(!str_contains_terms($row['search']['fuzzy'], $terms)) return false;

    foreach($fields as [$field, $needle]) {
      if($needle !== "" && !str_contains_term($row['search'][$field] ?? "", $needle))
        return false;
    }

    return true;
  });

  $searching = $tags || $terms || $fields;

  // Sort by relevance, matches in names first,
  // matches in notes or description later.
  if($searching) {
    $score = function($row) use ($terms) {
      $name = str_normalize($row['display']);
      $total = 0;

      foreach($terms as $term) {
        $pos = mb_strpos($name, str_normalize($term));
        $total += $pos === false ? 1000 : $pos;
      }

      return $total;
    };

    usort($rows, fn($a, $b) =>
      $score($a) <=> $score($b) ?:
      strcasecmp($a['sort'], $b['sort']));
  } else {
    usort($rows, fn($a, $b) =>
      strcasecmp($a['sort'], $b['sort']) ?:
      strcasecmp($a['display'], $b['display']));
  }

  // A tab replaces the is: filters with its own, keeping the rest of the
  // query (tags, free text) intact.
  $tab_query = fn($kind) => join(" ", ["is:$kind", ...array_filter(str_explode($query),
    fn($token) => $token != "is:person" && $token != "is:org")]);

?>
<nav class="contacts__tabs">
  <button type="button" <?php if(isset($kinds['person'])) echo 'class="is-active"' ?>
    z-set=".contacts__search" value="<?= esc_attr($tab_query('person')) ?>">
    <i class="fa-solid fa-people-group"></i> People
  </button>
  <button type="button" <?php if(isset($kinds['org'])) echo 'class="is-active"' ?>
    z-set=".contacts__search" value="<?= esc_attr($tab_query('org')) ?>">
    <i class="fa-solid fa-building-columns"></i> Organisations
  </button>
</nav>

<div class="contacts__list">
<?php $letter = null ?>
<?php foreach($rows as $row): ?>
  <?php if(!$searching): ?>
    <?php $initial = mb_strtoupper(mb_substr($row['sort'], 0, 1)) ?: "#" ?>
    <?php if($initial !== $letter): $letter = $initial ?>
      <h3 class="contacts__letter"><?= esc_inner($letter) ?></h3>
    <?php endif ?>
  <?php endif ?>
  <div
    class="contact-item"
    tabindex="0"
    data-id="<?= esc_attr($row['id']) ?>"
    data-kind="<?= esc_attr($row['kind']) ?>"
    z-key="enter o"
    x-get="/contacts/detail?kind=<?= esc_attr($row['kind']) ?>&id=<?= esc_attr($row['id']) ?>"
    x-on="click"
    x-target="#contacts-panel"
  >
    <?= esc_inner($row['display']) ?>
    <button
      type="button"
      z-key="e"
      x-get="/contacts/edit?kind=<?= esc_attr($row['kind']) ?>&id=<?= esc_attr($row['id']) ?>"
      x-target="#contacts-panel"
      z-stop
      hidden
    ></button>
    <a
      href="/contacts/delete?kind=<?= esc_attr($row['kind']) ?>&id=<?= esc_attr($row['id']) ?>"
      z-key="d"
      z-confirm="Delete this <?= $row['kind'] == 'org' ? 'organisation' : 'contact' ?>?"
      z-stop
      hidden
    ></a>
  </div>
<?php endforeach ?>
<?php if(!$rows): ?>
  <p class="placeholder">Nothing here.</p>
<?php endif ?>
</div>
