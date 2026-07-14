<?php
  // Contact edit form.

  $kind = @$_GET['kind'] ?? @$_POST['kind'] ?? "person";
  $id = @$_GET['id'] ?? @$_POST['id'];

  if(!in_array($kind, ['person', 'org'])) {
    fail("Malformed 'kind' parameter.", status: 400);
  }

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

  if($kind === "org" ? isset($_POST['display_name']) : isset($_POST['first_name'])) {
    if($kind === "org") {
      if($id) {
        \store\update_organisation(
          $id,
          cast_string(@$_POST['display_name']),
          cast_string(@$_POST['legal_name']),
          cast_string(@$_POST['registration_number']),
          cast_string(@$_POST['vat_number']),
          cast_string(@$_POST['note'])
        ) or fail("Could not update organisation.");
      } else {
        $id = \store\create_organisation(
          cast_string(@$_POST['display_name']),
          cast_string(@$_POST['legal_name']),
          cast_string(@$_POST['registration_number']),
          cast_string(@$_POST['vat_number']),
          cast_string(@$_POST['note'])
        ) or fail("Could not create organisation.");
      }

      \store\set_organisation_emails($id, unfold($_POST, 'email', 'email'));
      \store\set_organisation_phone_numbers($id, unfold($_POST, 'phone', 'phone_number'));
      \store\set_organisation_urls($id, unfold($_POST, 'url', 'url'));
      \store\set_organisation_socials($id, unfold($_POST, 'social', 'handle'));
      \store\set_organisation_addresses($id, unfold($_POST, 'address', 'street_name'));
    }
    else {
      if($id) {
        \store\update_contact(
          $id,
          cast_string(@$_POST['display_name']),
          cast_string(@$_POST['first_name']),
          cast_string(@$_POST['middle_name']),
          cast_string(@$_POST['infix']),
          cast_string(@$_POST['last_name']),
          cast_date(@$_POST['birth_day']),
          cast_string(@$_POST['note'])
        ) or fail("Could not update contact.");
      } else {
        $id = \store\create_contact(
          cast_string(@$_POST['display_name']),
          cast_string(@$_POST['first_name']),
          cast_string(@$_POST['middle_name']),
          cast_string(@$_POST['infix']),
          cast_string(@$_POST['last_name']),
          cast_date(@$_POST['birth_day']),
          cast_string(@$_POST['note'])
        ) or fail("Could not create contact.");
      }

      \store\set_contact_emails($id, unfold($_POST, 'email', 'email'));
      \store\set_contact_phone_numbers($id, unfold($_POST, 'phone', 'phone_number'));
      \store\set_contact_urls($id, unfold($_POST, 'url', 'url'));
      \store\set_contact_socials($id, unfold($_POST, 'social', 'handle'));
      \store\set_contact_roles($id, unfold($_POST, 'role', 'name'));
      \store\set_contact_addresses($id, unfold($_POST, 'address', 'street_name'));
    }

    $_GET['kind'] = $kind;
    $_GET['id'] = $id;

    include __DIR__ . "/detail.php"; exit;
  }

  if($id) {
    $item = $kind === "org" ? \store\get_organisation($id) : \store\get_contact($id);
    if(!$item) fail(($kind === 'org' ? "Organisation" : "Contact") ." not found.", status: 404);
  }

  $addresses = \store\list_addresses();
  $address_map = [];
  foreach($addresses as $a) $address_map[address_line($a)] = $a;

  $repeat = function($legend, $rows, $render, $extra = null) { ?>
    <fieldset class="repeat" data-repeat>
      <legend><?= esc_inner($legend) ?></legend>
      <div class="repeat__rows">
        <?php foreach($rows as $row): ?>
          <div class="repeat__row"><?php $render($row) ?><button type="button" data-remove data-repeat-kind="<?= esc_attr($legend) ?>">&times;</button></div>
        <?php endforeach ?>
      </div>
      <template class="repeat__template"><div class="repeat__row"><?php $render([]) ?><button type="button" data-remove data-repeat-kind="<?= esc_attr($legend) ?>">&times;</button></div></template>
      <button type="button" data-add>+ <?= esc_inner($legend) ?></button>
      <?php if($extra) $extra() ?>
    </fieldset>
  <?php };

  $generic_field = fn($scope, $column, $placeholder) =>
    function($row) use ($scope, $column, $placeholder) { ?>
    <input name="<?= $scope ?>_label[]" placeholder="label" value="<?= esc_attr(@$row['label']) ?>">
    <input name="<?= $scope ?>_<?= $column ?>[]" placeholder="<?= $placeholder ?>" required value="<?= esc_attr(@$row[$column]) ?>" data-value>
  <?php };

  $roles_field = function($row) { ?>
    <input name="role_name[]" placeholder="organisation" required value="<?= esc_attr(@$row['name']) ?>" data-value>
    <input name="role_function[]" placeholder="function" value="<?= esc_attr(@$row['function']) ?>">
  <?php };

  $address_field = function($row) { ?>
    <input name="address_label[]" placeholder="label" value="<?= esc_attr(@$row['link_label']) ?>">
    <input name="address_street_name[]" placeholder="street" value="<?= esc_attr(@$row['street_name']) ?>" required data-value>
    <input name="address_street_number[]" placeholder="number" value="<?= esc_attr(@$row['street_number']) ?>" required>
    <input name="address_postal_code[]" placeholder="postal code" value="<?= esc_attr(@$row['postal_code']) ?>" required>
    <input name="address_city[]" placeholder="city" value="<?= esc_attr(@$row['city']) ?>" required>
    <input name="address_province[]" placeholder="province" value="<?= esc_attr(@$row['province']) ?>" required>
    <input name="address_country[]" placeholder="country" value="<?= esc_attr(@$row['country']) ?>" required>
    <input name="address_timezone[]" placeholder="timezone" value="<?= esc_attr(@$row['timezone']) ?>" required>
  <?php };

  $social_field = function($row) { ?>
    <select name="social_type[]">
      <?php foreach(ENUM_SOCIAL_TYPE as $t): ?>
        <option value="<?= $t ?>" <?= @$row['type'] == $t ? 'selected' : '' ?>><?= SOCIALS_LABELS[$t] ?? ucfirst($t) ?></option>
      <?php endforeach ?>
    </select>
    <input name="social_handle[]" placeholder="handle" required value="<?= esc_attr(@$row['handle']) ?>" data-value>
  <?php };

