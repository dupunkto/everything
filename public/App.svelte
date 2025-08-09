<script lang="ts">
  import { Router } from "@mateothegreat/svelte5-router";

  import Home from "./routes/Home.svelte";
  import Imbox from "./routes/Imbox.svelte";
  import ToDo from "./routes/ToDo.svelte";
  import Index from "./routes/Index.svelte";
  import Note from "./routes/Note.svelte";

  import { listNotes } from "../linio/api";
  import { randomOf } from "../linio/arrays";
  import { u, navigate} from "./helpers";

  import confetti from "canvas-confetti";

  async function lucky() {
    const note = randomOf(await listNotes());
    navigate(`/${note.id}`); confetti();
  }
</script>

<style>
  footer {
    opacity: 0.7;
  }
</style>

<nav>
  <menu>
    <li><a href={u`/Imbox`}>Imbox</a></li>
    <li><a href={u`/ToDo`}>ToDo</a></li>
    <li><a href={u`/Index`}>Browse</a></li>
    <li><a href={u`/Index`} aria-label="Search">
      <i class="fa fa-search"></i>
    </a></li>
  </menu>
  <p>
    Or: <a href="javascript:void(0)" onclick={lucky}>I'm feeling lucky</a>
  </p>
</nav>

<main>
  <Router routes={[
    { component: Home },
    { component: Imbox, path: "/Imbox" },
    { component: ToDo, path: "/ToDo" },
    { component: Index, path: "/Index" },
    { component: Index, path: "/Search" },
    { component: Note, path: "(?<id>[0-9A-Z]{5})"}
  ]} />

	<footer>
		<p>
      Powered by
      <a href="//git.dupunkto.org/dupunkto/linio">Linio</a>,
      a <a href="//dupunkto.org">&lbrace;du&rbrace;punkto</a> project.
    </p>
	</footer>
</main>