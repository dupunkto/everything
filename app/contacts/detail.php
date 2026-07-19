<?php
  // Contact detail view.

  $kind = @$_GET['kind'] ?? "person";
  $id = @$_GET['id'] ?: @$_POST['id'];

  if(!in_array($kind, ['person', 'org'])) {
    fail("Malformed 'kind' parameter.", status: 400);
  }

  $item = $kind == "org" ? \store\get_organisation($id) : \store\get_contact($id);

  if(!$item) exit; // No row clears the panel.

  if($kind == "org") {
    $title = $item['display_name'];
    $legal = trim($item['legal_name'] ?? '');
    $subtitle = (is_nonempty_str($legal) && $legal !== $title) ? $legal : '';
  }

  if($kind == "person") {
    $full = str_implode(" ", [$item['first_name'], $item['middle_name'], $item['infix'], $item['last_name']]);
    $title = trim($item['display_name'] ?? '') ?: $full;
    $subtitle = (is_nonempty_str($full) && $full !== $title) ? $full : '';
  }

  $timezone = null;
  foreach($item['addresses'] as $address) {
    $zone = $address['timezone'];

    if(is_nonempty_str($zone) && $zone !== TIMEZONE) {
      $timezone = $zone; break;
    }
  }

?>
<header class="detail__header" data-contact-state="view" data-kind="<?= esc_attr($kind) ?>" data-id="<?= esc_attr($item['id']) ?>">
  <div class="detail__identity">
    <span class="detail__avatar"><i class="fa-solid fa-<?= $kind == "org" ? "building-columns" : "user" ?>"></i></span>
    <div>
      <h2><?= esc_inner($title) ?></h2>
      <?php if($subtitle): ?><p class="detail__subtitle"><?= esc_inner($subtitle) ?></p><?php endif ?>
    </div>
  </div>
  <div class="actions">
    <button type="button" data-edit z-key="e" x-get="/contacts/edit?kind=<?= $kind ?>&id=<?= $item['id'] ?>" x-target="#contacts-panel">Edit</button>
    <!-- Deselects: a detail request without an id renders nothing. -->
    <button type="button" z-key="escape" x-get="/contacts/detail" x-target="#contacts-panel" hidden></button>
  </div>
</header>

<?php
  $meta = [];

  if($kind == "person") {
    if($item['birth_day'] && $item['birth_month']) {
      // If the birth year is unknown, we take 2000 as a safe default,
      // so we can still do calculations on a proper DateTime object.
      $birthday = DateTime::createFromFormat('!Y-m-d', join("-", [
        str_pad($item['birth_year'] ?: 2000, 4, "0", STR_PAD_LEFT),
        str_pad($item['birth_month'], 2, "0", STR_PAD_LEFT),
        str_pad($item['birth_day'], 2, "0", STR_PAD_LEFT),
      ]));

      if($birthday) {
        if($item['birth_year']) {
          $meta[] = (new DateTime())->diff($birthday)->y . " yo";
        }

        $meta[] = $birthday->format('F, j');
        $meta[] = star_sign($birthday->format('n'), $birthday->format('j'));
      }
    }

    if($timezone) {
      $meta[] = local_date('H:i', 'now', $timezone);
    }
  }
?>

<?php if($meta || $item['tags']): ?>
  <p class="detail__meta">
    <?php foreach($meta as $text): ?>
      <span class="detail__meta-badge"><?= esc_inner($text) ?></span>
    <?php endforeach ?>
    <?php foreach($item['tags'] as $tag): ?>
      <?php $color = contrast_color($tag['color'], lighten($tag['color'], 0.85), darken($tag['color'], 0.65)) ?>
      <span class="detail__meta-badge detail__meta-tag" style="background-color: <?= esc_attr($tag['color']) ?>; color: <?= esc_attr($color) ?>"><?= esc_inner($tag['label']) ?></span>
    <?php endforeach ?>
  </p>
<?php endif ?>

