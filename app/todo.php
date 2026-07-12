<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/shell/head.php" ?>
    <title>ToDo</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/todo.css">
  </head>
  <body>
    <?php include __DIR__ . "/shell/menu.php" ?>
    <main class="main main--wide">
      <div class="page-header">
        <h1 class="page-header__title"><strong id="todo-title">ToDo</strong></h1>

        <input
          id="todo-search"
          class="page-header__search"
          name="q"
          placeholder="is:todo +qdentity"
          value="is:todo"
          x-get="/todo/listing"
          x-on="input"
          x-target="#todo-listing"
        >

        <div class="view-nav view-nav--home">
          <div class="view-nav__default">
            <button type="button" data-toggle-finished><i class="fa-regular fa-eye-slash"></i> Show finished</button>
            <button type="button" data-view="shelves"><i class="fa-regular fa-box-archive"></i> Shelves</button>
            <button type="button" data-view="backlog"><i class="fa-regular fa-folder-open"></i> Backlog</button>
          </div>
          <button type="button" class="view-nav__back" data-view="todo">&larr; Back to todo</button>
        </div>
      </div>

      <section id="todo-listing" class="listing listing--masonry" x-get="/todo/listing" x-data="#todo-search"></section>

      <script type="module">
        const search = document.querySelector('#todo-search');
        const title = document.querySelector('#todo-title');
        const nav = document.querySelector('.view-nav');

        const VIEWS = {
          todo:    { label: 'ToDo',    query: 'is:todo' },
          backlog: { label: 'Backlog', query: 'is:backlog' },
          shelves: { label: 'Shelves', query: 'is:nvm' },
        };

        let view = 'todo';
        let finished = false;

        const apply = () => {
          let query = VIEWS[view].query;
          if(view === 'todo' && finished) query += ' is:done';

          title.textContent = VIEWS[view].label;
          nav.classList.toggle('view-nav--home', view === 'todo');
          nav.querySelector('[data-toggle-finished]').innerHTML = finished
            ? '<i class="fa-regular fa-eye"></i> Hide finished'
            : '<i class="fa-regular fa-eye-slash"></i> Show finished';

          search.value = query;
          search.dispatchEvent(new Event('input', { bubbles: true }));
        };

        nav.addEventListener('click', (event) => {
          const button = event.target.closest('button');
          if(!button) return;

          if(button.dataset.toggleFinished !== undefined) finished = !finished;
          else if(button.dataset.view) view = button.dataset.view;

          apply();
        });
      </script>

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

          // Letter shortcuts must not hijack typing in the search field.
          if (focused == search) return;

          // Edit shortcuts

          if (focused?.matches('#todo-listing li')) {
            const id = focused.dataset.id;
            const idx = items.indexOf(focused);

            const statuses = { b: 'backlog', c: 'done', u: 'todo', s: 'nvm' };

            if (e.key in statuses) {
              e.preventDefault();

              // Pressing a status shortcut on a task already in that status
              // reverts it to plain 'todo' ('s' unshelves, 'c' uncompletes,
              // 'b' unbacklogs).
              let target = statuses[e.key];
              if (focused.dataset.status == target) target = 'todo';

              focused.querySelector("input[type=hidden][name=status]").value = target;
              focused.querySelector("input[type=checkbox][name=status]").checked = false;
              focused.querySelector("form").dispatchEvent(new Event("change", { bubbles: true }));
            }
            else if (e.key == 'd') {
              e.preventDefault();
              if (!confirm('Delete this task?')) return;
              window.location.href = `/todo/delete?id=${id}`;
            }
            else if (e.key == 'e' || e.key == 'o') {
              e.preventDefault();
              window.location.href = `/todo/edit?id=${id}`;
            }
          }

          if (e.key == 'n') {
            e.preventDefault();
            window.location.href = `/todo/new`;
          }
        });
      </script>
    </main>
  </body>
</html>
