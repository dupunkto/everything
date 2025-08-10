<script lang="ts">
  import { onMount, onDestroy } from 'svelte';

  import { Note } from "../../linio/types";
  import { listNotes } from "../../linio/api";

  import Item from "../components/Item.svelte";

  let notes: Note[] = $state(await listNotes());
  let query = $state('');

  let view_completed = $state(true);
  let view_shelved = $state(false);

  const isVisible = (note: Note) => {
    return note.type != 'task'
      || note.task.status == 'todo'
      || (note.task.status == 'done' && view_completed)
      || (note.task.status == 'nvm' && view_shelved)
  }

  let selected: string | null = $state(null);
  let selectNote = (n: Note) => selected = selected == n.id ? null : n.id;

  let filteredNotes = $derived(query.trim() 
    ? notes.filter(note => [note.title, note.headline, note.text, note.task?.list].some(
        field => field?.toLowerCase().includes(query.toLowerCase()))) : notes);

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
  }

  .actions {
    display: flex;
    gap: 0.2em;
    justify-content: flex-end;
  }
</style>

<div class="actions">
  <button onclick={() => view_completed = !view_completed}>
    <i class="{view_completed ? "fas" : "far"} fa-eye-slash"></i>
    {view_completed ? "Hide finished" : "Show finished"}
  </button>

  <button onclick={() => view_shelved = !view_shelved}>
    <i class="{view_shelved ? "fas" : "far"} fa-eye-slash"></i>
    {view_shelved ? "Hide shelved" : "Show shelved"}
  </button>
</div>

<input placeholder="Search..." bind:value={query}>

<ul class="note-list">
  {#each filteredNotes as note}
    {#if isVisible(note)}
      <li class="note-item">
        <Item {note}
          from="/Index"
          opened={note.id == selected}
          onclick={() => selectNote(note)}
          truncate_at={70}
        />
      </li>
    {/if}
  {/each}
</ul>