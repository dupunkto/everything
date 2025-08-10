<script lang="ts">
  import { completeTask } from "../../linio/api";
  import { truncate } from "../../linio/strings";
  import { navigate } from "../../linio/navigation";

  let { note, opened, onclick } = $props();

  async function handleClick(e: PointerEvent) {
    e.stopPropagation();

    const checkbox = e.target as HTMLInputElement;
    checkbox.disabled = true;

    const updated = await completeTask(note, checkbox.checked);
    checkbox.disabled = false;
    checkbox.checked = updated?.task?.status == 'done';

    note = updated;
  }

  async function handleKey(e: KeyboardEvent) {
    if (!['Enter', ' '].includes(e.key) 
      || document.activeElement != e.target) return;

    e.preventDefault();
    onclick(e);
  }
</script>

<style>
  section {
    position: relative;
    font-size: 1em;
    background: #fefefe;
    border-radius: var(--radius);
    margin-bottom: 0.5em;
    box-shadow: var(--shadow);
  }

  section:not(.open):hover {
    background-color: #f6f6f6;
  }

  section.open {
    background-color: white;
  }

  header {
    padding: 0.8em;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
  }

  .open header {
    padding-bottom: 0;
  }

  .id {
    font-weight: bold;
    padding: 0 0.3em;
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
  .title .checkbox {
    width: 1em;
    margin-right: 0.5em;
  }

  .contents {
    padding: 0 0.8em;
  }

  .contents :not(pre) {
    max-width: 42ch;
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
    gap: 0.2em;
    align-items: center;
    margin-top: 1.5em;
    padding: 0 0.8em;
  }

  footer time,
  footer button {
    padding: 0.25em 0.5em;
    border-top-left-radius: calc(var(--radius) / 2);
    border-top-right-radius: calc(var(--radius) / 2);
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
  }

  footer button {
    background: #fdfdfd;
    margin-bottom: 0;
    border-bottom: none;
  }

  .checkbox {
    transform: scale(1.5);
    transform-origin: center;
    margin-bottom: 0;
    aspect-ratio: 1;
  }
</style>

<section role="group" class="{ opened && "open" || "" }">
  <header role="button" tabindex="0" class="{ note.task?.status }" {onclick} onkeydown={(e) => handleKey(e)}>
    <p class="title">
      {#if note.task}
        <input
          type="checkbox"
          class="checkbox"
          checked={note.task.status != 'todo'}
          disabled={note.task.status == 'nvm'}
          onclick={(e) => handleClick(e)}
        >
      {:else}
        <i></i>
      {/if}

      <span class="id">#{note.id}</span>

      {#if note.title}
        <span class="title" title={note.title}>{truncate(note.title, 25)}</span>
      {:else if !opened}
        {truncate(note.headline, 25)}
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
      <button onclick={() => navigate(`/${note.id}?mode=edit`)}>Edit</button>

      <div class="dates">
        {#if note.task}
          {#if note.task.deadline && !(note.task.completed_at || note.task.shelved_at)}
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