?>
<form class="detail__edit" x-post="/contacts/edit" x-target="#contacts-panel" x-refresh="#contacts-list">
  <input type="hidden" name="kind" value="<?= $kind ?>">
  <input type="hidden" name="id" value="<?= esc_attr(@$item['id']) ?>">

  <div class="actions">
    <button type="button" data-cancel x-get="/contacts/detail?kind=<?= $kind ?>&id=<?= @$item['id'] ?>" x-target="#contacts-panel">Cancel</button>
    <button>Save</button>
  </div>

  <?php if($kind === "org"): ?>
    <input class="detail__title" name="display_name" placeholder="Display name" required value="<?= esc_attr(@$item['display_name']) ?>">
    <div class="field">
      <label for="legal_name">Legal name</label>
      <input id="legal_name" name="legal_name" value="<?= esc_attr(@$item['legal_name']) ?>">
    </div>
  <?php else: ?>
    <?php $has_middle = @$item['middle_name'] != '' ?>
    <?php $has_infix = @$item['infix'] != '' ?>
    <div class="detail__names">
      <input name="first_name" placeholder="First" required value="<?= esc_attr(@$item['first_name']) ?>">
      <button type="button" class="js-middle" title="Add middle name" z-toggle=".js-middle" <?= $has_middle ? 'hidden' : '' ?>>+</button>
      <input class="js-middle" name="middle_name" placeholder="Middle" value="<?= esc_attr(@$item['middle_name']) ?>" <?= $has_middle ? '' : 'hidden' ?>>
      <button type="button" class="js-infix" title="Add infix" z-toggle=".js-infix" <?= $has_infix ? 'hidden' : '' ?>>+</button>
      <input class="js-infix" name="infix" placeholder="Infix" value="<?= esc_attr(@$item['infix']) ?>" <?= $has_infix ? '' : 'hidden' ?>>
      <input name="last_name" placeholder="Last" value="<?= esc_attr(@$item['last_name']) ?>">
    </div>
    <div class="field">
      <input id="display_name" name="display_name" placeholder="Display name" value="<?= esc_attr(@$item['display_name']) ?>">
    </div>
    <div class="field">
      <label for="birth_day">Birthday</label>
      <input id="birth_day" name="birth_day" type="date" value="<?= esc_attr(@$item['birth_day']) ?>">
    </div>
  <?php endif ?>

  <hr>

  <?php $repeat("Email", @$item['emails'] ?: [], $generic_field('email', 'email', 'email')) ?>
  <?php $repeat("Phone", @$item['phone_numbers'] ?: [], $generic_field('phone', 'phone_number', 'phone')) ?>
  <?php $repeat("Address", @$item['addresses'] ?: [], $address_field, function() { ?>
    <button type="button" data-address-search-toggle>+ Existing address</button>
    <div class="address-search" data-address-search hidden>
      <input class="repeat__wide" type="search" placeholder="find existing address…" data-address-search-input>
      <ul class="listing address-search__results" data-address-search-results></ul>
    </div>
  <?php }) ?>
  <?php $repeat("Socials", @$item['socials'] ?: [], $social_field) ?>
  <?php $repeat("Websites", @$item['urls'] ?: [], $generic_field('url', 'url', 'url')) ?>
  <?php if($kind === "person"): ?>
    <?php $repeat("Roles", @$item['roles'] ?: [], $roles_field) ?>
  <?php endif ?>

  <?php if($kind == 'org'): ?>
    <h2>Accounting</h2>

    <div class="field">
      <label for="registration_number">Registration number</label>
      <input id="registration_number" name="registration_number" value="<?= esc_attr(@$item['registration_number']) ?>">
    </div>
    <div class="field">
      <label for="vat_number">VAT number</label>
      <input id="vat_number" name="vat_number" value="<?= esc_attr(@$item['vat_number']) ?>">
    </div>
  <?php endif; ?>

  <hr>

  <textarea name="note" rows="2" placeholder="Anything to note..?"><?= esc_inner(@$item['note']) ?></textarea>

  <?php if($id): ?>
    <div class="detail__delete">
      <a class="button" href="/contacts/delete?kind=<?= $kind ?>&id=<?= @$item['id'] ?>" z-confirm="Delete this <?= $kind === 'org' ? 'organisation' : 'contact' ?>?">Delete</a>
    </div>
  <?php endif ?>

  <script type="application/json" id="addresses-data"><?= json_encode($address_map) ?></script>
</form>