<div class="detail__cols">
  <div class="detail__main">
    <?php
      $comms = [
        ['fa-solid fa-phone', 'tel:', array_map(fn($r) => [$r['label'], $r['phone_number']], $item['phone_numbers'])],
        ['fa-solid fa-envelope', 'mailto:', array_map(fn($r) => [$r['label'], $r['email']], $item['emails'])],
      ];
    ?>
    <div class="detail__comms">
      <?php foreach($comms as [$icon, $scheme, $rows]):
        $rows = array_values(array_filter($rows, fn($r) => is_nonempty_str($r[1])));
        if(!$rows) continue;
        // Only surface the label column when the rows carry more than one.
        $show_label = count(array_unique(array_filter(array_map(fn($r) => trim($r[0]), $rows)))) > 1;
        foreach($rows as $i => [$label, $value]):
          $href = $scheme . ($scheme === 'tel:' ? preg_replace('/\s+/', "", $value) : $value); ?>
        <div class="detail__comm">
          <span class="detail__comm-icon"><?php if($i === 0): ?><i class="<?= $icon ?>"></i><?php endif ?></span>
          <span class="detail__comm-label"><?= $show_label ? esc_inner($label) : '' ?></span>
          <span class="detail__comm-value"><a href="<?= esc_attr($href) ?>"><?= esc_inner($value) ?></a></span>
        </div>
      <?php endforeach; endforeach ?>
    </div>

    <?php if($item['addresses']): ?>
      <h3>Addresses</h3>
      <div class="detail__addresses">
        <?php foreach($item['addresses'] as $address):
          $lines = [
            "{$address['street_name']} {$address['street_number']}",
            "{$address['postal_code']}  {$address['city']}", // double space between postcode and city
            is_nonempty_str($address['province']) ? "{$address['province']}, {$address['country']}" : $address['country'],
          ];

        ?>
          <div class="detail__address">
            <?php if(is_nonempty_str($address['link_label'])): ?>
            <span class="detail__address-label"><?= esc_inner($address['link_label']) ?></span>
            <?php endif ?>
            <p class="detail__address-lines"><?= esc_inner(str_implode("\n", $lines)) ?></p>
            <?php if($maps = maps_url(address_line($address))): ?>
            <a class="button detail__direction" href="<?= esc_attr($maps) ?>">Directions &rarr;</a>
            <?php endif ?>
          </div>
        <?php endforeach ?>
      </div>
    <?php endif ?>

    <form class="detail__note" x-post="/contacts/note" x-on="input">
      <h3>Note</h3>
      <input type="hidden" name="kind" value="<?= $kind ?>">
      <input type="hidden" name="id" value="<?= esc_attr($item['id']) ?>">
      <textarea name="note" rows="4" placeholder="Anything to note..?"><?= esc_inner($item['note'] ?? '') ?></textarea>
    </form>
  </div>

  <div class="detail__side">
    <?php if($kind === "org" && (is_nonempty_str($item['registration_number']) || is_nonempty_str($item['vat_number']))): ?>
      <h3>Accounting</h3>
      <dl class="detail__accounting">
        <?php if(is_nonempty_str($item['registration_number'])): ?>
          <dt>KvK</dt><dd><?= esc_inner($item['registration_number']) ?></dd>
        <?php endif ?>
        <?php if(is_nonempty_str($item['vat_number'])): ?>
          <dt>VAT</dt><dd><?= esc_inner($item['vat_number']) ?></dd>
        <?php endif ?>
      </dl>
    <?php endif ?>

    <?php if($item['socials']): ?>
      <ul class="detail__socials">
        <?php foreach($item['socials'] as $social): ?>
          <li>
            <?php
              $raw = trim($social['handle']);
              $h = ltrim($raw, "@");
              $enc = rawurlencode($h);
              $ap = explode("@", $h); // activitypub: user@instance

              $icon = match($social['type']) {
                'instagram' => 'fa-brands fa-instagram',
                'discord' => 'fa-brands fa-discord',
                'snapchat' => 'fa-brands fa-snapchat',
                'airbuds' => 'fa-solid fa-album',
                'tiktok' => 'fa-brands fa-tiktok',
                'github' => 'fa-brands fa-github',
                'codeberg' => 'fa-brands fa-codeberg',
                'gitlab' => 'fa-brands fa-gitlab',
                'linkedin' => 'fa-brands fa-linkedin',
                'matrix' => 'fa-solid fa-hashtag',
                'pinterest' => 'fa-brands fa-pinterest',
                'twitter' => 'fa-brands fa-twitter',
                'youtube' => 'fa-brands fa-youtube',
                'facebook' => 'fa-brands fa-facebook',
                'activitypub' => 'fa-brands fa-mastodon',
                'bsky' => 'fa-brands fa-bluesky',
                default => 'fa-solid fa-at',
              };

              $url = match($social['type']) {
                'instagram' => "https://instagram.com/$enc",
                'discord' => ctype_digit($h) ? "https://discord.com/users/$enc" : null,
                'snapchat' => "https://snapchat.com/add/$enc",
                'airbuds' => "https://i.airbuds.fm/$enc",
                'tiktok' => "https://tiktok.com/@$enc",
                'github' => "https://github.com/$enc",
                'codeberg' => "https://codeberg.org/$enc",
                'gitlab' => "https://gitlab.com/$enc",
                'linkedin' => $kind == "org" ? "https://linkedin.com/company/$enc" : "https://linkedin.com/in/$enc",
                'matrix' => "https://matrix.to/#/" . rawurlencode($raw),
                'pinterest' => "https://pinterest.com/$enc",
                'twitter' => "https://twitter.com/$enc",
                'youtube' => "https://youtube.com/@$enc",
                'facebook' => "https://facebook.com/$enc",
                'activitypub' => count($ap) == 2 ? "https://{$ap[1]}/@{$ap[0]}" : null,
                'bsky' => "https://bsky.app/profile/$enc",
                default => null,
              };
            ?>

            <?php if($url): ?>
              <a href="<?= esc_attr($url) ?>"><i class="<?= $icon ?>"></i> <?= esc_inner($social['handle']) ?></a>
            <?php else: ?>
              <span><i class="<?= $icon ?>"></i> <?= esc_inner($social['handle']) ?></span>
            <?php endif ?>
          </li>
        <?php endforeach ?>
      </ul>
    <?php endif ?>

    <?php if($item['urls']): ?>
      <h3>Websites</h3>
      <ul class="detail__urls">
        <?php foreach($item['urls'] as $u): ?>
          <li>
            <?php $label = trim($u['label']) ?>
            <?php $url = preg_replace('#^https?://#', "", $u['url']) ?>

            <a class="detail__url" href="<?= esc_attr($u['url']) ?>">
              <i class="fa-regular fa-globe"></i>
              <?php if($label): ?>
                <strong><?= esc_inner($label) ?></strong><br>
              <?php endif ?>
              <?= esc_inner($url) ?>
            </a>
          </li>
        <?php endforeach ?>
      </ul>
    <?php endif ?>

    <?php if($kind == "person" && $item['roles']): ?>
      <h3>Roles</h3>
      <ul class="detail__roles">
        <?php foreach($item['roles'] as $r): ?>
          <li>
            <strong><?= esc_inner($r['name']) ?></strong>
            <?php if(trim($r['function'] ?? '')): ?>
              <br><?= esc_inner($r['function']) ?>
            <?php endif ?>
          </li>
        <?php endforeach ?>
      </ul>
    <?php endif ?>
  </div>
</div>
