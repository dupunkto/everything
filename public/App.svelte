<script lang="ts">
  import { onMount, onDestroy } from 'svelte';
  import { Router } from "@mateothegreat/svelte5-router";

  import Home from "./routes/Home.svelte";
  import Imbox from "./routes/Imbox.svelte";
  import ToDo from "./routes/ToDo.svelte";
  import Search from "./routes/Search.svelte";
  import New from "./routes/New.svelte";
  import Note from "./routes/Note.svelte";
  import Tag from "./routes/Tag.svelte";

  import { listNotes } from "../linio/api";
  import { randomOf } from "../linio/arrays";
  import { u, navigate} from "../linio/navigation";

  import confetti from "canvas-confetti";

  async function lucky() {
    const notes = (await listNotes()).filter(n => {
      return n.type != 'task' || n.task?.status == 'todo';
    });

    navigate(`/${randomOf(notes).id}`); confetti();
  }

  function handleWindowKey(e: KeyboardEvent) {
    const isEditable = (element: Element | null) =>
      (element as HTMLElement)?.isContentEditable ||
      element?.tagName == 'INPUT' || 
      element?.tagName == 'TEXTAREA';

    if(isEditable(document.activeElement)) return;
    
    const shortcuts = {
      '/': () => navigate('/Search?autofocus=1'),
      'n': () => navigate('/New?autofocus=1'),
      '1': () => navigate('/Imbox'),
      '2': () => navigate('/ToDo'),
      '3': () => navigate('/Search'),
      '4': () => navigate('/New')
    };

    if(Object.keys(shortcuts).includes(e.key)) {
      e.preventDefault();
      shortcuts[e.key]();
    }
  }

  onMount(() => window.addEventListener('keydown', handleWindowKey));
  onDestroy(() => window.removeEventListener('keydown', handleWindowKey));
</script>

<style>
  nav {
    position: absolute;
    right: min(3em, 3vw);
    top: 0;
    display: flex;
    flex-direction: column;
    gap: 0.5em;
    align-items: flex-end;
    z-index: 100;
  }

  nav p {
    margin: 0 0.2em;
  }

  nav a {
    color: currentColor !important;
  }

  nav menu {
    display: flex;
    list-style: none;
    flex-direction: row;
    gap: 0.5em;
    margin: 0;
    padding: 0;
  }

  nav menu li {
    display: flex;
    background: var(--color-black);
    color: var(--color-white);
    &:hover { background: var(--color-bg-nav-hover); }
    border-bottom-right-radius: var(--radius);
    border-bottom-left-radius: var(--radius);
    box-shadow: var(--shadow);
  }

  nav menu li a {
    color: currentColor !important;
    text-decoration: none !important;
    font-size: 1.5em;
    padding: 0.5em;
  }

  footer {
    position: absolute;
    bottom: 0;
    right: 0;
    opacity: 0.7;
    margin: 0 1em;
  }
</style>

<nav>
  <menu>
    <li><a href={u`/Imbox`}>Imbox</a></li>
    <li><a href={u`/ToDo`}>ToDo</a></li>
    <li><a href={u`/Search`} aria-label="Search">
      <i class="fa fa-search"></i>
    </a></li>
    <li><a href={u`/New`} aria-label="New">
      <i class="fa fa-plus"></i>
    </a></li>
  </menu>
  <p>
    Or: <a href="javascript:void(0)" onclick={lucky}>I'm feeling lucky</a>
  </p>
</nav>

<main>
  <svelte:boundary>
    <Router routes={[
      { component: Home },
      { component: Imbox, path: "/Imbox" },
      { component: ToDo, path: "/ToDo" },
      { component: Search, path: "/Search" },
      { component: New, path: "/New" },
      { component: Tag, path: "/Tag" },
      { component: Note, path: "(?<id>[0-9A-Z]{5})"}
    ]} />
    {#snippet pending()}
      <p>Loading...</p>
    {/snippet}
  </svelte:boundary>
</main>

<footer>
  <p>
    Powered by
    <a href="//git.dupunkto.org/dupunkto/linio">Linio</a>,
    a <a href="//dupunkto.org">&lbrace;du&rbrace;punkto</a> project.
  </p>
</footer>