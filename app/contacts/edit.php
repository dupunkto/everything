<?php
  // Contact edit form.

  $kind = @$_GET['kind'] ?? @$_POST['kind'] ?? "person";
  $id = @$_GET['id'] ?? @$_POST['id'];

  if(!in_array($kind, ['person', 'org'])) {
    fail("Malformed 'kind' parameter.", status: 400);
  }

  // Address inputs share this column => field-name mapping (also used at /addresses).
  define('ADDR_SPEC', [
    'label' => 'addr_label',
    'street_name' => 'addr_street_name',
    'street_number' => 'addr_street_number',
    'postal_code' => 'addr_postal_code',
    'city' => 'addr_city',
    'province' => 'addr_province',
    'country' => 'addr_country',
    'timezone' => 'addr_timezone',
  ]);

  define('SOCIALS_LABELS', [
    'instagram' => 'Instagram',
    'discord' => 'Discord',
    'snapchat' => 'Snapchat',
    'github' => 'GitHub',
    'codeberg' => 'Codebeg',
    'linkedin' => 'LinkedIn',
    'matrix' => 'Matrix',
    'pinterest' => 'Pinterest',
    'twitter' => 'Twitter',
    'youtube' => 'YouTube',
    'facebook' => 'Facebook',
    'activitypub' => 'Mastodon',
    'bsky' => 'Bluesky',
  ]);

  // Zips parallel POST arrays into rows, dropping any whose $required column is blank.
  function rows($post, $spec, $required) {
    $cols = [];
    foreach($spec as $col => $key) $cols[$col] = (array)($post[$key] ?? []);
    $count = max([0, ...array_map('count', $cols)]);

    $out = [];
    for($i = 0; $i < $count; $i++) {
      $row = [];
      foreach($spec as $col => $key) $row[$col] = trim($cols[$col][$i] ?? "");
      if($row[$required] !== "") $out[] = $row;
    }
    return $out;
  }

  $is_new = ($id === null || $id === "");

  if(isset($_POST['_save'])) {
    // Creation is lazy: the blank "+ New" form writes nothing until saved, and
    // the `required` name inputs block empty submits client-side. The `if($id)`
    // guards only skip child writes when the insert itself was rejected by SQL.
    if($kind === "org") {
      $id = $is_new
        ? \store\create_organisation($_POST['display_name'], $_POST['legal_name'], $_POST['registration_number'], $_POST['vat_number'], $_POST['note'])
        : (\store\update_organisation($id, $_POST['display_name'], $_POST['legal_name'], $_POST['registration_number'], $_POST['vat_number'], $_POST['note']) ? $id : null);

      if($id) {
        \store\set_children('org_emails', 'org_id', $id, rows($_POST, ['label' => 'email_label', 'email' => 'email'], 'email'));
        \store\set_children('org_phone_numbers', 'org_id', $id, rows($_POST, ['label' => 'phone_label', 'phone_number' => 'phone'], 'phone_number'));
        \store\set_children('org_urls', 'org_id', $id, rows($_POST, ['label' => 'url_label', 'url' => 'url'], 'url'));
        \store\set_children('org_socials', 'org_id', $id, rows($_POST, ['type' => 'social_type', 'handle' => 'social'], 'handle'));
        \store\set_organisation_addresses($id, rows($_POST, ADDR_SPEC, 'street_name'));
      }
    }
    else {
      // Stored verbatim (blank stays blank) — the display name is derived from
      // the name fields at render time, so persisting a computed value here
      // would drift once first/last change.
      $display = trim($_POST['display_name']);
      $id = $is_new
        ? \store\create_contact($display, $_POST['first_name'], $_POST['middle_name'], $_POST['infix'], $_POST['last_name'], $_POST['birth_day'], $_POST['note'])
        : (\store\update_contact($id, $display, $_POST['first_name'], $_POST['middle_name'], $_POST['infix'], $_POST['last_name'], $_POST['birth_day'], $_POST['note']) ? $id : null);

      if($id) {
        \store\set_children('contact_emails', 'contact_id', $id, rows($_POST, ['label' => 'email_label', 'email' => 'email'], 'email'));
        \store\set_children('contact_phone_numbers', 'contact_id', $id, rows($_POST, ['label' => 'phone_label', 'phone_number' => 'phone'], 'phone_number'));
        \store\set_children('contact_urls', 'contact_id', $id, rows($_POST, ['label' => 'url_label', 'url' => 'url'], 'url'));
        \store\set_children('contact_socials', 'contact_id', $id, rows($_POST, ['type' => 'social_type', 'handle' => 'social'], 'handle'));
        \store\set_children('contact_roles', 'contact_id', $id, rows($_POST, ['name' => 'org_name', 'function' => 'org_function'], 'name'));
        \store\set_contact_addresses($id, rows($_POST, ADDR_SPEC, 'street_name'));
      }
    }

    if($id) {
      $_GET['kind'] = $kind; $_GET['id'] = $id;
      include __DIR__ . "/detail.php"; exit;
    }
    // A rejected insert falls through to re-render the form.
  }

  if($is_new) {
    $item = array_merge(
      repeat(['id', 'display_name', 'first_name', 'middle_name', 'infix', 'last_name', 'birth_day', 'legal_name', 'registration_number', 'vat_number', 'note'], ''),
      repeat(['emails', 'phone_numbers', 'urls', 'socials', 'roles', 'addresses', 'tags'], [])
    );
  }
  else {
    $item = $kind === "org" ? \store\get_organisation($id) : \store\get_contact($id);
    if(!$item) fail(($kind === 'org' ? "Organisation" : "Contact") ." not found.", status: 404);
  }

  $addresses = \store\list_addresses();
  $address_map = [];
  foreach($addresses as $a) $address_map[address_line($a)] = $a;

  // Renders a repeatable section: existing rows plus a hidden template the
  // client clones on "+ Add".
  $repeat = function($legend, $rows, $render) { ?>
    <fieldset class="repeat" data-repeat>
      <legend><?= esc_inner($legend) ?></legend>
      <div class="repeat__rows">
        <?php foreach($rows as $row): ?>
          <div class="repeat__row"><?php $render($row) ?><button type="button" data-remove>&times;</button></div>
        <?php endforeach ?>
      </div>
      <template class="repeat__template"><div class="repeat__row"><?php $render([]) ?><button type="button" data-remove>&times;</button></div></template>
      <button type="button" data-add>+ <?= esc_inner($legend) ?></button>
    </fieldset>
  <?php };

  // The `data-value` input is the row's meaningful value; the client only asks
  // to confirm a row removal when that field is non-empty.
  $pair = fn($labels, $values, $r) => function($row) use ($labels, $values, $r) { ?>
    <input name="<?= $labels ?>[]" placeholder="label" value="<?= esc_attr($row[$r[0]] ?? '') ?>">
    <input name="<?= $values ?>[]" placeholder="<?= $values ?>" value="<?= esc_attr($row[$r[1]] ?? '') ?>" data-value>
  <?php };

  $roles_field = function($row) { ?>
    <input name="org_name[]" placeholder="organisation" value="<?= esc_attr($row['name'] ?? '') ?>" data-value>
    <input name="org_function[]" placeholder="function" value="<?= esc_attr($row['function'] ?? '') ?>">
  <?php };

  $address_field = function($row) { ?>
    <input class="repeat__wide" name="addr_pick[]" list="addresses-list" placeholder="find existing address…" data-address-pick>
    <input name="addr_label[]" placeholder="label" value="<?= esc_attr($row['link_label'] ?? '') ?>">
    <input name="addr_street_name[]" placeholder="street" value="<?= esc_attr($row['street_name'] ?? '') ?>" data-value>
    <input name="addr_street_number[]" placeholder="nr" value="<?= esc_attr($row['street_number'] ?? '') ?>">
    <input name="addr_postal_code[]" placeholder="postcode" value="<?= esc_attr($row['postal_code'] ?? '') ?>">
    <input name="addr_city[]" placeholder="city" value="<?= esc_attr($row['city'] ?? '') ?>">
    <input name="addr_province[]" placeholder="province" value="<?= esc_attr($row['province'] ?? '') ?>">
    <input name="addr_country[]" placeholder="country" value="<?= esc_attr($row['country'] ?? '') ?>">
    <input name="addr_timezone[]" placeholder="timezone" value="<?= esc_attr($row['timezone'] ?? '') ?>">
  <?php };

  $social_field = function($row) { ?>
    <select name="social_type[]">
      <?php foreach(ENUM_SOCIAL_TYPE as $t): ?>
        <option value="<?= $t ?>" <?= ($row['type'] ?? '') === $t ? 'selected' : '' ?>><?= SOCIALS_LABELS[$t] ?? ucfirst($t) ?></option>
      <?php endforeach ?>
    </select>
    <input name="social[]" placeholder="handle" value="<?= esc_attr($row['handle'] ?? '') ?>" data-value>
  <?php };

