<script lang="ts">
  import { newNote } from "../../linio/api";
  import { navigate } from "../../linio/navigation";

  async function handleSubmit(e: SubmitEvent) {
    e.preventDefault();

    const data = new FormData(e.target as HTMLFormElement);
    const note = await newNote(data.get("text") as string);

    navigate(`/${note.id}`);
  }

  function handleKey(e: KeyboardEvent) {
    if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
      e.preventDefault();
      (e.target as HTMLTextAreaElement).form?.requestSubmit();
    }
  }

  function autoResize(e: Event) {
    const target = e.target as HTMLTextAreaElement;
    target.rows = 0; target.style.height = "";
    target.style.height = `${target.scrollHeight + 2}px`
  }
</script>

<style>
  textarea {
    background: #fefefe;
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
</style>

<form onsubmit={(e) => handleSubmit(e)}>
  <textarea
    name="text"
    rows="2"
    autofocus
    placeholder="What's on your mind?"
    oninput={(e) => autoResize(e)}
    onkeydown={(e) => handleKey(e)}
    required></textarea>
  <button type="submit">Save <i class="fa fa-arrow-right"></i></button>
</form>