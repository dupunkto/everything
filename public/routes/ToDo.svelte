<script lang="ts">
  import { Note } from "../../linio/types"
  import { listNotes } from "../../linio/api";
  
  import Item from "../components/Item.svelte";

  let tasks: Record<string, Note[]> = $state({all : []});

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

    for(const list of Object.keys(tasks)) {
      tasks[list].sort((a: Note, b: Note) => {
        const statuses = ['todo', 'done', 'nvm'];
        const diff = statuses.indexOf(a.task.status) - statuses.indexOf(b.task.status);

        if (diff != 0) return diff;

        switch(a.task.status) {
          case 'todo':  return (a.task.deadline || Infinity) - (b.task.deadline || Infinity);
          case 'done': return (a.task.completed_at || 0) - (b.task.completed_at || 0);
          case 'nvm': return (a.task.shelved_at || 0) - (b.task.shelved_at || 0);
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
      max-width: calc(100% - 370px);
    }
  }

  :global(main:has(.todos)) {
    margin: 0;
    padding: 0;
  }

  .todos {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 1em;
    padding: 0 min(3em, 3vw);
  }

  section h2 {
    padding: 0;
    border: none;
    text-align: right;
  }
</style>

<div class="todos">
  {#each Object.keys(tasks) as list}
    <section>
      <h2>~{list}</h2>

      {#each tasks[list] as note}
        <Item {note} opened={note.id == selected} onclick={() => selectNote(note)} />
      {/each}
    </section>
  {/each}
</div>