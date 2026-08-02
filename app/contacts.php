<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/shell/head.php" ?>
    <title>Contacts</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/contacts.css">
    <script src="<?= CANONICAL ?>/client/contacts.js" type="module"></script>
  </head>
  <body>
    <?php include __DIR__ . "/shell/menu.php" ?>
    <?php
      $kind = @$_GET['kind'] ?: 'person';
      $query = cast_str(@$_GET['q']) ?? (isset($_GET['kind']) ? "is:$kind" : CONTACTS_DEFAULT_QUERY);
      $url = null;

      if(@$_GET['edit']) $url = "/contacts/edit?kind=" . rawurlencode($kind) . "&id=" . rawurlencode($_GET['edit']);
      elseif(@$_GET['view']) $url = "/contacts/detail?kind=" . rawurlencode($kind) . "&id=" . rawurlencode($_GET['view']);
    ?>
    <main class="main main--wide main--scrollable contacts <?= UI_SIDEBAR_POSITION == 'right' ? 'contacts--sidebar-right' : '' ?>" z-nav=".contacts__search, .contact-item">
      <aside class="contacts__sidebar">
        <form id="contacts-controls" class="contacts__controls">
          <input
            name="q"
            type="search"
            class="contacts__search"
            z-key="/"
            placeholder="is:person +acme"
            value="<?= esc_attr($query) ?>"
            x-get="/contacts/listing"
            x-on="input"
            x-target="#contacts-list"
            x-data="#contacts-controls"
          >
        </form>

        <section id="contacts-list" x-get="/contacts/listing" x-data="#contacts-controls">
          <?php fragment("contacts/listing", ["q" => $query]) ?>
        </section>
      </aside>

      <section id="contacts-panel" class="contacts__panel detail"<?php if($url): ?> x-get="<?= esc_attr($url) ?>"<?php endif ?>>
        <?php
          if(@$_GET['edit']) fragment("contacts/edit", ["kind" => $kind, "id" => $_GET['edit']]);
          elseif(@$_GET['view']) fragment("contacts/detail", ["kind" => $kind, "id" => $_GET['view']]);
        ?>
      </section>
    </main>
  </body>
</html>
