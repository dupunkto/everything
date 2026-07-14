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
          <div class="contacts__tabs">
            <button type="button" data-type="person" class="is-active"><i class="fa-solid fa-people-group"></i> People</button>
            <button type="button" data-type="org"><i class="fa-solid fa-building-columns"></i> Organisations</button>
          </div>
          <input
            name="q"
            class="contacts__search"
            placeholder="is:person +acme"
            value="is:person"
            x-get="/contacts/listing"
            x-on="input"
            x-target="#contacts-list"
            x-data="#contacts-controls"
          >
        </form>

        <section id="contacts-list" class="contacts__list" x-get="/contacts/listing" x-data="#contacts-controls"></section>

      </aside>

      <section id="contacts-panel" class="contacts__panel">
      </section>
    </main>

    <script type="module">
      const controls = document.getElementById('contacts-controls');
      const search = controls.querySelector('[name="q"]');
      const panel = document.getElementById('contacts-panel');
      const list = document.getElementById('contacts-list');
      const tabs = controls.querySelectorAll('.contacts__tabs button');

      const deselect = () => { panel.innerHTML = ''; };

      // A tab replaces all is: filters with its own. (Both can still be stacked
      // by typing them; the active states reflect whatever is in the query.)
      const syncTabs = () => {
        const tokens = search.value.split(/\s+/).filter(Boolean);
        const person = tokens.includes('is:person'), org = tokens.includes('is:org');
        const active = { person: person || !org, org }; // no filter defaults to people
        tabs.forEach(b => b.classList.toggle('is-active', active[b.dataset.type]));
      };

      tabs.forEach(btn => btn.addEventListener('click', () => {
        const parts = search.value.split(/\s+/).filter(Boolean)
          .filter(p => p !== 'is:person' && p !== 'is:org');
        parts.unshift(`is:${btn.dataset.type}`);
        search.value = parts.join(" ");
        search.dispatchEvent(new Event('input', { bubbles: true }));
      }));

      search.addEventListener('input', syncTabs);
      syncTabs();

      // Deselect the open contact once it drops out of the re-filtered list,
      // but only in view mode — never yank it out from under an edit.
      list.addEventListener('x-swap', () => {
        const open = panel.querySelector('[data-edit]');
        if(!open) return;
        const url = new URL(open.getAttribute('x-get'), location.href);
        const selector = `.contact-item[data-id="${url.searchParams.get('id')}"][data-kind="${url.searchParams.get('kind')}"]`;
        if(!list.querySelector(selector)) deselect();
      });

      document.addEventListener('keydown', (e) => {
        if(document.activeElement?.matches('input, textarea, select, [contenteditable]')) return;

        // 'Escape' cancels an edit, or deselects the contact in view mode.
        if(e.key === 'Escape') {
          const cancel = panel.querySelector('[data-cancel]');
          if(cancel) { e.preventDefault(); cancel.click(); }
          else if(panel.querySelector('[data-edit]')) { e.preventDefault(); deselect(); }
        }

        // 'e' opens the edit form for whatever is showing in the panel.
        if(e.key === 'e') {
          const edit = document.querySelector('#contacts-panel [data-edit]');
          if(edit) { e.preventDefault(); edit.click(); }
        }

        // 'n' starts a new contact/org of the active tab in the panel.
        if(e.key === 'n') {
          e.preventDefault();
          document.getElementById('contacts-new').click();
        }
      });

      // Mark the edit form dirty on any field change or row add/remove.
      document.addEventListener('input', (e) =>
        e.target.closest('.detail__edit')?.setAttribute('data-dirty', ''));
      document.addEventListener('click', (e) => {
        if(e.target.closest('[data-add], [data-remove]'))
          e.target.closest('.detail__edit')?.setAttribute('data-dirty', '');
      });

      // Confirm before cancelling a form with unsaved edits. Capture phase, so
      // it runs before xhtml's click handler and can stop the swap.
      document.addEventListener('click', (e) => {
        const cancel = e.target.closest('[data-cancel]');
        if(cancel?.closest('.detail__edit')?.hasAttribute('data-dirty') && !confirm('Discard unsaved changes?')) {
          e.preventDefault();
          e.stopImmediatePropagation();
        }
      }, true);
    </script>
  </body>
</html>
