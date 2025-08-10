<script lang="ts">
  import { Note } from "../../linio/types";
  import { fetchNote, updateNote, completeTask } from "../../linio/api";
  import { formatDate, normalizeDate } from "../../linio/dates";
  import { navigate } from "../../linio/navigation";

  let { route } = $props();

  let id = route.result.path.params.id;
  let mode = $derived(route.result.querystring.params.mode || "view");
  let from = $derived(route.result.querystring.params.from);

  let note: Note = $state (await fetchNote(id));

  let task = $derived(!!note.task);
  let status = $derived(note.task?.status ?? "todo");

  function date_for_status(status: string) {
    switch(status) {
      case 'done': return 'completed_at';
      case 'nvm': return 'shelved_at';
      default: return 'deadline';
    }
  }

  async function handleClick(e: MouseEvent) {
    e.stopPropagation();

    const checkbox = e.target as HTMLInputElement;
    checkbox.disabled = true;

    const updated = await completeTask(note, checkbox.checked);
    checkbox.disabled = false;
    checkbox.checked = updated?.task?.status == 'done';

    note = updated;
  }

  async function handleSubmit(e: SubmitEvent) {
    e.preventDefault();

    const data = new FormData(e.target as HTMLFormElement);

    let md = data.get("text") as string;
    if(!md) throw `no content, got: ${JSON.stringify(Object.fromEntries(data))}`;

    if(data.get('task') == 'on') {
      let modifiers = 'TODO';

      const deadline = data.get('deadline') as string;
      const list = data.get('list') as string;
      const status = data.get('status') as string;
      const completed_at = data.get('completed_at') as string;
      const shelved_at = data.get('shelved_at') as string;

      if(deadline) modifiers += ` @ ${normalizeDate(deadline)}`;
      if(list) modifiers += ` ~${list}`;

      switch (status) {
        case 'done':
          modifiers += '\nDONE';
          if(completed_at) modifiers += ` @ ${normalizeDate(completed_at)}`;
          break;

        case 'nvm':
          modifiers += `\nNVM`;
          if(shelved_at) modifiers += ` @ ${normalizeDate(shelved_at)}`;
          break;
      }

      md = `${modifiers}\n${md}`;
    }

    note = await updateNote(note, md);
    navigate(from ?? `/${note.id}?mode=view`)
  }

  function autoResize(e: Event) {
    const target = e.target as HTMLTextAreaElement;
    target.rows = 0; target.style.height = "";
    target.style.height = `${target.scrollHeight + 2}px`
  }

  function autoFillToday(e: Event) {
    const target = e.target as HTMLInputElement;
    if(!target.value) target.value = formatDate(new Date());
  }
</script>

<style>
  .checkbox {
    transform: scale(3);
    transform-origin: right top;
    float: right;
    margin-top: 15px;
    margin-bottom: 50px;
  }

  article {
    position: relative;
    background: #fefefe;
    box-shadow: var(--shadow);
    border-radius: var(--radius);
    padding: 3px 3px 0 3px;
  }

  .id {
    font-weight: normal;
    color: gray;
    font-size: 0.85em;
    margin-left: 0.2em;
  }

  .contents {
    margin: 0.85em;
  }

  .contents > :first-child { margin-top: 0; }
  .contents > :last-child { margin-bottom: 0; }

  .note {
    position: relative;
  }

  .note aside {
    text-align: right;
    position: absolute;
    width: 150px;
    left: -150px;
    padding-right: 1em;
  }

  .metadata {
    clear: both;
    display: flex;
    flex-direction: column;
    gap: 0.8em;
    margin-top: 20px;
  }

  .ref::before { content: "Ref "; }
  .list::before { content: "List "; }
  .due::before { content: "Due "; }
  .completed::before { content: "Completed at "; }
  .shelved::before { content: "Shelved at "; }

  :is(.ref, .list, .due, .completed, .shelved)::before, label {
    display: block;
    color: gray;
  }

  .ref, .list, .due, .completed, .shelved {
    text-transform: lowercase;
  }

  footer {
    display: flex;
    gap: 0.2em;
    align-items: center;
    margin-top: 1.5em;
    padding: 0 0.8em;
  }

  footer button {
    background: #fdfdfd;
    margin-bottom: 0;
    border-bottom: none;
    padding: 0.25em 0.5em;
    border-top-left-radius: calc(var(--radius) / 2);
    border-top-right-radius: calc(var(--radius) / 2);
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
  }

  form {
    padding-top: 0.3em;
    padding-right: 0.3em;
    padding-left: 0.85em;
  }

  table {
    margin-bottom: 0.6em;
  }

  table tr {
    border: none;
  }
  
  table td:not(:last-child):has(input, select) {
    padding-right: 2em;
  }

  table input, table select, textarea {
    margin-bottom: 0;
    width: 100%;
  }

  table input[type="checkbox"] {
    width: initial;
  }
