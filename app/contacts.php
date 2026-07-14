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
    <main class="main main--wide contacts">
      <aside class="contacts__sidebar">
        <form id="contacts-controls" class="contacts__controls">
          <input
            name="q"
            type="search"
            class="contacts__search"
            z-key="/"
            placeholder="is:person +acme"
            value="is:person"
            x-get="/contacts/listing"
            x-on="input"
            x-target="#contacts-list"
            x-data="#contacts-controls"
          >
        </form>

        <section id="contacts-list" x-get="/contacts/listing" x-data="#contacts-controls"></section>
      </aside>

      <section id="contacts-panel" class="contacts__panel"></section>
    </main>
  </body>
</html>
