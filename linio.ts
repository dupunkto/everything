import { $, argv, serve } from "bun";
import { join } from "node:path";
import { marked } from "marked";

import dashboard from "./templates/dashboard.html";
import index from "./templates/index.html";
import todo from "./templates/todo.html";
import note from "./templates/note.html";

const ROOT = argv[2];

const server = serve({
  port: 4000,
  development: true,
  routes: {
    "/": dashboard,
    "/Index": index,
    "/ToDo": todo,
    "/:humid": note,
    
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

// Request handlers

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
  return Response.json(await putNote(generateID(), md));
}

async function deleteNote(req: Request) {
  // @ts-ignore
  const { humid } = req.params;
  return Response.json(await removeNote(humid));
}

// Public API

async function listNotes() {
  const files = await $`ls ${ROOT} | grep '\.txt$'`.text();
  const humids = files.trim().split("\n");

  return Promise.all(humids.map(fetchNoteByPath));
}

async function fetchNote(humid: string) {
  const file = await $`cat ${join(ROOT, `/${humid}.txt`)}`.text();
  return parseNote(humid, file);
}

async function fetchNoteByPath(path: string) {
  const file = await $`cat ${join(ROOT, path)}`.text();
  const humid = path.replace("/", "").replace(".txt", "");
  return parseNote(humid, file);
}

async function putNote(humid: string, md: string) {
  await Bun.write(join(ROOT, `${humid}.txt`), md);
  return fetchNote(humid);
}

async function removeNote(humid: string) {
  const path = join(ROOT, `${humid}.txt`);
  const file = Bun.file(path);
  const note = fetchNoteByPath(path);

  await file.delete();
  return note;
}

async function parseNote(humid: string, md: string) {
  const title = await parseTitle(md);
  const html = await parseContents(md);
  const task = await parseTask(md);

  return { id: humid, title, md, html, task };
}

async function parseTitle(md: string) {
  for (const line of md.split("\n")) {
    if(line.startsWith("TODO")) continue;
    if(line.startsWith("DONE")) continue;
    if(line.startsWith("DNF")) continue;
    if(line.trim() == "") continue;
    else return truncate(trimHeading(line), 20);
  }
}

function trimHeading(str: string) {
  return str.replace(/^#+\s*/, "");
}

function truncate(str: string, length: number) {
  if(str.length <= length) return str;
  else return str.substring(0, length - 3) + "...";
}

async function parseContents(md: string) {
  md = removeToDos(md);
  md = replaceLineBreaks(md);
  md = await linkOtherNotes(md);

  return marked.parse(md);
}

function removeToDos(md: string) {
  const lines = md.split('\n');
  const kw = ['TODO', 'DONE', 'DNF'];

  for(let i = 0; i < 2; i++) {
    if (lines[0] && kw.some(k => lines[0].startsWith(k))) lines.shift();
  }

  return lines.join('\n');
}

function replaceLineBreaks(md: string) {
  return md.trim().split("\n").join("  \n");
}

async function linkOtherNotes(md: string) {
  const matches = [...md.matchAll(/#([A-Z0-9]{5})/g)];
  const uniqueCodes = [...new Set(matches.map(m => m[1]))];

  const notes: any = {};
  await Promise.all(uniqueCodes.map(async (humid: string) => {
    notes[humid] = await fetchNote(humid);
  }))

  return md.replace(/#([A-Z0-9]{5})/g, (_, humid: string) => {
    const { id, title } = notes[humid];
    return `[${title} (${id})](/${id})`;
  });
}

async function parseTask(md: string) {
  const lines = md.trim().split('\n').map(line => line.trim());

  const attributeOrder = ['TODO', 'DONE', 'DNF'];
  const pattern = /^(?<status>TODO|DONE|DNF)(?:\s+@\s*(?<date>\d{4}(?:-\d{1,2})?(?:-\d{1,2})?)?)?(?:\s+~(?<list>\S+))?$/i;

  const task = {
    status: "none",
    deadline: null,
    list: "all",
    completed_at: null,
    shelved_at: null
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

      case 'dnf':
        if (date) task.shelved_at = date;
        break;
    }
  }

  return task.status != "none" ? task : null;
}

function generateID() {
  const LENGTH = 5;
  const BASE = 36;

  const buffer = new Uint8Array(4);
  crypto.getRandomValues(buffer);

  let n = 0;
  for (let i = 0; i < buffer.length; i++)
    n = (n << 8) | buffer[i];

  return n.toString(BASE).toUpperCase().padStart(LENGTH, '0').slice(-LENGTH);
}