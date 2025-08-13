<script lang="ts">
  import { onMount, onDestroy } from 'svelte';

  import { Note } from "../../linio/types";
  import { listNotes } from "../../linio/api";
  import { navigate } from "../../linio/navigation";
  import { keyboardNavigation } from "../../linio/keyboard";

  import Item from "../components/Item.svelte";

  let { route } = $props();

  let autofocus = $derived(route.result.querystring.params.autofocus);

  let notes: Note[] = $state(await listNotes());
  let query = $state('');

  interface Selectors {
    type?: string[];
    list?: string[];
    status?: string[];
    due?: string[];
    completed?: string[];
    shelved?: string[];
    created?: string[];
    modified?: string[];
  }

  function parseQuery(query: string): { selectors: Selectors; terms: string[] } {
    const selectors: Selectors = {};
    const attributes = ['type', 'status', 'list', 'due', 'completed', 'shelved', 'created', 'modified'];
    const matches = [...query.matchAll(/(\w+):(\S+)/g)];
    
    matches
      .filter(([, key]) => attributes.includes(key))
      .forEach(([match, key, value]) => {
        (selectors[key as keyof Selectors] ??= []).push(value);
        query = query.replace(match, '');
      });

    const terms = query.split(/\s+/).filter(Boolean);
    return { selectors, terms };
  }

  function selectorsMatches(note: Note, selectors: Selectors): boolean {
    if (selectors.type && !selectors.type.some(type => note.type === type)) return false;
    
    if (selectors.list && (!note.task?.list || !selectors.list.some(list => 
      note.task?.list == list))) return false;
    
    if (selectors.status && (!note.task || !selectors.status.some(status => 
      note.task?.status == status))) return false;
    
    if (selectors.due && (!note.task?.deadline || !selectors.due.some(due => 
      note.task!.deadline == due))) return false;
    
    if (selectors.completed && (!note.task?.completed_at || !selectors.completed.some(completed => 
      note.task!.completed_at == completed))) return false;
    
    if (selectors.shelved && (!note.task?.shelved_at || !selectors.shelved.some(shelved => 
      note.task!.shelved_at == shelved))) return false;

    if (selectors.created && !selectors.created.some(created => 
      note.created_at == created)) return false;

    if (selectors.modified && !selectors.modified.some(modified => 
      note.modified_at == modified)) return false;

    return true;
  }

  function queryMatches(note: Note, terms: string[]): boolean {
    if (terms.length == 0) return true;
    
    const haystack = [note.title, note.headline, note.text]
      .filter(Boolean)
      .join(' ')
      .toLowerCase();

    return terms.every(needle => haystack.includes(needle.toLowerCase()));
  }

  let view_completed = $state(true);
  let view_shelved = $state(false);

  const isVisible = (note: Note) => {
    return note.type != 'task'
      || note.task?.status == 'todo'
      || (note.task?.status == 'done' && view_completed)
      || (note.task?.status == 'nvm' && view_shelved)
  }

  let filteredNotes = $derived.by((): Note[] => {
    if (query.trim() == '') return notes;
    const { selectors, terms } = parseQuery(query);

    return notes.filter(note => 
      selectorsMatches(note, selectors) && queryMatches(note, terms)
    );
  });

  function handleWindowKey(e: KeyboardEvent) {
    const isEditable = (element: Element | null) =>
      (element as HTMLElement)?.isContentEditable ||
      element?.tagName == 'INPUT' || 
      element?.tagName == 'TEXTAREA';

    if(isEditable(document.activeElement)) return;

    if(e.key == '/') {
      e.preventDefault();
      document.querySelector("input")?.focus();
    }
  }

  onMount(() => window.addEventListener('keydown', handleWindowKey));
  onDestroy(() => window.removeEventListener('keydown', handleWindowKey));

  $effect(() => {
    if(autofocus) document.querySelector("input")?.focus();
  });
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
    background: var(--color-bg-surface);
    font-size: 1em;
    padding: .5em .8em;
  }

  .search {
    display: flex;
    flex-direction: column;
    height: 100%;
  }

  .actions {
    display: flex;
    gap: 0.2em;
    justify-content: flex-end;
  }
</style>

<div
  class="search"
  use:keyboardNavigation={{ listClasses: ['note-list'], inputClass: 'search-input' }}
>
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

  <input class="search-input" placeholder="Search..." bind:value={query}>

  <ul class="note-list">
    {#each filteredNotes as note}
      {#if isVisible(note)}
        <li class="note-item">
          <Item {note}
            from="/Search"
            opened={false}
            onclick={() => navigate(`/${note.id}`)}
          />
        </li>
      {/if}
    {/each}
  </ul>
</div>