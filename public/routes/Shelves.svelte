<script lang="ts">
  import { Note } from "../../linio/types"
  import { getConfig, listNotes } from "../../linio/api";
  import { keyboardNavigation } from "../../linio/keyboard";
  import { u } from "../../linio/navigation";

  import Item from "../components/Item.svelte";

  let config = await getConfig();

  let tasks: Record<string, Note[]> = $state({all : []});
  let lists: string[] = $state([]);

  listNotes().then((notes) => {
    for(const note of notes) {
      if (!note.task || note.task.status != 'nvm') continue;
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
        return compareDates(a.task.deadline, b.task.deadline);
      })
    }

    lists = Object.keys(tasks).sort((x, y) => {
      const ix = config.lists.indexOf(x);
      const iy = config.lists.indexOf(y);

      if (ix != -1 && iy != -1) return ix - iy;
      else if (ix !== -1) return -1;
      else if (iy !== -1) return 1;
      else return x.localeCompare(y);
    });
  });

  let visibleLists = $derived(lists.filter(list => tasks[list]?.length > 0));

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

  header h1 {
    margin: 0;
    transform: scale(2);
    transform-origin: left top;
  }

  .actions a {
    margin-bottom: 0;
  }

  .todos {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    grid-template-rows: masonry;
    column-gap: 1em;
    overflow-y: auto;
    padding-bottom: 4em;
  }

  section h2 {
    padding: 0;
    border: none;
    text-align: right;
  }
</style>

<header>
  <h1>Shelves</h1>

  <div class="actions">
    <a class="button" href={u`/ToDo`}>
      <i class="fas fa-arrow-left"></i>
      Back to ToDo
    </a>
  </div>
</header>

<div class="todos" use:keyboardNavigation={{ listClasses: visibleLists }}>
  {#each visibleLists as list}
    <section class={list}>
      <h2>~{list}</h2>

      {#each tasks[list] as note}
        <Item {note}
          from="/Backlog"
          opened={note.id == selected}
          onclick={() => selectNote(note)}
          hide_list={true}
        />
      {/each}
    </section>
  {/each}
</div>
