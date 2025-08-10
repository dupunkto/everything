<script lang="ts">
  import { Note } from "../../linio/types"
  import { listNotes } from "../../linio/api";
  
  import Item from "../components/Item.svelte";

  let tasks: Record<string, Note[]> = $state({all : []});

  let view_completed = $state(false);
  let view_shelved = $state(false);

  const isVisible = (note: Note) => {
    if(!note.task) return false;
    else return note.task.status == 'todo'
      || (note.task.status == 'done' && view_completed)
      || (note.task.status == 'nvm' && view_shelved)
  }

  listNotes().then((notes) => {
    for(const note of notes) {
      if (!note.task) continue;
      const list: string | undefined = note.task.list;

      if(list) {
        if(!tasks[list]) tasks[list] = [];
        tasks[list].push(note)
      } else {
        tasks.all.push(note);
      }
    }

    const compareDates = (a: string | undefined, b: string | undefined) => {
      if(!a && !b) return 0; if(!a) return 1; if(!b) return -1;
      return new Date(a).getTime() - new Date(b).getTime();
    };

    for(const list of Object.keys(tasks)) {
      tasks[list].sort((a: Note, b: Note) => {
        if(!a.task || !b.task) return 0;
        
        const statuses = ['todo', 'done', 'nvm'];
        const diff = statuses.indexOf(a.task.status) - statuses.indexOf(b.task.status);

        if (diff != 0) return diff;

        switch(a.task.status) {
          case 'todo': return compareDates(a.task.deadline, b.task.deadline);
          case 'done': return compareDates(b.task.completed_at, a.task.completed_at);
          case 'nvm': return compareDates(b.task.shelved_at, a.task.shelved_at);
          default: return 0;
        }
      })
    }
  });

  let selected: string | null = $state(null);
  let selectNote = (n: Note) => selected = selected == n.id ? null : n.id;
</script>

<style>
  @media (min-width: 815px) {
    :global(main:has(.todos)) {
      max-width: calc(100% - 400px);
    }
  }

  :global(main:has(.todos)) {
    margin: 0;
    padding: 0;
  }

  @media (max-width: 815px) {
    header {
      margin-top: 5em;
    }
  }

  header, .todos {
    padding: 0 min(3em, 3vw);
  }

  header {
    margin-top: 1.5em;
    position: relative;
    z-index: 1;
    display: flex;
    gap: 0.5em;
    align-items: center;
    justify-content: space-between;
  }

  header h1, header button {
    margin: 0;
  }

  header h1 {
    transform: scale(2);
    transform-origin: left top;
  }

  .actions button {
    margin-bottom: 0;
  }

  .todos {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 1em;
    overflow-y: auto;
  }

  section h2 {
    padding: 0;
    border: none;
    text-align: right;
  }
</style>

<header class="actions">
  <h1>ToDo</h1>

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
</header>

<div class="todos">
  {#each Object.keys(tasks) as list}
    {#if tasks[list].length > 0}
      <section>
        <h2>~{list}</h2>

        {#each tasks[list] as note}
          {#if isVisible(note)}
            <Item {note} opened={note.id == selected} onclick={() => selectNote(note)} from="/ToDo" />
          {/if}
        {/each}
      </section>
    {/if}
  {/each}
</div>