</style>

{#if note}
  <div class="note">
    <aside>
      {#if note.task}
        <input
          type="checkbox"
          class="checkbox"
          checked={note.task.status != 'todo'}
          disabled={note.task.status == 'nvm' || mode == 'edit'}
          onclick={(e) => handleClick(e)}
        />
      {/if}

      <div class="metadata">
        {#if note.task}
          {#if note.task.list}
            <time class="list">{note.task.list}</time>
          {/if}
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
        {#if !note.title}
          <time class="ref">#{note.id}</time>
        {/if}
      </div>
    </aside>
    <article>
      {#if mode == 'edit'}
        <form id="edit-form" onsubmit={(e) => handleSubmit(e)}>
          <table>
            <tbody>
              <tr style="height: 36.4px">
                <td style="width: 83.4667px"><label for="task">task</label></td>
                <td>
                  <input
                    type="checkbox"
                    name="task"
                    checked={!!note.task}
                    onclick={(e) => task = (e.target as HTMLInputElement).checked}
                  />
                </td>
                {#if task}
                  <td><label for="list">list</label></td>
                  <td><input type="text" name="list" value={note.task?.list ?? "all"}></td>
                {/if}
              </tr>
              {#if task}
                {#if status && status != 'todo'}
                  <tr>
                    <td colspan="2"><label for="deadline">todo</label></td>
                    <td><label for="deadline">before</label></td>
                    <td>
                      <input
                        type="date"
                        name="deadline"
                        value={normalizeDate(note.task?.deadline)}
                      />
                    </td>
                  </tr>
                {/if}
                <tr>
                  <td><label for="status">status</label></td>
                  <td>
                    <select name="status" onchange={(e) => status = (e.target as HTMLInputElement).value}>
                      <option>todo</option>
                      <option selected={note.task?.status == 'done'}>done</option>
                      <option selected={note.task?.status == 'nvm'}>nvm</option>
                    </select>
                  </td>
                  <td><label for={date_for_status(status)}>{status == 'todo' ? 'before' : 'at'}</label></td>
                  <td>
                    <input
                      type="date"
                      name={date_for_status(status)}
                      value={normalizeDate(note.task?.[date_for_status(status)]) || formatDate(new Date())}
                    />
                  </td>
                </tr>
              {/if}
            </tbody>
          </table>
          <textarea
            name="text"
            rows={note.text.split("\n").length}
            oninput={(e) => autoResize(e)}
          >{note.text}</textarea>
        </form>
      {:else}
        <div class="contents">
          {#if note.title}
            <h1>{note.title} <span class="id">#{note.id}</span></h1>
          {/if}

          {@html note.html}
        </div>
      {/if}

      <footer>
        {#if mode == 'edit'}
          <button onclick={() => navigate(from ?? `/${note.id}?mode=view`)}>Cancel</button>
          <button type="submit" form="edit-form">Save</button>
        {:else}
          <button onclick={() => navigate(`/${note.id}?mode=edit`)}>Edit</button>
        {/if}
      </footer>
    </article>
  </div>
{/if}