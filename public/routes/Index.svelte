<script lang="ts">
  import { Note } from "../../linio/types";
  import { listNotes } from "../../linio/api";

  import Item from "../components/Item.svelte";

  let notes: Note[] = $state(await listNotes());

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

  input {
    background: #fefefe;
    font-size: 1.2em;
    margin-bottom: 1em;
  }
</style>

<h1>Index</h1>

<input placeholder="Search...">

<ul class="note-list">
  {#each notes as note}
    <li class="note-item">
      <Item {note} opened={note.id == selected} onclick={() => selectNote(note)} from="/Index" />
    </li>
  {/each}
</ul>