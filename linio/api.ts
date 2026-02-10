import { Config, Note } from "./types";
import { formatDate } from "./dates";

export async function getConfig(): Promise<Config> {
  const response = await fetch("/api/config");
  return await response.json();
}

export async function fetchNote(humid: string): Promise<Note> {
  const response = await fetch(`/api/note/${humid}`);
  return await response.json();
}

export async function listNotes(): Promise<Note[]> {
  const response = await fetch("/api/notes");
  return await response.json();
}

export async function listNotesByTag(tag: string): Promise<Note[]> {
  const response = await fetch(`/api/notes?tag=${tag}`);
  return await response.json();
}

// TODO(robin): this function should handle header format as well, and
// write proper Task-Status: done and Task-Completed: today headers.
export function completeNote(note: Note, checked: boolean): Promise<Note> {
  if (note.task) {
    let md = note.raw.replaceAll(/^(BACKLOG|BLOCKED|DONE|NVM).*$(\r?\n)?/gim, '');
    if(checked) md = insert(md, `DONE @ ${formatDate(new Date())}`);
    return updateNote(note, md);
  }
  
  if (note.wish) {
    const md = checked ?
      insert(note.raw, `BOUGHT @ ${formatDate(new Date())}`) :
      note.raw.replace(/^(BOUGHT|NVM).*$(\r?\n)?/im, '');
    return updateNote(note, md);
  }
  
  return Promise.resolve(note);
}

export async function setStatus(note: Note, status: string, reason?: string): Promise<Note> {
  if (note.task) {
    if (!['todo', 'backlog', 'blocked', 'done', 'nvm'].includes(status)) return note;

    let md = note.raw.replaceAll(/^(BACKLOG|BLOCKED|DONE|NVM).*$(\r?\n)?/gim, '');

    if (note.task.status != status) {
      if (status == 'nvm') md = insert(md, `NVM @ ${formatDate(new Date())}`);
      else if (status == 'backlog') md = insert(md, 'BACKLOG');
      else if (status == 'blocked') md = insert(md, reason ? `BLOCKED ${reason}` : 'BLOCKED');
      else if (status == 'done') md = insert(md, `DONE @ ${formatDate(new Date())}`);
    }

    return updateNote(note, md);
  }

  if (note.wish) {
    if (!['dream', 'bought', 'nvm'].includes(status)) return note;

    let md = note.raw.replaceAll(/^(BOUGHT|NVM).*$(\r?\n)?/gim, '');

    if (note.wish.status != status) {
      if (status == 'nvm') md = insert(md, `NVM @ ${formatDate(new Date())}`);
      else if (status == 'bought') md = insert(md, `BOUGHT @ ${formatDate(new Date())}`);
    }

    return updateNote(note, md);
  }

  return note;
}

function insert(raw: string, modifier: string): string {
  const lines = raw.split('\n');

  let i = 0;
  while (i < lines.length && /^[A-Za-z][^:]*:\s*.+/.test(lines[i].trim())) i++;
  
  if (i == 0) lines.splice(0, 0, modifier, '');
  else lines.splice(i, 0, modifier);

  return lines.join('\n');
}

export async function newNote(md: string): Promise<Note> {
  const response = await fetch(`/api/notes`, {
    method: "POST", body: JSON.stringify({ md })
  });

  return await response.json();
}

export function updateNote(note: Note, md: string): Promise<Note> {
  return new Promise(async (resolve, reject) => {
    const response = await fetch(`/api/note/${note.id}`, {
      method: "PUT",
      body: JSON.stringify({ md })
    });

    if(!response.ok) reject();
    resolve(await response.json());
  });
}

export async function deleteNote(note: Note): Promise<void> {
  await fetch(`/api/note/${note.id}`, { method: "DELETE" });
}

export async function fetchScratchpad(): Promise<string> {
  const response = await fetch("/api/scratchpad");
  return await response.text();
}

export async function updateScratchpad(content: string): Promise<void> {
  await fetch("/api/scratchpad", {
    method: "PUT",
    body: content
  });
}
