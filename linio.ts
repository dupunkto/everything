import { argv, serve } from "bun";
import { parseArgs } from "util";
import * as fs from "node:fs/promises";
import { join } from "node:path";
import { watch } from "node:fs";
import { marked } from "marked";

const { values, positionals } = parseArgs({
  args: argv,
  strict: false,
  options: {
    lists: { type: 'string' },
    format: { type: 'string' },
    basic: { type: 'boolean' },
    h: { type: 'boolean' },
    m: { type: 'boolean' },
    b: { type: 'boolean' }
  },
  allowPositionals: true,
});

const ROOT = positionals[2] || process.cwd();

// Default in case the CLI argument is omitted.
const LISTS = ["all", "life", "projects", "maakotheek", "qdentity", "writing"];

const TODOS = ['TODO', 'DONE', 'NVM'];
const WISHES = ['WISH', 'BOUGHT', 'NVM'];

const HUMID_PATTERN = /#([A-Z0-9]{5})/g;
const TAG_PATTERN = /\[\[([^\]]+)\]\]/g;
const TODO_PATTERN = /^(TODO|DONE|NVM)(?:\s+@\s*(\d{4}(?:-\d{1,2})?(?:-\d{1,2})?))?(?:\s+~(\S+))?$/i;
const WISH_PATTERN = /^(WISH|BOUGHT|NVM)(?:\s+@\s*(\d{4}(?:-\d{1,2})?(?:-\d{1,2})?))?$/i;
const HEADER_PATTERN = /^([A-Za-z-]+):\s+(.*)$/;

import { generateHumID } from "./linio/humid";
import { Note, Task, Wish, Config } from "./linio/types";
import { normalizeDate } from "./linio/dates";

if(values.h) values.format = 'headers';
if(values.m) values.format = 'modifiers';
if(values.b) values.format = 'mixed';

if(!values.format) values.format = 'mixed';

const config: Config = {
  format: values.format as string,
  lists: typeof values.lists == 'string' ? values.lists.split(',') : LISTS,
  features: 'basic',
};

import app from "./public/index.html";

