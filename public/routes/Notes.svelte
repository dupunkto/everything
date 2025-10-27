<script lang="ts">
  import { Note } from "../../linio/types"
  import { listNotes } from "../../linio/api";

  import Item from "../components/Item.svelte";

  let notes: Note[] = $state([]);

  listNotes().then((all) => {
    notes = all
      .filter(note => note.type == 'note')
      .sort((a, b) => new Date(b.modified_at).getTime() - new Date(a.modified_at).getTime());
  });
</script>

<style>
  @media (min-width: 815px) {
    :global(main:has(.notes)) {
      max-width: calc(100% - var(--sidebar-width));
    }
  }

  :global(main:has(.notes)) {
    margin: 0;
    padding: 0;
  }

  @media (max-width: 815px) {
    header {
      margin-top: 5em;
    }
  }

  header, .notes {
    padding: 0 min(3em, 3vw);
  }

  header {
    margin-top: 1.5em;
    position: relative;
    z-index: 1;
  }

  header h1 {
    margin: 0;
    transform: scale(2);
    transform-origin: left top;
  }

  .notes {
    margin-top: 3em;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    grid-template-rows: masonry;
    column-gap: 1em;
    overflow-y: auto;
    padding-bottom: 4em;
  }
</style>

<header>
  <h1>Notes</h1>
</header>

<div class="notes">
  {#each notes as note}
    <Item {note}
      from="/Notes"
      opened={true}
      onclick={() => {}}
    />
  {/each}
</div>
