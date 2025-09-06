<script lang="ts">
  import { onMount, onDestroy } from 'svelte';
  import EasyMDE from "easymde";
  import "easymde/dist/easymde.min.css";

  import { Note, Config } from "../../linio/types";
  import { getConfig, fetchNote, updateNote, completeNote, deleteNote } from "../../linio/api";
  import { formatDate, normalizeDate } from "../../linio/dates";
  import { navigate } from "../../linio/navigation";

  let { route } = $props();

  let id = route.result.path.params.id;
  let mode = $derived(route.result.querystring.params.mode || "view");
  let autofocus = $derived(route.result.querystring.params.autofocus);
  let from = $derived(route.result.querystring.params.from);

  let note: Note = $state (await fetchNote(id));
  let config: Config = $state (await getConfig());

  let task = $derived(!!note.task);

  let taskStatus = $derived(note.task?.status ?? "todo");
  let wishStatus = $derived(note.wish?.status ?? "dream");

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

    const updated = await completeNote(note, checkbox.checked);
    checkbox.disabled = false;
    checkbox.checked = updated?.task?.status == 'done' || updated?.wish?.status == 'bought';

    note = updated;
  }

  function handleWindowKey(e: KeyboardEvent) {  
    const isEditable = (element: Element | null) =>
      (element as HTMLElement)?.isContentEditable ||
        element?.tagName === 'INPUT' || 
        element?.tagName === 'TEXTAREA';

    if(e.key == 'Escape' && mode == 'edit') {
      e.preventDefault();
      navigate(from ?? `/${note.id}?mode=view`);
      return;
    }

    if(isEditable(document.activeElement)) return;
    if(e.metaKey || e.ctrlKey) return;

    if(e.key == 'c') {
      e.preventDefault();
      (document.querySelector(".checkbox") as HTMLInputElement)?.click();
      return;
    }

    if(e.key == 'd') {
      e.preventDefault();
      handleDelete();
      return;
    }

    if(e.key == 'e') {
      e.preventDefault();
      
      switch(mode) {
        case 'view': return navigate(`/${note.id}?mode=edit&autofocus=1`);
        case 'edit': return document.querySelector("textarea")?.focus();
      }
    }
  }

  function handleKey(e: KeyboardEvent) {
    if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
      e.preventDefault();
      (e.target as HTMLTextAreaElement).form?.requestSubmit();
    }
  }

  async function handleSubmit(e: SubmitEvent) {
    e.preventDefault();

    const data = new FormData(e.target as HTMLFormElement);

    let md = data.get("text") as string;
    if(!md) throw `no content, got: ${JSON.stringify(Object.fromEntries(data))}`;

    const includesHeaders = note.raw.includes("Created: ") || note.raw.includes("Modified: ");

    const isTask = data.get('task') == 'on';
    const isWish = data.get('wish') == 'on';
    
    if(config.format != 'headers') {
      if(isTask) {
        let modifiers = 'TODO';

        const deadline = data.get('deadline') as string;
        const list = data.get('list') as string;
        const status = data.get('status') as string;
        const completed_at = data.get('completed_at') as string;
        const shelved_at = data.get('shelved_at') as string;

        if(deadline) modifiers += ` @ ${normalizeDate(deadline)}`;
        if(list && list != 'all') modifiers += ` ~${list}`;

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

      if(isWish) {
        let modifiers = 'WISH';
        
        const status = data.get('status') as string;
        const bought_at = data.get('bought_at') as string;
        const shelved_at = data.get('shelved_at') as string;

        switch (status) {
          case 'bought':
            modifiers += '\nBOUGHT';
            if(bought_at) modifiers += ` @ ${normalizeDate(bought_at)}`;
            break;

          case 'nvm':
            modifiers += '\nNVM';
            if(shelved_at) modifiers += ` @ ${normalizeDate(shelved_at)}`;
            break;
        }

        md = `${modifiers}\n${md}`;
      }
    }

    if(config.format != 'modifiers' || includesHeaders) {
      const headers: string[] = [];

      headers.push(`Created: ${normalizeDate(note.created_at)}`);
      headers.push(`Modified: ${normalizeDate(new Date().toISOString())}`);
      
      if(isTask && config.format == 'headers') {
        headers.push('Type: task');
        
        const deadline = data.get('deadline') as string;
        const list = data.get('list') as string;
        const status = data.get('status') as string;
        const completed_at = data.get('completed_at') as string;
        const shelved_at = data.get('shelved_at') as string;

        headers.push(`Task-Status: ${status}`);
        if(deadline) headers.push(`Task-Deadline: ${normalizeDate(deadline)}`);
        if(list && list != 'all') headers.push(`Task-List: ${list}`);
        if(completed_at) headers.push(`Task-Completed: ${normalizeDate(completed_at)}`);
        if(shelved_at) headers.push(`Task-Shelved: ${normalizeDate(shelved_at)}`);
      }

      if(isWish && config.format == 'headers') {
        headers.push('Type: wish');
        
        const status = data.get('status') as string;
        const bought_at = data.get('bought_at') as string;
        const shelved_at = data.get('shelved_at') as string;

        headers.push(`Wish-Status: ${status}`);
        if(bought_at) headers.push(`Wish-Bought: ${normalizeDate(bought_at)}`);
        if(shelved_at) headers.push(`Wish-Shelved: ${normalizeDate(shelved_at)}`);
      }

      if(headers.length > 0) {
        md = headers.join('\n') + '\n\n' + md;
      }
    }

    note = await updateNote(note, md);
    navigate(from ?? `/${note.id}?mode=view`)
  }

  async function handleDelete() {
    if (!confirm('Are you sure?')) return;
    
    await deleteNote(note);
    navigate(from || (note.task ? '/ToDo' : '/'));
  }

  function autoResize(e: Event) {
    resizeToFit(e.target as HTMLTextAreaElement);
  }

  function resizeToFit(target: HTMLTextAreaElement | null) {
    if(!target) return;
    target.rows = 0; target.style.height = "";
    target.style.height = `${target.scrollHeight + 2}px`;
  }

  onMount(() => window.addEventListener('keydown', handleWindowKey));
  onDestroy(() => window.removeEventListener('keydown', handleWindowKey));

  $effect(() => {
    if(mode == 'edit') {
      const textarea = document.querySelector("textarea");

      if(config.features == 'basic') {
        if(autofocus) textarea?.focus();
        resizeToFit(textarea);
      }

      if(config.features == 'fancy') {
        const { codemirror } = new EasyMDE({
          element: textarea!,
          spellChecker: false,
          forceSync: true, // Syncs edits back to textarea
        });

        if(autofocus) {
          const line = codemirror.lineCount() - 1;
          const ch = codemirror.getLine(line).length;

          codemirror.focus();
          codemirror.setCursor({ line, ch });
        }
      }
    }
  });
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
    background: var(--color-bg-surface);
    box-shadow: var(--shadow);
    border-radius: var(--radius);
    padding: 3px 3px 0 3px;
  }

  .id {
    font-weight: normal;
    color: var(--color-gray);
    font-size: 0.85em;
    margin-left: 0.2em;
  }

  .contents {
    margin: 0.85em;
  }

  .contents > :first-child { margin-top: 0; }
  .contents > :last-child { margin-bottom: 0; }

  .list, .type {
    text-wrap: nowrap;
    position: absolute;
    top: 0.8em;
    right: 1em;
  }

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

  .type::before { content: "Type "; }
  .ref::before { content: "Ref "; }
  .due::before { content: "Due "; }
  .completed::before { content: "Completed "; }
  .bought::before { content: "Bought "; }
  .shelved::before { content: "Shelved "; }
  .created::before { content: "Created "; }
  .modified::before { content: "Last modified "; }

  :is(.ref, .due, .completed, .bought, .shelved, .created, .modified)::before, label {
    display: block;
    color: var(--color-gray);
  }

  .type, .ref, .due, .completed, .bought, .shelved, .created, .modified {
    text-transform: lowercase;
  }

  .type::before {
    display: inline;
    margin-inline-end: 0.8em;
  }

  footer {
    display: flex;
    gap: 0.2em;
    align-items: center;
    margin-top: 1.5em;
    padding: 0 0.8em;
    position: relative;
  }

  footer button {
    background: var(--color-bg-surface-alt);
    margin-bottom: 0;
    border-bottom: none;
    padding: 0.25em 0.5em;
    border-top-left-radius: calc(var(--radius) / 2);
    border-top-right-radius: calc(var(--radius) / 2);
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
  }

  .delete {
    position: absolute;
    right: 1em;
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

  textarea {
    border: none;
  }
</style>

{#if note}
  <div class="note">
    <aside>
      {#if note.task || note.wish}
        <input
          type="checkbox"
          class="checkbox"
          checked={(note.task?.status && note.task.status != 'todo') || (note.wish?.status == 'bought')}
          disabled={(note.task?.status == 'nvm') || (note.wish?.status == 'nvm') || mode == 'edit'}
          onclick={(e) => handleClick(e)}
        />
      {/if}

      <div class="metadata">
        {#if !note.title}
          <time class="ref">#{note.id}</time>
        {/if}
          
        {#if note.task?.deadline}
          <time class="due">{note.task.deadline}</time>
        {/if}
        {#if note.task?.shelved_at}
          <time class="shelved">{note.task.shelved_at}</time>
        {/if}
        {#if note.task?.completed_at}
          <time class="completed">{note.task.completed_at}</time>
        {/if}
        {#if note.wish?.bought_at}
          <time class="bought">{note.wish.bought_at}</time>
        {/if}
        {#if note.wish?.shelved_at}
          <time class="shelved">{note.wish.shelved_at}</time>
        {/if}
        <time class="created">{note.created_at}</time>
        {#if note.modified_at != note.created_at}
          <time class="modified">{note.modified_at}</time>
        {/if}
      </div>
    </aside>
    <article>
      {#if mode == 'edit'}
        <form id="edit-form" onsubmit={(e) => handleSubmit(e)}>
          {#if note.type == 'note' || note.type == 'task'}
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
                    <td><input type="text" name="list" value={note.task?.list} placeholder="all"></td>
                  {/if}
                </tr>
                {#if task}
                  {#if taskStatus && taskStatus != 'todo'}
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
                      <select name="status" onchange={(e) => taskStatus = (e.target as HTMLInputElement).value}>
                        <option>todo</option>
                        <option selected={note.task?.status == 'done'}>done</option>
                        <option selected={note.task?.status == 'nvm'}>nvm</option>
                      </select>
                    </td>
                    <td><label for={date_for_status(taskStatus)}>{taskStatus == 'todo' ? 'before' : 'at'}</label></td>
                    <td>
                      <input
                        type="date"
                        name={date_for_status(taskStatus)}
                        value={normalizeDate(note.task?.[date_for_status(taskStatus)]) || (taskStatus != 'todo' && formatDate(new Date()))}
                      />
                    </td>
                  </tr>
                {/if}
              </tbody>
            </table>
          {:else if note.type == 'wish'}
            <input type="hidden" name="wish" value="on" />
            <table>
              <tbody>
                <tr>
                  <td><label for="status">status</label></td>
                  <td>
                    <select name="status" onchange={(e) => wishStatus = (e.target as HTMLInputElement).value}>
                      <option selected={note.wish?.status == 'dream'}>dream</option>
                      <option selected={note.wish?.status == 'bought'}>bought</option>
                      <option selected={note.wish?.status == 'nvm'}>nvm</option>
                    </select>
                  </td>
                  <td><label for={wishStatus == 'bought' ? 'bought_at' : 'shelved_at'}>{wishStatus == 'dream' ? '' : 'at'}</label></td>
                  <td>
                    {#if wishStatus != 'dream'}
                      <input
                        type="date"
                        name={wishStatus == 'bought' ? 'bought_at' : 'shelved_at'}
                        value={normalizeDate(wishStatus == 'bought' ? note.wish?.bought_at : note.wish?.shelved_at) || formatDate(new Date())}
                      />
                    {/if}
                  </td>
                </tr>
              </tbody>
            </table>
          {/if}
          <textarea
            name="text"
            oninput={(e) => autoResize(e)}
            onkeydown={(e) => handleKey(e)}
          >{note.text}</textarea>
        </form>
      {:else}
        {#if note.type != 'note' && note.type != 'task'}
          <span class="type"><code>{note.type}</code></span>
        {/if}
        {#if note.task?.list}
          <span class="list">~{note.task.list}</span>
        {/if}
        <div class="contents">
          {#if note.title}
            <h1>{@html note.title} <span class="id">#{note.id}</span></h1>
          {/if}

          {@html note.html}
        </div>
      {/if}

      <footer>
        {#if mode == 'edit'}
          <button onclick={() => navigate(from ?? `/${note.id}?mode=view`)}>Cancel</button>
          <button type="submit" form="edit-form">Save</button>
          <button class="delete" onclick={handleDelete}>Delete</button>
        {:else}
          <button onclick={() => navigate(`/${note.id}?mode=edit`)}>Edit</button>
        {/if}
      </footer>
    </article>
  </div>
{/if}