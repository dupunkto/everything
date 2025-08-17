import { SveltePlugin } from "bun-plugin-svelte";

await Bun.build({
  entrypoints: [`${process.env.PUBLIC}/index.html`],
  outdir: process.env.DIST,
  plugins: [SveltePlugin()],
});
