<script lang="ts">
  import { fetchScratchpad, updateScratchpad } from "../../linio/api";

  let content = $state(await fetchScratchpad());
  let timeout: Timer | null = null;

  function handleInput(e: Event) {
    const target = e.target as HTMLTextAreaElement;
    content = target.value;
    autoResize(e);

    if(timeout) clearTimeout(timeout);
    timeout = setTimeout(() => updateScratchpad(content), 500);
  }

  function autoResize(e: Event) {
    const target = e.target as HTMLTextAreaElement;
    target.rows = 0; target.style.height = "";
    target.style.height = `${target.scrollHeight + 2}px`
  }
</script>

<style>
  textarea {
    background: var(--color-bg-surface);
    min-height: 150px;
    width: 100%;
  }
</style>

<textarea
  rows="2"
  placeholder="Your scratchpad..."
  value={content}
  oninput={(e) => handleInput(e)}></textarea>
