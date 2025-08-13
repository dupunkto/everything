export interface Config {
  lists: string[],
  useHeaders: boolean
}

export interface Note {
  id: string,
  type: string, // 'note', 'task', 'wish'
  title: string | null,
  headline: string,
  html: string,
  text: string,
  raw: string,
  headers: Record<string, string>,
  task: Task | null,
  wish: Wish | null,
  created_at: string,
  modified_at: string
}

export interface Task {
  status: 'todo' | 'done' | 'nvm',
  deadline?: string,
  list?: string,
  completed_at?: string,
  shelved_at?: string
}

export interface Wish {
  status: 'dream' | 'bought' | 'nvm',
  bought_at?: string,
  shelved_at?: string
}
