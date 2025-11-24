<script lang="ts">
  import { onMount } from 'svelte';
  import { fetchScratchpad, updateScratchpad } from "../../linio/api";

  let { visible = $bindable() }: { visible: boolean } = $props();

  let content = $state('');
  let loaded = $state(false);
  let timeout: Timer | null = null;

  function toggle() {
    visible = !visible;
  }

  function handleInput(e: Event) {
    const target = e.target as HTMLTextAreaElement;
    content = target.value;
    updateHeight(target);

    if (timeout) clearTimeout(timeout);
    timeout = setTimeout(() => updateScratchpad(content), 500);
  }

  function updateHeight(target: HTMLTextAreaElement) {
    target.style.height = 'auto';
    const maxHeight = window.innerHeight * 0.65;
    const newHeight = Math.min(target.scrollHeight, maxHeight);
    target.style.height = `${Math.max(200, newHeight)}px`;
  }

  onMount(async () => {
    content = await fetchScratchpad();
    loaded = true;
  });

  let textarea: HTMLTextAreaElement;
  let previous: Element | null = null;

  $effect(() => {
    if (!visible || !textarea) return;

    previous = document.activeElement;
    textarea.focus();
    updateHeight(textarea);

    return () => {
      if (previous instanceof HTMLElement) previous.focus();
    };
  });
</script>

<style>
  .overlay {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: var(--color-bg-surface);
    border-top: 2px solid var(--color-border);
    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
    z-index: 1000;
    display: flex;
    flex-direction: column;
  }

  header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.2em 0.75em;
    border-bottom: 1px solid var(--color-border);
    background: var(--color-bg);
  }

  header h3 {
    margin: 0;
    font-size: 0.9em;
    font-weight: 600;
    opacity: 0.7;
  }

  header button {
    background: none;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 5px;
    overflow: visible;
    opacity: 0.5;
    margin-bottom: 0;
  }

  header button svg {
    width: 1em;
    height: 1em;
  }

  header button:hover {
    opacity: 1;
  }

  textarea {
    width: 100%;
    border: none;
    padding: 1em;
    font-family: monospace;
    resize: none;
    background: transparent;
    overflow-y: auto;
  }

  textarea:focus {
    outline: none;
  }
</style>

{#if visible}
  <div class="overlay">
    <header>
      <h3>Scratchpad</h3>
      <button onclick={toggle} aria-label="Close">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
        </svg>
      </button>
    </header>
    <textarea
      bind:this={textarea}
      placeholder="Your scratchpad..."
      value={content}
      oninput={(e) => handleInput(e)}></textarea>
  </div>
{/if}
