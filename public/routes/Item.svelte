<script lang="ts">
  import { completeTask } from "../../linio/api";
  import { navigate } from "../helpers";
  let { note, opened, onclick } = $props();
</script>

<style>
  section {
    position: relative;
    font-size: 18px;
  }

  section:not(.open):hover {
    background-color: rgb(235, 235, 235);
  }

  section.open {
    background-color: rgb(247, 247, 247);
  }

  header {
    padding: 15px;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
  }

  .open header {
    padding-bottom: 0;
  }

  .id {
    font-weight: bold;
    padding-left: 3px;
    padding-right: 2px;
  }

  .nvm {
    text-decoration: line-through;
  }

  .title {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.2em;
  }

  .title i,
  .title input[type="checkbox"] {
    width: 16px;
    margin-right: 0.5em;
  }

  .contents {
    padding: 0 15px;
  }

  .list {
    float: right;
    padding: 1px 10px;
    border-radius: 10px;
  }

  .list::before {
    content: "~";
  }

  .dates {
    position: absolute;
    display: flex;
    bottom: 0;
    right: 1em;
    display: flex;
    gap: 0.2em;
  }

  .due::before { content: "Due "; }
  .completed::before { content: "Completed at "; }
  .shelved::before { content: "Shelved at "; }

  .due, .completed, .shelved {
    background: light-dark(black, white);
    color: light-dark(white, black);
    text-transform: lowercase;
  }

  footer {
    display: flex;
    gap: 0.7em;
    align-items: center;
    margin-top: 20px;
    padding: 0 15px;
  }

  footer time,
  footer button {
    padding: 0.25em 0.5em;
    border-top-left-radius: 5px;
    border-top-right-radius: 5px;
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
  }

  footer button {
    background: #fdfdfd;
    margin-bottom: 0;
    border-bottom: none;
  }

  input[type="checkbox"] {
    transform: scale(1.5);
    transform-origin: center;
    margin-bottom: 0;
    aspect-ratio: 1;
  }
</style>

<section class="{ opened && "open" || "" }">
  <header class="{ note.task?.status }" {onclick}>
    <p class="title">
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
      {:else}
        <i></i>
      {/if}

      <span class="id">#{note.id}</span>

      {#if note.title}
        <span class="title">{note.title}</span>
      {:else if !opened}
        {note.headline}
      {/if}
    </p>

    {#if note.task?.list}
      <span class="list">{note.task.list}</span>
    {/if}
  </header>

  {#if opened}
    <div class="contents">
      {@html note.html}
    </div>

    <footer>
      <button onclick={() => navigate(`/${note.id}`)}>Open</button>
      <div class="dates">
        {#if note.task}
          {#if note.task.deadline}
            <time class="due">{note.task.deadline}</time>
          {/if}
          {#if note.task.shelved_at}
            <time class="shelved">{note.task.shelved_at}</time>
          {/if}
          {#if note.task.completed_at}
            <time class="completed">{note.task.completed_at}</time>
          {/if}
        {/if}
      </div>
    </footer>
  {/if}
</section>