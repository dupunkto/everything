<!DOCTYPE html>
<html lang="en">
  <head>
    <?php include __DIR__ . "/shell/head.php" ?>
    <title>Wishlist</title>
    <link rel="stylesheet" href="<?= CANONICAL ?>/css/wishlist.css">
  </head>
  <body>
    <?php include __DIR__ . "/shell/menu.php" ?>
    <main class="main">
      <div class="page-header">
        <h1 class="page-header__title"><strong>Wishlist</strong></h1>

        <div class="view-nav view-nav--home">
          <div class="view-nav__default">
            <button type="button" data-toggle-bought><i class="fa-regular fa-eye-slash"></i> Show bought</button>
            <button type="button" data-view="shelves"><i class="fa-regular fa-box-archive"></i> Shelves</button>
          </div>
          <button type="button" class="view-nav__back" data-view="dream">&larr; Back to wishlist</button>
        </div>
      </div>

      <input
        type="hidden"
        id="wishlist-filter"
        name="show"
        value="dream"
        x-get="/wishlist/listing"
        x-on="input"
        x-target="#wishlist-listing"
      >

      <ul id="wishlist-listing" class="listing" x-get="/wishlist/listing" x-data="#wishlist-filter"></ul>

      <script type="module">
        const filter = document.querySelector('#wishlist-filter');
        const nav = document.querySelector('.view-nav');

        let view = 'dream';   // 'dream' or 'shelves'
        let bought = false;

        const apply = () => {
          nav.classList.toggle('view-nav--home', view === 'dream');
          nav.querySelector('[data-toggle-bought]').innerHTML = bought
            ? '<i class="fa-regular fa-eye"></i> Hide bought'
            : '<i class="fa-regular fa-eye-slash"></i> Show bought';

          filter.value = view === 'shelves' ? 'shelves' : (bought ? 'bought' : 'dream');
          filter.dispatchEvent(new Event('input', { bubbles: true }));
        };

        nav.addEventListener('click', (event) => {
          const button = event.target.closest('button');
          if(!button) return;

          if(button.dataset.toggleBought !== undefined) bought = !bought;
          else if(button.dataset.view) view = button.dataset.view;

          apply();
        });
      </script>

      <script type="module">
        const listing = document.querySelector('#wishlist-listing');

        document.addEventListener('keydown', (event) => {
          const items = [...listing.querySelectorAll('li')];
          const focused = document.activeElement;

          if(event.key == 'ArrowDown' || event.key == 'ArrowUp') {
            event.preventDefault();
            const idx = items.indexOf(focused);
            if(idx == -1) { items[0]?.focus(); return; }
            items[idx + (event.key == 'ArrowDown' ? 1 : -1)]?.focus();
            return;
          }

          if(focused?.matches('#wishlist-listing li')) {
            const id = focused.dataset.id;

            const statuses = { s: 'nvm', c: 'bought' };

            if(event.key in statuses) {
              event.preventDefault();

              // Pressing the shortcut again reverts to 'dream', so 's'
              // unshelves and 'c' un-buys.
              const target = focused.dataset.status == statuses[event.key] ? 'dream' : statuses[event.key];

              focused.querySelector('input[type=hidden][name=status]').value = target;
              focused.querySelector('input[type=checkbox][name=status]').checked = false;
              focused.querySelector('form').dispatchEvent(new Event('change', { bubbles: true }));
              return;
            }

            if(event.key == 'e' || event.key == 'o') {
              event.preventDefault();
              window.location.href = `/wishlist/edit?id=${id}`;
              return;
            }

            if(event.key == 'd') {
              event.preventDefault();
              if(!confirm('Delete this wish?')) return;
              window.location.href = `/wishlist/delete?id=${id}`;
              return;
            }
          }

          if(event.key == 'n') {
            event.preventDefault();
            window.location.href = '/wishlist/new';
          }
        });
      </script>
    </main>
  </body>
</html>
