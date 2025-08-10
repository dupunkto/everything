<script lang="ts">
  import { newNote } from "../../linio/api";
  import { navigate } from "../../linio/navigation";

  async function handleSubmit(e: SubmitEvent) {
    e.preventDefault();

    const data = new FormData(e.target as HTMLFormElement);
    const note = await newNote(data.get("text") as string);

    navigate(`/${note.id}`);
  }

  document.querySelector("textarea")?.focus();

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

<form onsubmit={(e) => handleSubmit(e)} oninput={(e) => autoResize(e)}>
  <textarea name="text" autofocus placeholder="What's on your mind?"></textarea>
  <button type="submit">Save <i class="fa fa-arrow-right"></i></button>
</form>