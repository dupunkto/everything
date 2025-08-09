<script lang="ts">
  import { Note } from "../../linio/types";
  import { fetchNote, completeTask } from "../../linio/api";

  let { route } = $props();
  let id = route.result.path.params.id;
  
  let note: Note = $state({});
  fetchNote(id).then((n) => note = n);
</script>

<style>
  input[type="checkbox"] {
    transform: scale(5);
    transform-origin: left top;
    margin-left: -90px;
    margin-right: 70px;
    float: left;
  }

  article {
    clear: left;
  }
</style>

<article>
  {#if note.task}
    <input
      type="checkbox"
      checked={note.task.status != 'todo'}
      disabled={note.task.status == 'nvm'}
      onclick={async (e) => {
        e.stopPropagation();
        e.target.disabled = true;
        const updated = await completeTask(note, e.target.checked);
        e.target.disabled = false;
        e.target.checked = updated.task.status == 'done';
      }}
    >
  {/if}

  {#if note.title}
    <h1>{note.title}</h1>
  {/if}

  {@html note.html}

  <footer>
    
  </footer>
</article>