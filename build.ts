import { SveltePlugin } from "bun-plugin-svelte";

await Bun.build({
  entrypoints: ["public/index.html"],
  outdir: "dist",
  plugins: [SveltePlugin()],
});
