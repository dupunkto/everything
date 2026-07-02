<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/../shell/head.php" ?>
    <title>Settings</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/settings.css">
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/settings/subscriptions.css">
  </head>
  <body>
    <?php include __DIR__ . "/../shell/menu.php" ?>
    <main>
      <header class="bar">
        <h2>Subscriptions</h2>
        <a class="button" z-toggle="#subscription-new">Add subscription</a>
      </header>

      <section id="subscription-new" x-get="/settings/subscriptions/new" hidden></section>
      <section id="subscriptions-listing" x-get="/settings/subscriptions/listing"></section>
    </main>

    <script>
      // Stand-in for `z-toggle` — flips the [hidden] attribute on the
      // element matched by the attribute's selector when the host is
      // clicked. zhtml.js should subsume this and generalize over arbitrary
      // attribute toggles (hidden, disabled, checked, aria-*, etc.) plus
      // class manipulation, with the same selector resolution rules as
      // xhtml's x-target.
      //
      // The Escape-to-dismiss bit below is the same generalization in
      // disguise: a future `z-dismiss-on="escape"` (or `z-toggle-on=...`)
      // attribute would render this loop redundant.
      for(const node of document.querySelectorAll("[z-toggle]")) {
        const target = document.querySelector(node.getAttribute("z-toggle"));
        if(!target) continue;

        node.addEventListener("click", () => target.hidden = !target.hidden);

        document.addEventListener("keydown", (event) => {
          if(event.key == "Escape" && !target.hidden) target.hidden = true;
        });
      }
    </script>
  </body>
</html>