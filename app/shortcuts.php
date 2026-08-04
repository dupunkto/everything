<?php

  $address_shortcuts = MAP_PROVIDER == 'none' ? [] : [
    ["g", "Go to " . map_provider_label()],
  ];

  $groups = [
    "Global" => [
      ["mod+/ / mod+k", "Open global search"],
      ["mod+shift+n", "Create new " . application_label(UI_INSERT_APPLICATION)],
      ["1", "Open mail"],
      ["2", "Open calendar"],
      ["3", "Open ToDo"],
      ["4", "Open tracker"],
      ["5", "Open notes"],
      ["6", "Open bookmarks"],
      ["7", "Open wishlist"],
      ["8", "Open contacts"],
      ["9", "Open addresses"],
      ["0", "Open settings"],
      ["?", "Open shortcuts"],
      ["mod+enter", "Submit or save"],
      ["escape", "Cancel or dismiss"],
    ],
    "Lists" => [
      ["/", "Focus search"],
      ["r", "Refresh"],
      ["n", "Create new item"],
      ["↑ / ↓", "Select item"],
      ["enter / o", "Open selected item"],
      ["e", "Edit selected item"],
      ["d", "Delete selected item"],
    ],
    "ToDo" => [
      ["c", "Toggle done"],
      ["u", "Revert to todo"],
      ["m", "Mark circled"],
      ["b", "Toggle backlog"],
      ["s", "Toggle shelved"],
      ["w", "Toggle wip"],
      ["x", "Toggle blocked"],
    ],
    "Wishlist" => [
      ["c", "Toggle bought"],
      ["s", "Toggle shelved"],
    ],
    "Bookmarks" => [
      ["g", "Go to bookmark"],
    ],
    "Addresses" => $address_shortcuts,
  ];

  $key = function($key) {
    $label = match($key) {
      "mod" => '<span data-shortcut-mod>Control</span>',
      "enter" => "Enter",
      "escape" => "Esc",
      default => $key,
    };

    return "<kbd>" . ($key == "mod" ? $label : esc_inner($label)) . "</kbd>";
  };

  $keys = function($combo) use ($key) {
    return implode(" / ", array_map(
      fn($alternative) => implode(" + ", array_map($key, explode("+", $alternative))),
      preg_split('/\s+\/\s+/', $combo)
    ));
  };

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/shell/head.php" ?>
    <title>Shortcuts</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/settings.css">
  </head>
  <body>
    <?php include __DIR__ . "/shell/menu.php" ?>
    <main class="main main--semi-wide">
      <header class="page-header">
        <?php $parent = "/settings/general"; include __DIR__ . "/settings/back.php" ?>
        <h2>Shortcuts</h2>
      </header>

      <?php foreach(array_filter($groups) as $title => $shortcuts): ?>
        <section class="shortcuts-group">
          <h3><?= esc_inner($title) ?></h3>
          <table class="shortcuts-table">
            <tbody>
              <?php foreach($shortcuts as $shortcut): ?>
                <tr>
                  <th scope="row"><?= $keys($shortcut[0]) ?></th>
                  <td><?= esc_inner($shortcut[1]) ?></td>
                </tr>
              <?php endforeach ?>
            </tbody>
          </table>
        </section>
      <?php endforeach ?>
    </main>

    <script>
      for(const key of document.querySelectorAll("[data-shortcut-mod]")) {
        key.innerText = navigator.userAgent.includes("Mac") ? "⌘" : "Control";
      }
    </script>
  </body>
</html>
