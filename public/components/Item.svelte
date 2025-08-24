<script lang="ts">
  import { completeNote } from "../../linio/api";
  import { navigate } from "../../linio/navigation";

  let { note, opened, onclick, from, ...props } = $props();

  async function handleClick(e: MouseEvent) {
    e.stopPropagation();

    const checkbox = e.target as HTMLInputElement;
    checkbox.disabled = true;

    const updated = await completeNote(note, checkbox.checked);
    checkbox.disabled = false;
    checkbox.checked = updated?.task?.status == 'done' || updated?.wish?.status == 'bought';

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
    background: var(--color-bg-surface);
    border-radius: var(--radius);
    margin-bottom: 0.5em;
    box-shadow: var(--shadow);
  }

  section:not(.open):hover {
    background-color: var(--color-bg-surface-hover);
  }

  section:has(header:focus-visible) {
    background-color: var(--color-bg-active);
    & > header { outline: none; }
  }

  section.open:has(header:focus-visible) {
    background-color: var(--color-bg-surface-hover);
  }

  section.open {
    background-color: var(--color-white);
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
  .bought::before { content: "Bought at "; }
  .shelved::before { content: "Shelved at "; }

  .due, .completed, .bought, .shelved {
    background: var(--color-black);
    color: var(--color-white);
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
    background: var(--color-bg-surface-alt);
    margin-bottom: 0;
    border-bottom: none;
  }

  .checkbox {
    transform: scale(1.5);
    transform-origin: center;
    margin-bottom: 0;
    aspect-ratio: 1;
  }

  .list {
    text-wrap: nowrap;
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
      {#if note.task || note.wish}
        <input
          type="checkbox"
          class="checkbox"
          checked={(note.task?.status && note.task.status != 'todo') || (note.wish?.status == 'bought')}
          disabled={(note.task?.status == 'nvm') || (note.wish?.status == 'nvm')}
          onclick={(e) => handleClick(e)}
        >
      {:else if note.type == 'bookmark'}
        <img class="favicon" src={new URL("/favicon.ico", new URL(note.headline).origin).href} alt="Icon">
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
        {#if note.wish}
          {#if note.wish.shelved_at}
            <time class="shelved">{note.wish.shelved_at}</time>
          {/if}
          {#if note.wish.bought_at}
            <time class="bought">{note.wish.bought_at}</time>
          {/if}
        {/if}
      </div>
    </footer>
  {/if}
</section>