const server = serve({
  port: 9000,
  hostname: "linio",
  development: DEV,
  routes: {
    "/": app,
    "/:page": app,
    "/Tag/:tag": app,
    
    // API endpoints
    "/api/config": {
      GET: getConfig,
    },

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

async function getConfig(req: BunRequest) {
  return Response.json(config);
}

async function getNotes(req: BunRequest) {
  const { searchParams } = new URL(req.url);
  const tag = searchParams.get('tag');
  
  if(tag) return Response.json(await listNotesByTag(tag));
  else return Response.json(await listNotes());
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

async function listNotesByTag(tag: string): Promise<Note[]> {
  return (await listNotes()).filter(n => n.tags.includes(tag));
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
  let created_at = normalizeDate(stat.birthtime.toISOString())!;
  let modified_at = normalizeDate(stat.mtime.toISOString())!;

  const note: Note = {
    id: humid,
    type: 'note',
    title: null,
    headline: "",
    raw: md,
    text: "",
    html: "",
    headers: {},
    tags: [],
    task: null,
    wish: null,
    created_at,
    modified_at
  };
  
  if (!md || typeof md != 'string') return note;

  const lines = md.trim().split('\n');
  
  // Parse headers first
  const { headers, bodyStart } = parseHeaders(lines);
  populateNoteFromHeaders(note, headers);

  // Process body content
  const bodyLines = lines.slice(bodyStart);
  const wishLines: string[] = [];
  const taskLines: string[] = [];
  const contentLines: string[] = [];

  for (let [i, line] of bodyLines.entries()) {
    if(line.trim().match("^https?://.*") && i == 0) {
      note.type = 'bookmark';
    }

    // We still support old-style modifiers, along with new-style headers.
    // Which method the frontend will write is configurable via the CLI.
    if(line.startsWith("TODO") && note.type == 'note') note.type = 'task';
    if(line.startsWith("WISH") && note.type == 'note') note.type = 'wish';
    
    if (WISHES.some(wish => line.startsWith(wish))) wishLines.push(line);
    if (TODOS.some(todo => line.startsWith(todo))) taskLines.push(line);

    if(TODOS.concat(WISHES).some(mod => line.startsWith(mod))) continue;

    if (!note.title && line.startsWith('# ')) {
      const title = line.replace(/^#+\s*/, '');
      note.title = await marked.parseInline(title);
    }
    
    if (!note.headline && line.trim() != '')
      note.headline = line;

    contentLines.push(line);
  }

  if (note.type == 'wish' && !note.wish) note.wish = parseWish(wishLines) ?? { status: 'dream' };
  if (note.type == 'task' && !note.task) note.task = parseTask(taskLines) ?? { status: 'todo' }; 

  note.text = contentLines.join('\n').trim();
  
  const matches = Array.from(md.matchAll(TAG_PATTERN));
  note.tags = Array.from(new Set(matches.map(m => m[1])));

  // We do not want the title in the HTML
  while(contentLines[0]?.trim() == '') contentLines.shift();
  if (contentLines.length > 0 && contentLines[0].trim().startsWith('#'))
    contentLines.shift();

  // Please excuse this monstrosity. I miss the Erlang pipe operator ok.
  note.html = await marked.parse(await processText(contentLines.join('  \n')));

  return note;
}

async function processText(md: string): Promise<string> {  
  const matches = Array.from(md.matchAll(HUMID_PATTERN));
  const notes: Record<string, Note> = {};

  if (matches.length > 0) {
    const uniqueCodes = Array.from(new Set(matches.map(m => m[1])));

    await Promise.all(uniqueCodes.map(async (humid: string) => {
      const note = await fetchNote(humid);
      if (note) notes[humid] = note;
    }));

    md = md.replace(/#([A-Z0-9]{5})/g, (_, humid: string) => {
      const note = notes[humid]; if (!note) return `#${humid}`;
      const { id, title, headline } = note;
      return `[**#${id}**: ${title || headline}](/${id})`;
    });
  }

  return md.replace(/\[\[([^\]]+)\]\]/g, (_, tag: string) => {
    return `<a href="/Tag/${tag}">[[${tag}]]</a>`;
  });
}

function parseHeaders(lines: string[]): { headers: Record<string, string>, bodyStart: number } {
  const headers: Record<string, string> = {};
  let bodyStart = 0;
  
  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    
    if (line.trim() === '') {
      bodyStart = i + 1;
      break;
    }
    
    const match = line.match(HEADER_PATTERN);
    if (!match) {
      bodyStart = i;
      break;
    }
    
    const [, key, value] = match;
    headers[key] = value.trim();
  }
  
  return { headers, bodyStart };
}

function populateNoteFromHeaders(note: Note, headers: Record<string, string>) {
  for (const [key, value] of Object.entries(headers)) {
    switch (key.toLowerCase()) {
      case 'created':
        const createdDate = normalizeDate(value);
        if (createdDate) note.created_at = createdDate;
        break;
        
      case 'modified':
        const modifiedDate = normalizeDate(value);
        if (modifiedDate) note.modified_at = modifiedDate;
        break;
        
      case 'type':
        note.type = value;
        break;
        
      default:
        switch(true) {
          case key.startsWith('Task-'):
            if (!note.task) note.task = { status: 'todo' };
            populateTaskFromHeader(note.task, key.slice(5), value);
            break;

          case key.startsWith('Wish-'):
            if (!note.wish) note.wish = { status: 'dream' };
            populateWishFromHeader(note.wish, key.slice(5), value);
            break;

          default:
            note.headers[key] = value;
            break;
        }
        break;
    }
  }
}

function populateTaskFromHeader(task: Task, field: string, value: string) {
  switch (field.toLowerCase()) {
    case 'status':
      if (['todo', 'done', 'nvm'].includes(value)) {
        task.status = value as Task['status'];
      }
      break;

    case 'deadline':
      task.deadline = value;
      break;

    case 'list':
      task.list = value;
      break;

    case 'completed':
      task.completed_at = value;
      break;

    case 'shelved':
      task.shelved_at = value;
      break;
  }
}

function populateWishFromHeader(wish: Wish, field: string, value: string) {
  switch (field.toLowerCase()) {
    case 'status':
      if (['dream', 'bought', 'nvm'].includes(value)) {
        wish.status = value as Wish['status'];
      }
      break;

    case 'bought':
      wish.bought_at = value;
      break;

    case 'shelved':
      wish.shelved_at = value;
      break;
  }
}

function parseWish(lines: string[]): Wish | null {
  if (lines.length == 0) return null;

  const wish: Wish = {
    status: 'dream',
    bought_at: undefined,
    shelved_at: undefined
  };

  const modifiers = lines
    .map(line => line.match(WISH_PATTERN))
    .filter(match => match != null)
    .sort((a, b) => {
      const statusA = a[1]?.toUpperCase() || '';
      const statusB = b[1]?.toUpperCase() || '';

      return WISHES.indexOf(statusA) - WISHES.indexOf(statusB);
    });

  for (const [, status, date] of modifiers) {
    if (!status) continue;

    switch (status.toLowerCase()) {
      case 'wish':
        wish.status = 'dream';
        break;

      case 'bought':
        wish.status = 'bought';
        if (date) wish.bought_at = date;
        break;

      case 'nvm':
        wish.status = 'nvm';
        if (date) wish.shelved_at = date;
        break;
    }
  }

  return wish;
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

    task.status = status.toLowerCase() as Task['status'];

    switch (task.status) {
      case 'todo':
        if (date) task.deadline = date;
        if (list && list != 'all') task.list = list;
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
