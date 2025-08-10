<script lang="ts">
  import { onMount, onDestroy } from 'svelte';

  import { Note } from "../../linio/types";
  import { listNotes } from "../../linio/api";

  import Item from "../components/Item.svelte";

  let notes: Note[] = $state(await listNotes());

  let selected: string | null = $state(null);
  let selectNote = (n: Note) => selected = selected == n.id ? null : n.id;

  function handleKey(e: KeyboardEvent) {
    const isEditable = (element: Element | null) =>
      (element as HTMLElement)?.isContentEditable ||
      element?.tagName === 'INPUT' || 
      element?.tagName === 'TEXTAREA';

    if(e.key == '/' && !isEditable(document.activeElement)) {
      e.preventDefault();
      document.querySelector("input")?.focus();
    }
  }

  onMount(() => window.addEventListener('keydown', handleKey));
  onDestroy(() => window.removeEventListener('keydown', handleKey));
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
    font-size: 1em;
    padding: .5em .8em;
    margin-bottom: 1em;
  }
</style>

<input placeholder="Search...">

<ul class="note-list">
  {#each notes as note}
    <li class="note-item">
      <Item {note} opened={note.id == selected} onclick={() => selectNote(note)} from="/Index" />
    </li>
  {/each}
</ul>