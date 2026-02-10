<script lang="ts">
  import { onDestroy, onMount } from 'svelte';

  import { getConfig, newNote, listNotes } from "../../linio/api";
  import { navigate } from "../../linio/navigation";
  import { normalizeDate  } from "../../linio/dates";

  let { route } = $props();

  let config = $state(await getConfig());
  let autofocus = $derived(route.result.querystring.params.autofocus);
  let textarea: HTMLTextAreaElement;
  let cursorPosition = $state(0);
  let tags: string[] = $state([]);

  listNotes().then(notes => {
    const allTags = notes.flatMap(n => n.tags);
    tags = Array.from(new Set(allTags)).sort();
    console.log('Loaded tags:', tags);
  });

  async function handleSubmit(e: SubmitEvent) {
    e.preventDefault();

    const data = new FormData(e.target as HTMLFormElement);

    let today = new Date().toISOString();
    let md = data.get("text") as string;

    if(config.format != 'modifiers') {
      const headers: string[] = [];

      headers.push(`Created: ${normalizeDate(today)}`);
      headers.push(`Modified: ${normalizeDate(today)}`);

      md = headers.join('\n') + '\n\n' + md;
    }

    const note = await newNote(md);

    navigate(`/${note.id}`);
  }

  function handleWindowKey(e: KeyboardEvent) {
    const isEditable = (element: Element | null) =>
      (element as HTMLElement)?.isContentEditable ||
      element?.tagName == 'INPUT' || 
      element?.tagName == 'TEXTAREA';

    if(isEditable(document.activeElement)) return;
    if(e.metaKey || e.ctrlKey) return;

    if(e.key == 'e' || e.key == 'n') {
      e.preventDefault();
      document.querySelector("textarea")?.focus();
    }
  }

  function handleKey(e: KeyboardEvent) {
    if ((e.metaKey || e.ctrlKey) && e.key == 'Enter') {
      e.preventDefault();
      (e.target as HTMLTextAreaElement).form?.requestSubmit();
    }
    
    if (e.key == 'Tab') {
      e.preventDefault();
      insertEnter(e);
      autoResize(e);
    }
  }

  function insertEnter(e: Event) {
    const target = e.target as HTMLTextAreaElement;
    const start = target.selectionStart;
    const end = target.selectionEnd;
    target.value = target.value.substring(0, start) + '\n' + target.value.substring(end);
    target.selectionStart = target.selectionEnd = start + 1;
  }

  function autoResize(e: Event) {
    const target = e.target as HTMLTextAreaElement;
    target.rows = 0; target.style.height = "";
    target.style.height = `${target.scrollHeight + 2}px`
  }

  function updateCursor(e: Event) {
    const target = e.target as HTMLTextAreaElement;
    cursorPosition = target.selectionStart;
  }

  function insertTag(tag: string) {
    if (!textarea) return;

    const start = cursorPosition;
    const end = cursorPosition;
    const tagText = `[[${tag}]]`;

    textarea.value = textarea.value.substring(0, start) + tagText + textarea.value.substring(end);
    textarea.selectionStart = textarea.selectionEnd = start + tagText.length;
    cursorPosition = textarea.selectionStart;

    textarea.focus();
    autoResize({ target: textarea } as Event);
  }

  onMount(() => window.addEventListener('keydown', handleWindowKey));
  onDestroy(() => window.removeEventListener('keydown', handleWindowKey))

  $effect(() => {
    if(autofocus) document.querySelector("textarea")?.focus();
  });
</script>

<style>
  textarea {
    background: var(--color-bg-surface);
    min-height: 150px;
  }

  form, textarea {
    width: 100%;
  }

  button {
    float: right;
  }

  button i {
    margin-inline-start: 0.2em;
  }

  .tags {
    margin-top: 1em;
    display: flex;
    flex-wrap: wrap;
    gap: 0.5em;
  }

  .tags a {
    text-decoration: none;
    color: var(--color-text);
    padding: 0.2em 0.5em;
    background: var(--color-bg-surface);
    border-radius: var(--radius);
    transition: background 0.2s;
  }

  .tags a:hover {
    background: var(--color-bg-surface-hover);
  }
</style>

<form onsubmit={(e) => handleSubmit(e)}>
  <textarea
    bind:this={textarea}
    name="text"
    rows="2"
    placeholder="What's on your mind?"
    oninput={(e) => autoResize(e)}
    onkeydown={(e) => handleKey(e)}
    onselect={(e) => updateCursor(e)}
    onclick={(e) => updateCursor(e)}
    required></textarea>
    {#if tags.length > 0}
      <p class="tags">
        {#each tags as tag}
          <a href="#" onclick={(e) => { e.preventDefault(); insertTag(tag); }}>[[{tag}]]</a>
        {/each}
      </p>
    {/if}
  <button type="submit">Save <i class="fa fa-arrow-right"></i></button>
</form>