?>
<form class="detail__edit" x-post="/contacts/edit" x-target="#contacts-panel" x-refresh="#contacts-list">
  <input type="hidden" name="_save" value="1">
  <input type="hidden" name="kind" value="<?= $kind ?>">
  <input type="hidden" name="id" value="<?= esc_attr($item['id']) ?>">

  <div class="actions">
    <button type="button" data-cancel x-get="/contacts/detail?kind=<?= $kind ?>&id=<?= $item['id'] ?>" x-target="#contacts-panel">Cancel</button>
    <button>Save</button>
  </div>

  <?php if($kind === "org"): ?>
    <input class="detail__title" name="display_name" placeholder="Display name" required value="<?= esc_attr($item['display_name']) ?>">
    <div class="field">
      <label for="legal_name">Legal name</label>
      <input id="legal_name" name="legal_name" value="<?= esc_attr($item['legal_name'] ?? '') ?>">
    </div>
  <?php else: ?>
    <?php $has_middle = ($item['middle_name'] ?? '') !== '' ?>
    <?php $has_infix = ($item['infix'] ?? '') !== '' ?>
    <div class="detail__names">
      <input name="first_name" placeholder="First" required value="<?= esc_attr($item['first_name']) ?>">
      <button type="button" class="js-middle" title="Add middle name" z-toggle=".js-middle" <?= $has_middle ? 'hidden' : '' ?>>+</button>
      <input class="js-middle" name="middle_name" placeholder="Middle" value="<?= esc_attr($item['middle_name']) ?>" <?= $has_middle ? '' : 'hidden' ?>>
      <button type="button" class="js-infix" title="Add infix" z-toggle=".js-infix" <?= $has_infix ? 'hidden' : '' ?>>+</button>
      <input class="js-infix" name="infix" placeholder="Infix" value="<?= esc_attr($item['infix']) ?>" <?= $has_infix ? '' : 'hidden' ?>>
      <input name="last_name" placeholder="Last" value="<?= esc_attr($item['last_name']) ?>">
    </div>
    <div class="field">
      <input id="display_name" name="display_name" placeholder="Display name" value="<?= esc_attr($item['display_name']) ?>">
    </div>
    <div class="field">
      <label for="birth_day">Birthday</label>
      <input id="birth_day" name="birth_day" type="date" value="<?= esc_attr($item['birth_day']) ?>">
    </div>
  <?php endif ?>

  <hr>

  <?php $repeat("Email", $item['emails'], $pair('email_label', 'email', ['label', 'email'])) ?>
  <?php $repeat("Phone", $item['phone_numbers'], $pair('phone_label', 'phone', ['label', 'phone_number'])) ?>
  <?php $repeat("Address", $item['addresses'], $address_field) ?>
  <?php $repeat("Socials", $item['socials'], $social_field) ?>
  <?php $repeat("Websites", $item['urls'], $pair('url_label', 'url', ['label', 'url'])) ?>
  <?php if($kind === "person"): ?>
    <?php $repeat("Roles", $item['roles'], $roles_field) ?>
  <?php endif ?>

  <?php if($kind == 'org'): ?>
    <h2>Accounting</h2>

    <div class="field">
      <label for="registration_number">Registration number</label>
      <input id="registration_number" name="registration_number" value="<?= esc_attr($item['registration_number'] ?? '') ?>">
    </div>
    <div class="field">
      <label for="vat_number">VAT number</label>
      <input id="vat_number" name="vat_number" value="<?= esc_attr($item['vat_number'] ?? '') ?>">
    </div>
  <?php endif; ?>

  <hr>

  <textarea name="note" rows="2" placeholder="Anything to note..?"><?= esc_inner($item['note'] ?? '') ?></textarea>

  <?php if(!$is_new): ?>
    <div class="detail__delete">
      <a class="button" href="/contacts/delete?kind=<?= $kind ?>&id=<?= $item['id'] ?>" z-confirm="Delete this <?= $kind === 'org' ? 'organisation' : 'contact' ?>?">Delete</a>
    </div>
  <?php endif ?>

  <datalist id="addresses-list">
    <?php foreach(array_keys($address_map) as $line): ?>
      <option value="<?= esc_attr($line) ?>"></option>
    <?php endforeach ?>
  </datalist>
  <script type="application/json" id="addresses-data"><?= json_encode($address_map) ?></script>
</form>
