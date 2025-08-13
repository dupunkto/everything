<script lang="ts">
  import { completeTask } from "../../linio/api";
  import { navigate } from "../../linio/navigation";

  let { note, opened, onclick, from, ...props } = $props();

  async function handleClick(e: MouseEvent) {
    e.stopPropagation();

    const checkbox = e.target as HTMLInputElement;
    checkbox.disabled = true;

    const updated = await completeTask(note, checkbox.checked);
    checkbox.disabled = false;
    checkbox.checked = updated?.task?.status == 'done';

    note = updated;
  }

  async function handleKey(e: KeyboardEvent) {
    const focused = document.activeElement == e.target
      && document.activeElement!.tagName == "HEADER";
    
    if(e.key == 'o' && (opened || focused)) {
      e.preventDefault();
      navigate(`/${note.id}?mode=view`);
    }

    if(e.key == 'e' && (opened || focused)) {
      e.preventDefault();
      navigate(`/${note.id}?mode=edit&from=${from}&autofocus=1`);
    }

    if (['Enter', ' '].includes(e.key) && focused) {
      e.preventDefault();
      onclick(e);
    }
  }
</script>

<style>
  section {
    position: relative;
    font-size: 1em;
    background: light-dark(#fefefe, #1a1e23);
    border-radius: var(--radius);
    margin-bottom: 0.5em;
    box-shadow: var(--shadow);
  }

  section:not(.open):hover {
    background-color: light-dark(#f6f6f6, #21242c);
  }

  section:has(header:focus-visible) {
    background-color: light-dark(#ddd, #3a3f47);
    & > header { outline: none; }
  }

  section.open:has(header:focus-visible) {
    background-color: light-dark(#f6f6f6, #21242c);
  }

  section.open {
    background-color: light-dark(white, #161a1f);
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
  
  .headline {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 1;
    overflow: hidden;
    flex: 1;
    min-width: 0;
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
    background: light-dark(#fdfdfd, #2f3136);
    color: #333;
    margin-bottom: 0;
    border-bottom: none;
  }

  @media (prefers-color-scheme: dark) {
    footer button:focus {
      border-color: #666;
    }
    footer button:active {
      background: #ddd;
    }
  }

  .checkbox {
    transform: scale(1.5);
    transform-origin: center;
    margin-bottom: 0;
    aspect-ratio: 1;
  }
</style>

<section role="group" class="{ opened && "open" || "" }">
  <header
    role="button"
    tabindex="0"
    class="item { note.task?.status }"
    {onclick}
    onkeydown={(e) => handleKey(e)}
    style={props.style}
  >
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
        <span class="headline" title={note.title.replace(/<[^>]*>/g, '')}>
          {@html note.title}
        </span>
      {:else if !opened}
        <span class="headline">{note.headline}</span>
      {/if}
    </p>

    {#if note.task?.list && !props.hide_list}
      <span class="list">~{note.task.list}</span>
    {/if}

    {#if note.type != 'note' && note.type != 'task'}
      <code class="type">{note.type}</code>
    {/if}
  </header>

  {#if opened}
    <div class="contents">
      {@html note.html}
    </div>

    <footer>
      <button onclick={() => navigate(`/${note.id}`)}>Open</button>
      <button onclick={() => navigate(`/${note.id}?mode=edit&from=${from}`)}>Edit</button>

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