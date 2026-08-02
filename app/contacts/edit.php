<?php
  // Contact edit form.

  $kind = $_GET['kind'] ?? $_POST['kind'] ?? "person";
  $id = @$_GET['id'] ?: @$_POST['id'];

  if(!in_array($kind, ['person', 'org'])) {
    fail("Malformed 'kind' parameter.", status: 400);
  }

  define('SOCIALS_LABELS', [
    'instagram' => 'Instagram',
    'discord' => 'Discord',
    'snapchat' => 'Snapchat',
    'spacehey' => 'SpaceHey',
    'airbuds' => 'Airbuds',
    'tiktok' => 'TikTok',
    'wattpad' => 'Wattpad',
    'github' => 'GitHub',
    'codeberg' => 'Codebeg',
    'gitlab' => 'GitLab',
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
    $creating = !$id;
    $item = $id ? ($kind == 'org' ? \store\get_organisation($id) : \store\get_contact($id)) : [];
    if($id) $item or fail(($kind == 'org' ? "Organisation" : "Contact") . " not found.", status: 404);

    $phones = array_map(fn($row) => [...$row,
      'phone_number' => normalize_phone_number($row['phone_number'])
    ], unfold($_POST, 'phone', 'phone_number'));

    $timezone = cast_str($_POST['timezone']);
    if($timezone && !in_array($timezone, ENUM_TIMEZONE))
      fail("Invalid 'timezone' parameter.", status: 400);

    if($kind === "org") {
      if($id) {
        $fields = \core\diff($item,
          display_name: cast_str($_POST['display_name']),
          legal_name: cast_str($_POST['legal_name']),
          registration_number: cast_str($_POST['registration_number']),
          vat_number: cast_str($_POST['vat_number']), timezone: $timezone,
          note: cast_str($_POST['note']));

        \store\update_organisation(
          $id,
          cast_str($_POST['display_name']),
          cast_str($_POST['legal_name']),
          cast_str($_POST['registration_number']),
          cast_str($_POST['vat_number']),
          $timezone,
          cast_str($_POST['note'])
        );
      } else {
        $id = \store\put_organisation(
          cast_str($_POST['display_name']),
          cast_str($_POST['legal_name']),
          cast_str($_POST['registration_number']),
          cast_str($_POST['vat_number']),
          $timezone,
          cast_str($_POST['note'])
        );
      }

      \store\set_organisation_emails($id, unfold($_POST, 'email', 'email'));
      \store\set_organisation_phone_numbers($id, $phones);
      \store\set_organisation_urls($id, unfold($_POST, 'url', 'url'));
      \store\set_organisation_socials($id, unfold($_POST, 'social', 'handle'));
      \store\set_organisation_addresses($id, unfold($_POST, 'address', 'street_address'));

      if(!$creating) $fields = [...$fields, 'emails', 'phone_numbers', 'urls', 'socials', 'addresses'];
    }
    else {
      if($id) {
        $fields = \core\diff($item,
          first_name: cast_str($_POST['first_name']),
          middle_name: cast_str($_POST['middle_name']), legal_infix: cast_str($_POST['legal_infix']),
          legal_name: cast_str($_POST['legal_name']), family_infix: cast_str($_POST['family_infix']),
          family_name: cast_str($_POST['family_name']), name_order: cast_str($_POST['name_order']),
          nickname: cast_str($_POST['nickname']), pronouns: cast_str($_POST['pronouns']),
          birth_day: cast_num($_POST['birth_day']), birth_month: cast_num($_POST['birth_month']),
          birth_year: cast_num($_POST['birth_year']),
          anniversary_day: cast_num($_POST['anniversary_day']), anniversary_month: cast_num($_POST['anniversary_month']),
          anniversary_year: cast_num($_POST['anniversary_year']),
          timezone: $timezone, note: cast_str($_POST['note']));

        \store\update_contact(
          $id,
          $item['display_name'],
          cast_str($_POST['first_name']),
          cast_str($_POST['middle_name']),
          cast_str($_POST['legal_infix']),
          cast_str($_POST['legal_name']),
          cast_str($_POST['family_infix']),
          cast_str($_POST['family_name']),
          cast_str($_POST['name_order']),
          cast_str($_POST['nickname']),
          cast_str($_POST['pronouns']),
          cast_num($_POST['birth_day']),
          cast_num($_POST['birth_month']),
          cast_num($_POST['birth_year']),
          cast_num($_POST['anniversary_day']),
          cast_num($_POST['anniversary_month']),
          cast_num($_POST['anniversary_year']),
          $timezone,
          cast_str($_POST['note'])
        );
      } else {
        $id = \store\put_contact(
          null,
          cast_str($_POST['first_name']),
          cast_str($_POST['middle_name']),
          cast_str($_POST['legal_infix']),
          cast_str($_POST['legal_name']),
          cast_str($_POST['family_infix']),
          cast_str($_POST['family_name']),
          cast_str($_POST['name_order']),
          cast_str($_POST['nickname']),
          cast_str($_POST['pronouns']),
          cast_num($_POST['birth_day']),
          cast_num($_POST['birth_month']),
          cast_num($_POST['birth_year']),
          cast_num($_POST['anniversary_day']),
          cast_num($_POST['anniversary_month']),
          cast_num($_POST['anniversary_year']),
          $timezone,
          cast_str($_POST['note'])
        );
      }

      \store\set_contact_emails($id, unfold($_POST, 'email', 'email'));
      \store\set_contact_phone_numbers($id, $phones);
      \store\set_contact_urls($id, unfold($_POST, 'url', 'url'));
      \store\set_contact_socials($id, unfold($_POST, 'social', 'handle'));
      \store\set_contact_roles($id, array_values(array_filter(
        unfold($_POST, 'role', 'org_id'), fn($row) => @$row['org_id'] !== null)));
      \store\set_contact_addresses($id, unfold($_POST, 'address', 'street_address'));
      \store\set_contact_tags($id, $_POST['tags'] ?? []);

      if(!$creating) $fields = [...$fields, 'emails', 'phone_numbers', 'urls', 'socials', 'roles', 'addresses', 'tags'];
    }

    $table = $kind === 'org' ? 'organisations' : 'contacts';
    $message = $creating
      ? "Created $table/$id."
      : "Updated [" . join(", ", $fields) . "] for $table/$id.";
    \store\put_audit_log($table, $id, $message, 'user', operation: $creating ? 'insert' : 'update');

    fragment("contacts/detail", ["kind" => $kind, "id" => $id]); exit;
  }

  if($id) {
    $item = $kind === "org" ? \store\get_organisation($id) : \store\get_contact($id);
    if(!$item) fail(($kind === 'org' ? "Organisation" : "Contact") ." not found.", status: 404);
  }

  $addresses = \store\list_addresses();
  $address_map = [];
  foreach($addresses as $a) $address_map[address_line($a)] = $a;

  $generic_field = fn($scope, $column, $placeholder) =>
    function($row) use ($scope, $column, $placeholder) { ?>
    <input name="<?= $scope ?>_label[]" placeholder="label" value="<?= esc_attr(@$row['label']) ?>">
    <input name="<?= $scope ?>_<?= $column ?>[]" placeholder="<?= $placeholder ?>" required value="<?= esc_attr(@$row[$column]) ?>" data-value>
  <?php };

  $organisations = $kind == 'person' ? \store\list_organisations() : [];

  $roles_field = function($row) use ($organisations) { ?>
    <select name="role_org_id[]" required data-value>
      <?php foreach($organisations as $organisation): ?>
        <option value="<?= esc_attr($organisation['id']) ?>" <?= @$row['org_id'] == $organisation['id'] ? 'selected' : '' ?>>
          <?= esc_inner($organisation['display_name']) ?>
        </option>
      <?php endforeach ?>
    </select>
    <input name="role_role[]" placeholder="role" value="<?= esc_attr(@$row['role']) ?>">
    <?php $main = cast_bool(@$row['main']) ?>
    <input type="hidden" name="role_main[]" value="<?= $main ? 1 : 0 ?>">
    <button type="button" class="role-main" title="Make main role" data-role-main>
      <i class="fa-<?= $main ? 'solid' : 'regular' ?> fa-star"></i>
    </button>
  <?php };

  $address_field = function($row) { ?>
    <div class="address-row__fields">
      <input type="hidden" name="address_id[]" value="<?= esc_attr(@$row['id']) ?>">
      <input name="address_label[]" placeholder="label" value="<?= esc_attr(@$row['link_label']) ?>">
      <input name="address_street_address[]" placeholder="street address" value="<?= esc_attr(@$row['street_address']) ?>" required data-value>
      <input name="address_postal_code[]" placeholder="postal code" value="<?= esc_attr(@$row['postal_code']) ?>">
      <input name="address_city[]" placeholder="city" value="<?= esc_attr(@$row['city']) ?>">
      <input name="address_province[]" placeholder="province" value="<?= esc_attr(@$row['province']) ?>">
      <?php $selected = array_key_exists('country', $row ?: []) ? $row['country'] : COUNTRY ?>
      <select name="address_country[]">
        <?php foreach(country_codes() as $country): ?>
          <option value="<?= $country ?>" <?= $selected == $country ? 'selected' : '' ?>><?= $country ?></option>
        <?php endforeach ?>
        <option value="" <?= $selected ? '' : 'selected' ?>>No country</option>
      </select>
    </div>
  <?php };

  $social_field = function($row) { ?>
    <select name="social_type[]">
      <?php foreach(ENUM_SOCIAL_TYPE as $t): ?>
        <option value="<?= $t ?>" <?= @$row['type'] == $t ? 'selected' : '' ?>><?= SOCIALS_LABELS[$t] ?? ucfirst($t) ?></option>
      <?php endforeach ?>
    </select>
    <input name="social_handle[]" placeholder="handle" required value="<?= esc_attr(@$row['handle']) ?>" data-value>
  <?php };

  $cancel_url = isset($item['id'])
    ? "/contacts/detail?kind=" . rawurlencode($kind) . "&id=" . rawurlencode($item['id'])
    : "/contacts/detail";

?>
<form class="detail__edit" data-contact-state="edit" data-kind="<?= esc_attr($kind) ?>" data-id="<?= esc_attr(@$item['id']) ?>" x-post="/contacts/edit" x-target="#contacts-panel" x-refresh="#contacts-list">
  <input type="hidden" name="kind" value="<?= $kind ?>">
  <input type="hidden" name="id" value="<?= esc_attr(@$item['id']) ?>">

  <div class="actions">
    <button type="button" formnovalidate z-key="escape" z-discard="Discard unsaved changes?"
      x-get="<?= esc_attr($cancel_url) ?>" x-target="#contacts-panel" x-blur>Cancel</button>
    <button>Save</button>
  </div>

  <?php if($kind === "org"): ?>
    <input autofocus class="detail__title" name="display_name" placeholder="Display name" required value="<?= esc_attr(@$item['display_name']) ?>">
    <div class="field">
      <label for="legal_name">Legal name</label>
      <input id="legal_name" name="legal_name" value="<?= esc_attr(@$item['legal_name']) ?>">
    </div>
  <?php else: ?>
    <?php $has_middle = @$item['middle_name'] != '' ?>
    <?php $has_family_infix = @$item['family_infix'] != '' ?>
    <?php $has_legal = @$item['legal_infix'] != '' || @$item['legal_name'] != '' ?>
    <?php $family_infix_label = $has_legal ? "Family infix" : "Infix" ?>
    <?php $family_name_label = $has_legal ? "Family name" : "Last" ?>
    <div class="detail__names">
      <input autofocus name="first_name" placeholder="First" required value="<?= esc_attr(@$item['first_name']) ?>">
      <button type="button" class="js-middle" title="Add middle name" z-toggle=".js-middle" <?= $has_middle ? 'hidden' : '' ?>>+</button>
      <input class="js-middle" name="middle_name" placeholder="Middle" value="<?= esc_attr(@$item['middle_name']) ?>" <?= $has_middle ? '' : 'hidden' ?>>
      <button type="button" class="js-family-infix" title="Add family infix" z-toggle=".js-family-infix" <?= $has_family_infix ? 'hidden' : '' ?>>+</button>
      <input class="js-family-infix" name="family_infix" placeholder="<?= $family_infix_label ?>" value="<?= esc_attr(@$item['family_infix']) ?>" <?= $has_family_infix ? '' : 'hidden' ?>>
      <input name="family_name" placeholder="<?= $family_name_label ?>" value="<?= esc_attr(@$item['family_name']) ?>">
      <button type="button" class="js-legal-name" title="Add legal name" z-toggle=".js-legal-name" data-legal-name-toggle <?= $has_legal ? 'hidden' : '' ?>>+</button>
    </div>
    <div class="detail__names detail__names--legal js-legal-name" <?= $has_legal ? '' : 'hidden' ?>>
      <select name="name_order">
        <option value="family_legal" <?= @$item['name_order'] != 'legal_family' ? 'selected' : '' ?>>Family name first</option>
        <option value="legal_family" <?= @$item['name_order'] == 'legal_family' ? 'selected' : '' ?>>Legal name first</option>
      </select>
      <input name="legal_infix" placeholder="Legal infix" value="<?= esc_attr(@$item['legal_infix']) ?>">
      <input name="legal_name" placeholder="Legal name" value="<?= esc_attr(@$item['legal_name']) ?>">
    </div>
    <div class="field detail__nickname">
      <input name="nickname" placeholder="Nickname" value="<?= esc_attr(@$item['nickname']) ?>">
      <input name="pronouns" placeholder="Pronouns" value="<?= esc_attr(@$item['pronouns']) ?>">
    </div>
    <?php $has_anniversary = @$item['anniversary_day'] || @$item['anniversary_month'] || @$item['anniversary_year'] ?>
    <div class="field">
      <label for="birth_day">Birthday</label>
      <span class="birthday-fields">
        <input id="birth_day" name="birth_day" type="number" min="1" max="31" placeholder="day" value="<?= esc_attr(@$item['birth_day']) ?>">
        <input name="birth_month" type="number" min="1" max="12" placeholder="month" value="<?= esc_attr(@$item['birth_month']) ?>">
        <input name="birth_year" type="number" min="1" max="9999" placeholder="year" value="<?= esc_attr(@$item['birth_year']) ?>">
        <button type="button" class="js-anniversary" title="Add anniversary" z-toggle=".js-anniversary" <?= $has_anniversary ? 'hidden' : '' ?>>+</button>
      </span>
    </div>
    <div class="field js-anniversary" <?= $has_anniversary ? '' : 'hidden' ?>>
      <label for="anniversary_day">Anniversary</label>
      <span class="birthday-fields">
        <input id="anniversary_day" name="anniversary_day" type="number" min="1" max="31" placeholder="day" value="<?= esc_attr(@$item['anniversary_day']) ?>">
        <input name="anniversary_month" type="number" min="1" max="12" placeholder="month" value="<?= esc_attr(@$item['anniversary_month']) ?>">
        <input name="anniversary_year" type="number" min="1" max="9999" placeholder="year" value="<?= esc_attr(@$item['anniversary_year']) ?>">
      </span>
    </div>
    <div class="field">
      <?php tags_field(isset($item['id']) ? \store\list_contact_tags($item['id']) : []) ?>
    </div>
  <?php endif ?>

  <hr>

  <?php repeat_field("Emails", "Email", @$item['emails'] ?: [], $generic_field('email', 'email', 'email')) ?>
  <?php repeat_field("Phones", "Phone", @$item['phone_numbers'] ?: [], $generic_field('phone', 'phone_number', 'phone')) ?>
  <?php repeat_field("Addresses", "Address", @$item['addresses'] ?: [], $address_field, confirm: "", extra: function() { ?>
    <button type="button" data-address-search-toggle>+ Existing address</button>
    <div class="address-search" data-address-search hidden>
      <input class="repeat__wide" type="search" placeholder="find existing address…">
      <ul class="listing address-search__results"></ul>
    </div>
  <?php }) ?>
  <?php repeat_field("Socials", "Social", @$item['socials'] ?: [], $social_field) ?>
  <?php repeat_field("Websites", "Website", @$item['urls'] ?: [], $generic_field('url', 'url', 'url')) ?>
  <?php if($kind === "person" && $organisations): ?>
    <?php repeat_field("Roles", "Role", @$item['roles'] ?: [], $roles_field) ?>
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

  <div class="field">
    <label for="timezone">Timezone</label>
    <?php \forms\options('timezone', ['' => "No timezone", ...timezone_options()], @$item['timezone'], flat: true) ?>
  </div>

  <hr>

  <textarea name="note" rows="2" placeholder="Anything to note..?"><?= esc_inner(@$item['note']) ?></textarea>

  <?php if($id): ?>
    <div class="detail__delete">
      <button class="button" type="button" formnovalidate z-key="d" x-delete="/contacts/delete?kind=<?= $kind ?>&id=<?= @$item['id'] ?>" z-confirm="Delete this <?= $kind === 'org' ? 'organisation' : 'contact' ?>?">Delete</button>
    </div>
  <?php endif ?>

  <script type="application/json" id="addresses-data"><?= json_encode($address_map) ?></script>
</form>
