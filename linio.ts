import { argv, serve } from "bun";
import * as fs from "node:fs/promises";
import { join } from "node:path";
import { watch } from "node:fs";
import { marked } from "marked";

const ROOT = argv[2];
const TODOS = ['TODO', 'DONE', 'NVM'];

const HUMID_PATTERN = /#([A-Z0-9]{5})/g;
const TODO_PATTERN = /^(TODO|DONE|NVM)(?:\s+@\s*(\d{4}(?:-\d{1,2})?(?:-\d{1,2})?))?(?:\s+~(\S+))?$/i;

import { generateHumID } from "./linio/humid";
import { Note, Task } from "./linio/types";
import { normalizeDate } from "./linio/dates";

import app from "./public/index.html";

const server = serve({
  port: 9000,
  hostname: "linio",
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

// In-memory cache
const noteCache = new Map<string, Note>();
const fileStats = new Map<string, number>();

watch(ROOT, (_, filename) => {
  if (!filename?.endsWith('.txt')) return;

  const humid = filename.replace('.txt', '');
  noteCache.delete(humid);
  fileStats.delete(filename);
});

// HTTP handlers

import { BunRequest } from "bun";

async function getNotes(req: BunRequest) {
  return Response.json(await listNotes());
}

async function getNote(req: BunRequest) {
  const { humid }: any = req.params;
  const note = await fetchNote(humid);

  return note ? Response.json(note)
    : sendError(404, "Note not found");
}

async function updateNote(req: BunRequest) {
  const { humid }: any = req.params;
  const body = await req.json().catch(() => null);
  
  if (!humid || typeof humid != 'string')
    return sendError(400, 'Missing or bad HumID.');
  
  if (!body || typeof body.md != 'string')
    return sendError(400, 'Missing or bad Markdown.');

  return Response.json(await putNote(humid, body.md));
}

async function newNote(req: Request) {
  const body = await req.json().catch(() => null);
  
  if (!body || typeof body.md != 'string')
    return sendError(400, "Missing or bad Markdown.");

  return Response.json(await putNote(generateHumID(), body.md));
}

async function deleteNote(req: BunRequest) {
  const { humid }: any = req.params;

  if (!humid || typeof humid !== 'string')
    return sendError(400, 'Missing or bad HumID.');

  return Response.json(await removeNote(humid));
}

function sendError(status: number, error: string) {
  return new Response(JSON.stringify({ error }), {
    status, headers: {'Content-Type': 'application/json'}
  });
}

// Programmatic API

async function listNotes(): Promise<Note[]> {
  const humids = (await fs.readdir(ROOT, { withFileTypes: true }))
    .filter(f => f.isFile() && f.name.endsWith('.txt'))
    .map(f => f.name.replace('.txt', ''));

  const notes = await Promise.all(humids.map(id => fetchNote(id)));
  return notes.filter((note): note is Note => note != null);
}

async function fetchNote(humid: string): Promise<Note | null> {  
  const path = join(ROOT, `${humid}.txt`);
  const file = Bun.file(path);
  
  if (!await file.exists()) return null;

  const stat = await file.stat();
  const cachedMod = fileStats.get(humid);
  
  if (noteCache.has(humid) && cachedMod == stat.mtime.getTime())
    return noteCache.get(humid)!;

  const content = await file.text();
  const note = await parseNote(humid, content, stat);
  
  noteCache.set(humid, note);
  fileStats.set(humid, stat.mtime.getTime());
  
  return note;
}

async function putNote(humid: string, md: string): Promise<Note> {
  const path = join(ROOT, `${humid}.txt`);
  await Bun.write(path, md);
  
  noteCache.delete(humid);
  fileStats.delete(humid);
  
  return await fetchNote(humid) as Note;
}

async function removeNote(humid: string): Promise<Note> {
  const note = await fetchNote(humid);
  if (!note) throw new Error('Note not found');

  const path = join(ROOT, `${humid}.txt`);
  const file = Bun.file(path);

  if (await file.exists()) await fs.unlink(path);
  
  noteCache.delete(humid);
  fileStats.delete(humid);
  
  return note;
}

// Note parsing (single-pass optimization)

async function parseNote(humid: string, md: string, stat: any): Promise<Note> {
  const created_at = normalizeDate(stat.birthtime.toISOString())!;
  const modified_at = normalizeDate(stat.mtime.toISOString())!;

  const note: Note = {
    id: humid,
    type: 'note',
    title: null,
    headline: "",
    raw: md,
    text: "",
    html: "",
    task: null,
    created_at,
    modified_at
  };
  
  if (!md || typeof md != 'string') return note;

  const lines = md.split('\n');

  const taskLines: string[] = [];
  const contentLines: string[] = [];

  for (let line of lines) {    
    if (TODOS.some(todo => line.startsWith(todo))) {
      note.type = 'task';
      taskLines.push(line);
      continue;
    }

    if(line.startsWith("WISHLIST")) {
      note.type = 'wish';
      continue;
    }

    if (!note.title && line.startsWith('# ')) {
      const title = line.replace(/^#+\s*/, '');
      note.title = await marked.parseInline(title);
    }
    
    if (!note.headline && line.trim() != '')
      note.headline = line;

    contentLines.push(await linkOtherNotes(line));
  }

  note.text = contentLines.join('\n').trim();

  // We do not want the title in the HTML
  while(contentLines[0].trim() == '') contentLines.shift();
  if (contentLines.length > 0 && contentLines[0].trim().startsWith('#'))
    contentLines.shift();

  note.html = await marked.parse(contentLines.join('  \n'));
  note.task = parseTask(taskLines);

  return note;
}

function parseTask(lines: string[]): Task | null {
  if (lines.length == 0) return null;

  const task: Task = {
    status: 'todo',
    deadline: undefined,
    list: undefined,
    completed_at: undefined,
    shelved_at: undefined
  };

  const modifiers = lines
    .map(line => line.match(TODO_PATTERN))
    .filter(match => match != null)
    .sort((a, b) => {
      const statusA = a[1]?.toUpperCase() || '';
      const statusB = b[1]?.toUpperCase() || '';

      return TODOS.indexOf(statusA) - TODOS.indexOf(statusB);
    });

  for (const [, status, date, list] of modifiers) {
    if (!status) continue;

    task.status = status.toLowerCase();

    switch (task.status) {
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

  return task;
}

async function linkOtherNotes(line: string): Promise<string> {  
  const matches = Array.from(line.matchAll(HUMID_PATTERN));
  if (matches.length == 0) return line;
  
  const uniqueCodes = Array.from(new Set(matches.map(m => m[1])));
  const notes: Record<string, Note> = {};

  await Promise.all(uniqueCodes.map(async (humid: string) => {
    const note = await fetchNote(humid);
    if (note) notes[humid] = note;
  }));

  return line.replace(/#([A-Z0-9]{5})/g, (_, humid: string) => {
    const note = notes[humid]; if (!note) return `#${humid}`;
    const { id, title, headline } = note;
    return `[**#${id}**: ${title || headline}](/${id})`;
  });
}
