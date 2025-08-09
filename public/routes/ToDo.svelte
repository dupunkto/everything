<script lang="ts">
  import { listNotes } from "../../linio/api";
  import Item from "./Item.svelte";

  let tasks = $state([]);

  listNotes().then((notes) => {
    for(const note of notes) {
      if (!note.task) continue;
      const list = note.task.list;

      if(list) {
        if(!tasks[list]) tasks[list] = [];
        tasks[list].push(note)
      } else {
        tasks.all.push(note);
      }
    }

    for(const list of Object.keys(tasks)) {
      tasks[list].sort((a, b) => {
        const statuses = ['todo', 'done', 'nvm'];
        const diff = statuses.indexOf(a.task.status) - statuses.indexOf(b.task.status);

        if (diff != 0) return diff;

        switch(a.task.status) {
          case 'todo':  return (a.task.deadline || Infinity) - (b.task.deadline || Infinity);
          case 'done': return (a.task.completed_at || 0) - (b.task.completed_at || 0);
          case 'nvm': return (a.task.shelved_at || 0) - (b.task.shelved_at || 0);
        }
      })
    }
  });

  let selected: string | null = $state(null);
  let selectNote = (n: Note) => selected = selected == n.id ? null : n.id;
</script>

{#each Object.keys(tasks) as list}
  <section>
    <h2>~{list}</h2>

    {#each tasks[list] as note}
      <Item {note} opened={note.id == selected} onclick={() => selectNote(note)} />
    {/each}
  </section>
{/each}