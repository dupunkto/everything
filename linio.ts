import { $, argv, serve } from "bun";
import { join } from "node:path";
import { marked } from "marked";

const ROOT = argv[2];
const TODOS = ['TODO', 'DONE', 'NVM'];

import { generateHumID } from "./linio/humid";
import { Note, Task } from "./linio/types";

import app from "./public/index.html";

const server = serve({
  port: 4000,
  development: true,
  routes: {
    "/": app,
    "/:page": app,
    
    // API endpoints
    "/api/notes": {
      GET: getNotes,
      POST: newNote,
    },

    "/api/note/:humid": {
      GET: getNote,
      PUT: updateNote,
      DELETE: deleteNote,
    }
  }
});

console.log(`Serving '${ROOT}' on ${server.url}`);

// HTTP handlers

async function getNotes(req: Request) {
  return Response.json(await listNotes());
}

async function getNote(req: Request) {
  // @ts-ignore
  const { humid } = req.params;
  return Response.json(await fetchNote(humid));
}

async function updateNote(req: Request) {
  // @ts-ignore
  const { humid } = req.params;
  const { md } = await req.json();

  return Response.json(await putNote(humid, md));
}

async function newNote(req: Request) {
  const { md } = await req.json();
  return Response.json(await putNote(generateHumID(), md));
}

async function deleteNote(req: Request) {
  // @ts-ignore
  const { humid } = req.params;
  return Response.json(await removeNote(humid));
}

// Programmatic API

async function listNotes(): Promise<Note[]> {
  const files = await $`ls ${ROOT} | grep '\.txt$'`.text();
  const humids = files.trim().split("\n");

  return Promise.all(humids.map(fetchNoteByPath));
}

async function fetchNote(humid: string): Promise<Note> {
  const file = await $`cat ${join(ROOT, `/${humid}.txt`)}`.text();
  return parseNote(humid, file);
}

async function fetchNoteByPath(path: string): Promise<Note> {
  const file = await $`cat ${join(ROOT, path)}`.text();
  const humid = path.replace("/", "").replace(".txt", "");
  return parseNote(humid, file);
}

async function putNote(humid: string, md: string): Promise<Note> {
  await Bun.write(join(ROOT, `${humid}.txt`), md);
  return fetchNote(humid);
}

async function removeNote(humid: string): Promise<Note> {
  const path = join(ROOT, `${humid}.txt`);
  const file = Bun.file(path);
  const note = fetchNoteByPath(path);

  await file.delete();
  return note;
}

// Filesystem management

async function parseNote(humid: string, md: string): Promise<Note> {
  const title = await parseTitle(md);
  const headline = await parseHeadline(md);
  const html = await parseContents(md);
  const task = await parseTask(md);

  return { id: humid, title, headline, md, html, task };
}

async function parseTitle(md: string): Promise<string | null> {
  for (const line of md.split("\n")) {
    if(line.startsWith("TODO")) continue;
    if(line.startsWith("DONE")) continue;
    if(line.startsWith("NVM")) continue;
    if(line.trim() == "") continue;
    if(!line.trim().startsWith("# ")) break;
    return truncate(trimHeading(line), 20);
  }

  return null;
}

async function parseHeadline(md: string): Promise<string> {
  const contents = await removeToDos(md);
  return contents.split("\n").filter(line => line.trim() != "")[0];
}

async function parseContents(md: string): Promise<string> {
  md = await removeToDos(md);
  md = await linkOtherNotes(md);
  md = removeTitle(md);
  md = replaceLineBreaks(md);

  return marked.parse(md);
}

async function removeToDos(md: string): Promise<string> {
  return md.split('\n')
    .filter(line => !TODOS.some(m => line.startsWith(m)))
    .join('\n');
}

async function linkOtherNotes(md: string): Promise<string> {
  const matches = [...md.matchAll(/#([A-Z0-9]{5})/g)];
  const uniqueCodes = [...new Set(matches.map(m => m[1]))];

  const notes: any = {};
  await Promise.all(uniqueCodes.map(async (humid: string) => {
    notes[humid] = await fetchNote(humid);
  }))

  return md.replace(/#([A-Z0-9]{5})/g, (_, humid: string) => {
    const { id, title, headline } = notes[humid];
    return `[**#${id}**: ${title || headline}](/${id})`;
  });
}

async function parseTask(md: string): Promise<Task | null> {
  const lines = md.trim().split('\n').map(line => line.trim());

  const attributeOrder = ['TODO', 'DONE', 'NVM'];
  const pattern = /^(?<status>TODO|DONE|NVM)(?:\s+@\s*(?<date>\d{4}(?:-\d{1,2})?(?:-\d{1,2})?)?)?(?:\s+~(?<list>\S+))?$/i;

  const task = {
    status: undefined,
    deadline: undefined,
    list: "all",
    completed_at: undefined,
    shelved_at: undefined
  };

  const modifiers = lines
    .filter(line => line.match(pattern))
    .sort((a, b) => {
      const statusA = a.match(pattern)?.groups?.status?.toUpperCase() || '';
      const statusB = b.match(pattern)?.groups?.status?.toUpperCase() || '';
      return attributeOrder.indexOf(statusA) - attributeOrder.indexOf(statusB);
    });

  for (const line of modifiers) {
    const match = line.match(pattern);
    if (!match) continue;

    // @ts-ignore
    const { status, date, list } = match.groups;
    task.status = status?.toLowerCase();

    switch(task.status) {
      case 'todo':
        if (date) task.deadline = date;
        if (list) task.list = list;
        break;

      case 'done':
        if (date) task.completed_at = date;
        break;

      case 'nvm':
        if (date) task.shelved_at = date;
        break;
    }
  }

  return task.status ? task : null;
}

// String utilities

function truncate(str: string, length: number): string {
  if(str.length <= length) return str;
  else return str.substring(0, length - 3) + "...";
}

function trimHeading(str: string): string {
  return str.replace(/^#+\s*/, "");
}

function removeTitle(str: string): string {
  const lines = str.trim().split('\n');
  if (lines[0].trim().startsWith('#')) lines.shift();
  return lines.join('\n');
}

function replaceLineBreaks(md: string): string {
  return md.trim().split("\n").join("  \n");
}
