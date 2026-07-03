<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/shell/head.php" ?>
    <title>ToDo</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/todo.css">
  </head>
  <body>
    <?php include __DIR__ . "/shell/menu.php" ?>
    <main class="wide">
      <input
        id="todo-search"
        name="q"
        placeholder="is:todo +qdentity"
        value="is:todo"
        x-get="/todo/listing"
        x-on="input"
        x-target="#todo-listing"
      >

      <section id="todo-listing" x-get="/todo/listing" x-data="#todo-search"></section>

      <script type="module">
        document.addEventListener('keydown', (e) => {
          const search = document.querySelector('#todo-search');
          const focused = document.activeElement;
          const items = [...document.querySelectorAll('#todo-listing li')];

          // Navigation shortcuts

          if (e.key == 'ArrowDown' || e.key == 'ArrowUp') {
            e.preventDefault();

            // Up from the search bar does nothing.
            if(focused == search && e.key == 'ArrowUp') { return; }

            // Down from the search bar focuses the first item.
            if(focused == search && e.key == 'ArrowDown') { items[0]?.focus(); return; }

            const idx = items.indexOf(focused);

            // Up from the first item focuses the search bar.
            if(idx == 0 && e.key == 'ArrowUp') { search.focus(); return; }

            // If nothing is focused yet, focuses the first item.
            if (idx == -1) { items[0]?.focus(); return; }

            // For all the other items, up goes up and down goes down.
            items[idx + (e.key == 'ArrowDown' ? 1 : -1)]?.focus();

            return;
          }

          // Edit shortcuts

          if (focused?.matches('#todo-listing li')) {
            const id = focused.dataset.id;
            const idx = items.indexOf(focused);

            const statuses = { b: 'backlog', c: 'done', u: 'todo', s: 'nvm' };

            if (e.key in statuses) {
              e.preventDefault();
              focused.querySelector("input[type=hidden][name=status]").value = statuses[e.key];
              focused.querySelector("input[type=checkbox][name=status]").checked = false;
              focused.querySelector("form").dispatchEvent(new Event("change", { bubbles: true }));
            }
            else if (e.key === 'd') {
              e.preventDefault();
              if (!confirm('Delete this task?')) return;
              // TODO(robin): implement this!
            }
            else if (e.key === 'e' || e.key === 'o') {
              e.preventDefault();
              window.location.href = `/todo/edit?id=${id}`;
            }
          }
        });
      </script>
    </main>
  </body>
</html>
