<script lang="ts">
  import { Note } from "../../linio/types";
  import { listNotes } from "../../linio/api";
  import { keyboardNavigation } from "../../linio/keyboard";

  import Item from "../components/Item.svelte";

  let notes: Note[] = $state(await listNotes());

  let view_bought = $state(false);

  const isVisible = (note: Note) => {
    return note.wish?.status != 'bought' || view_bought;
  };

  let filteredNotes = $derived.by((): Note[] => {
    const wishes = notes.filter(note => note.type == 'wish');

    return wishes.sort((a, b) => {
      const aBought = a.wish?.status == 'bought';
      const bBought = b.wish?.status == 'bought';

      if (aBought && !bBought) return 1;
      if (!aBought && bBought) return -1;

      return b.created_at.localeCompare(a.created_at);
    });
  });

  let selected: string | null = $state(null);
  let selectNote = (n: Note) => selected = selected == n.id ? null : n.id;
</script>

<style>
  ul {
    list-style: none;
    padding: 0;
    margin: 0;
    overflow-y: auto;
    flex-grow: 1;
  }

  .wishlist {
    display: flex;
    flex-direction: column;
    height: 100%;
  }

  .wishlist header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1.5em;
    margin-bottom: 0.5em;
  }

  .wishlist header h1 {
    margin-top: 1em;
    margin-bottom: 0.1em;
  }

  .actions {
    display: flex;
    gap: 0.2em;
    justify-content: flex-end;
  }

  .actions button {
    margin-bottom: 0;
  }
</style>

<div
  class="wishlist"
  use:keyboardNavigation={{ listClasses: ['note-list'] }}
>
  <header>
    <h1>Wishlist</h1>
    <div class="actions">
      <button onclick={() => view_bought = !view_bought}>
        <i class="{view_bought ? "fas" : "far"} fa-eye-slash"></i>
        {view_bought ? "Hide bought" : "Show bought"}
      </button>
    </div>
  </header>

  <ul class="note-list">
    {#each filteredNotes as note}
      {#if isVisible(note)}
        <li class="note-item">
          <Item {note}
            from="/Wishlist"
            opened={note.id == selected}
            onclick={() => selectNote(note)}
          />
        </li>
      {/if}
    {/each}
  </ul>
</div>
