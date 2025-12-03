<script lang="ts">
  import { Note } from "../../linio/types";
  import { listNotes } from "../../linio/api";
  import { keyboardNavigation } from "../../linio/keyboard";
  
  import Item from "../components/Item.svelte";

  let notes: Note[] = $state(await listNotes());
  let selected: string | null = $state(null);
  let selectNote = (n: Note) => selected = selected == n.id ? null : n.id;

  const now = new Date();
  const oneWeekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
  const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const endOfTomorrow = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 2);
  const oneWeekFromNow = new Date(now.getTime() + 7 * 24 * 60 * 60 * 1000);
  const oneMonthFromNow = new Date(now.getTime() + 31 * 24 * 60 * 60 * 1000);

  const recents = $derived.by(() => {
    const otherSectionIds = new Set([
      ...overdue.map(n => n.id),
      ...thisWeek.map(n => n.id),
      ...thisMonth.items.map(n => n.id)
    ]);
    
    return notes
      .filter(note => {
        const createdAt = new Date(note.created_at);
        return createdAt >= oneWeekAgo && !otherSectionIds.has(note.id);
      })
      .sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())
      .slice(0, 7);
  });

  const today = $derived.by(() => {
    return notes
      .filter(note =>
        note.task?.status === 'todo' &&
        note.task.deadline &&
        new Date(note.task.deadline) >= startOfToday &&
        new Date(note.task.deadline) < endOfTomorrow
      )
      .sort((a, b) => {
        const aDate = new Date(a.task!.deadline!).getTime();
        const bDate = new Date(b.task!.deadline!).getTime();
        return aDate - bDate;
      });
  });

  const thisWeek = $derived.by(() => {
    return notes
      .filter(note =>
        note.task?.status === 'todo' &&
        note.task.deadline &&
        new Date(note.task.deadline) >= endOfTomorrow &&
        new Date(note.task.deadline) <= oneWeekFromNow
      )
      .sort((a, b) => {
        const aDate = new Date(a.task!.deadline!).getTime();
        const bDate = new Date(b.task!.deadline!).getTime();
        return aDate - bDate;
      });
  });

  const thisMonth = $derived.by(() => {
    const monthTodos = notes
      .filter(note => 
        note.task?.status === 'todo' && 
        note.task.deadline &&
        new Date(note.task.deadline) > oneWeekFromNow && 
        new Date(note.task.deadline) <= oneMonthFromNow
      )
      .sort((a, b) => {
        const aDate = new Date(a.task!.deadline!).getTime();
        const bDate = new Date(b.task!.deadline!).getTime();
        return aDate - bDate;
      });
    
    return {
      items: monthTodos.slice(0, 15),
      total: monthTodos.length
    };
  });

  const overdue = $derived.by(() => {
    return notes
      .filter(note =>
        note.task?.status === 'todo' &&
        note.task.deadline &&
        new Date(note.task.deadline) < startOfToday
      )
      .sort((a, b) => {
        const aDate = new Date(a.task!.deadline!).getTime();
        const bDate = new Date(b.task!.deadline!).getTime();
        return aDate - bDate;
      });
  });

  const listClasses = ['today', 'this-week', 'this-month'];
</script>

<style>
  @media (min-width: 815px) {
    :global(main:has(.imbox)) {
      max-width: calc(100% - var(--sidebar-width));
    }
  }

  :global(main:has(.imbox)) {
    margin: 0;
    padding: 0;
  }

  header, .imbox {
    padding: 0 min(3em, 3vw);
  }

  header {
    margin-top: 1.5em;
    position: relative;
    z-index: 1;
    display: flex;
    gap: 0.5em;
    align-items: center;
    justify-content: space-between;
    margin-bottom: -3em;
  }

  header h1 {
    margin: 0;
    transform: scale(2);
    transform-origin: left top;
  }

  .imbox {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    grid-template-rows: masonry;
    column-gap: 1em;
    overflow-y: auto;
    padding-bottom: 4em;
  }

  section:first-of-type {
    margin-top: 6.8em;
  }

  section h2 {
    padding: 0;
    border: none;
    font-size: 1.2em;
  }

  .count {
    color: var(--color-text-muted);
    font-size: 0.9em;
    margin-top: 0.5em;
  }
</style>

<header>
  <h1>Imbox</h1>
</header>

<div class="imbox" use:keyboardNavigation={{ listClasses }}>
  <section class="today">
    <h2>
      Today
      {#if overdue.length > 0}
        <small>({overdue.length} overdue)</small>
      {/if}
    </h2>
    {#each overdue as note}
      <Item {note}
        from="/Imbox"
        opened={note.id == selected}
        onclick={() => selectNote(note)}
        style="color: var(--color-text-error)"
      />
    {/each}
    {#each today as note}
      <Item {note}
        from="/Imbox"
        opened={note.id == selected}
        onclick={() => selectNote(note)}
      />
    {/each}
  </section>
  
  <section class="this-week">
    <h2>This week</h2>
    {#each thisWeek as note}
      <Item {note}
        from="/Imbox"
        opened={note.id == selected}
        onclick={() => selectNote(note)}
      />
    {/each}
  </section>

  <section class="this-month">
    <h2>This month</h2>
    {#each thisMonth.items as note}
      <Item {note}
        from="/Imbox"
        opened={note.id == selected}
        onclick={() => selectNote(note)}
      />
    {/each}
    {#if thisMonth.total > 15}
      <div class="count">+ {thisMonth.total - 15} more</div>
    {/if}
  </section>
</div>