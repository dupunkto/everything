import { Note } from "./types";
import { formatDate } from "./dates";

export async function fetchNote(humid: string): Promise<Note> {
  const response = await fetch(`/api/note/${humid}`);
  return await response.json();
}

export async function listNotes(): Promise<Note[]> {
  const response = await fetch("/api/notes");
  return await response.json();
}

export function completeTask(note: Note, checked: boolean): Promise<Note> {
  const md = checked ?
    `DONE @ ${formatDate(new Date())}\n${note.raw}` :
    note.raw.replace(/^(DONE|NVM).*$(\r?\n)?/im, '');

  return updateNote(note, md);
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
