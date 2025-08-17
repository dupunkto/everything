<script lang="ts">
  import { Note } from "../../linio/types";
  import { listNotesByTag } from "../../linio/api";

  import Item from "../components/Item.svelte";

  let { route } = $props();

  let tag = route.result.path.params.tag;
  let notes: Note[] = $derived(await listNotesByTag(tag));
</script>

<style>
  ul {
    list-style: none;
    padding: 0;
    margin: 0;
    overflow-y: auto;
    flex-grow: 1;
  }
  
  .placeholder-text {
    color: var(--color-text-muted);
    font-style: italic;
  }
</style>

<main>
  <h1>[[{tag}]]</h1>
  
  {#if notes.length == 0}
    <p class="placeholder-text">No notes found with this tag.</p>
  {:else}
    <ul class="note-list">
      {#each notes as note}
        <li class="note-item">
          <Item {note}
            from="/Tag/{tag}"
            opened={true}
            onclick={() => navigate(`/${note.id}`)}
          />
        </li>
      {/each}
    </ul>
  {/if}
</main>