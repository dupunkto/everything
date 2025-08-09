<script lang="ts">
  import { Note } from "../../linio/types";
  import { listNotes } from "../../linio/api";

  import Item from "./Item.svelte";

  let notes: Note[] = $state([]);
  listNotes().then((n) => notes = n);

  let selected: string | null = $state(null);
  let selectNote = (n: Note) => selected = selected == n.id ? null : n.id;
</script>

<style>
  ul {
    list-style: none;
    padding: 0;
    margin: 0;
  }
</style>

<ul class="note-list">
  {#each notes as note}
    <li class="note-item">
      <Item {note} opened={note.id == selected} onclick={() => selectNote(note)} />
    </li>
  {/each}
</ul>