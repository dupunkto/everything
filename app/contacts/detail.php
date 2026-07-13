<?php
  // Contact detail view.

  $kind = @$_GET['kind'] ?? "person";
  $id = @$_GET['id'] ?? @$_POST['id'];

  if(!in_array($kind, ['person', 'org'])) 
    fail("Malformed 'kind' parameter.", status: 400);

  if(!$id)
    fail("Missing 'id' parameter.", status: 400);

  $item = $kind == "org" ? \store\get_organisation($id) : \store\get_contact($id);

  if(!$item) exit; // No row clears the panel.

  if($kind == "org") {
    $title = $item['display_name'];
    $legal = trim($item['legal_name'] ?? '');
    $subtitle = (is_nonempty_str($legal) && $legal !== $title) ? $legal : '';
  }
  else {
    $full = str_join(" ", [$item['first_name'], $item['middle_name'], $item['infix'], $item['last_name']]);
    $title = trim($item['display_name']) ?: $full;
    $subtitle = (is_nonempty_str($full) && $full !== $title) ? $full : '';
  }

  $timezone = null;

  foreach($item['addresses'] as $address) {
    $zone = $address['timezone'];

    if(is_nonempty_str($zone) && $zone !== TIMEZONE) {
      $timezone = $zone; break;
    }
  }

  $get_social_link = function($type, $handle) use ($kind) {
    $raw = trim($handle);
    $h = ltrim($raw, "@");
    $enc = rawurlencode($h);
    $ap = explode("@", $h); // activitypub: user@instance

    $icons = [
      'instagram' => 'fa-brands fa-instagram', 'discord' => 'fa-brands fa-discord',
      'snapchat' => 'fa-brands fa-snapchat', 'github' => 'fa-brands fa-github',
      'linkedin' => 'fa-brands fa-linkedin', 'matrix' => 'fa-solid fa-hashtag',
      'pinterest' => 'fa-brands fa-pinterest', 'twitter' => 'fa-brands fa-twitter',
      'youtube' => 'fa-brands fa-youtube', 'facebook' => 'fa-brands fa-facebook',
      'activitypub' => 'fa-brands fa-mastodon', 'atproto' => 'fa-brands fa-bluesky',
    ];

    $url = match($type) {
      'instagram'   => "https://instagram.com/$enc",
      'discord'     => ctype_digit($h) ? "https://discord.com/users/$enc" : null,
      'snapchat'    => "https://snapchat.com/add/$enc",
      'github'      => "https://github.com/$enc",
      'linkedin'    => $kind == "org" ? "https://linkedin.com/company/$enc" : "https://linkedin.com/in/$enc",
      'matrix'      => "https://matrix.to/#/" . rawurlencode($raw),
      'pinterest'   => "https://pinterest.com/$enc",
      'twitter'     => "https://twitter.com/$enc",
      'youtube'     => "https://youtube.com/@$enc",
      'facebook'    => "https://facebook.com/$enc",
      'activitypub' => count($ap) == 2 ? "https://{$ap[1]}/@{$ap[0]}" : null,
      'atproto'     => "https://bsky.app/profile/$enc",
      default       => null,
    };

    return [$icons[$type] ?? 'fa-solid fa-at', $url];
  };

?>
<header class="detail__header">
  <div class="detail__identity">
    <span class="detail__avatar"><i class="fa-solid fa-<?= $kind == "org" ? "building-columns" : "user" ?>"></i></span>
    <div>
      <h2><?= esc_inner($title) ?></h2>
      <?php if($subtitle): ?><p class="detail__subtitle"><?= esc_inner($subtitle) ?></p><?php endif ?>
    </div>
  </div>
  <div class="actions">
    <button type="button" data-edit x-get="/contacts/edit?kind=<?= $kind ?>&id=<?= $item['id'] ?>" x-target="#contacts-panel">Edit</button>
  </div>
</header>

<?php if($kind == "person" && $item['birth_day']):
  $bd = DateTime::createFromFormat('Y-m-d', $item['birth_day']) ?: null; ?>
  <?php if($bd): ?>
    <p class="detail__meta">
      <span><?= (new DateTime())->diff($bd)->y ?> yo</span>  
      <span><?= $bd->format('F, j') ?></span>
      <span><?= star_sign((int)$bd->format('n'), (int)$bd->format('j')) ?></span>
      <?php if($timezone): ?>
        <span title="<?= esc_attr($timezone) ?>"><?= local_date('H:i', 'now', $timezone) ?></span>
      <?php endif ?>
    </p>
  <?php endif ?>
<?php elseif($kind == "person" && $timezone): ?>
  <p class="detail__meta">
    <span title="<?= esc_attr($timezone) ?>"><?= local_date('H:i', 'now', $timezone) ?></span>
  </p>
<?php endif ?>

<div class="detail__cols">
  <div class="detail__main">
    <?php
      $comms = [
        ['fa-solid fa-phone', 'tel:', array_map(fn($r) => [$r['label'], $r['phone_number']], $item['phones'])],
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
            <p class="detail__address-lines"><?= esc_inner(str_join("\n", $lines)) ?></p>
            <?php if($maps = maps_url(MAP_PROVIDER, address_line($address))): ?>
            <a class="button detail__direction" href="<?= esc_attr($maps) ?>">Directions &rarr;</a>
            <?php endif ?>
          </div>
        <?php endforeach ?>
      </div>
    <?php endif ?>

    <?php include __DIR__ . "/note.php" ?>
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
      <h3>Socials</h3>
      <ul class="detail__socials">
        <?php foreach($item['socials'] as $s): ?>
          <li>
            <?php [$icon, $url] = $get_social_link($s['type'], $s['handle']) ?>

            <?php if($url): ?>
              <a href="<?= esc_attr($url) ?>"><i class="<?= $icon ?>"></i> <?= esc_inner($s['handle']) ?></a>
            <?php else: ?>
              <span><i class="<?= $icon ?>"></i> <?= esc_inner($s['handle']) ?></span>
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

<?php if($item['note']): ?>
  <div class="detail__note"><?= nl2br(esc_inner($item['note'])) ?></div>
<?php endif ?>
