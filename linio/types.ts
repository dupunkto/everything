export interface Config {
  format: string,
  lists: string[],
  features: 'basic' | 'fancy',
  git: boolean
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
  tags: string[],
  task: Task | null,
  wish: Wish | null,
  created_at: string,
  modified_at: string
}

export interface Task {
  status: 'todo' | 'backlog' | 'done' | 'nvm',
  deadline?: string,
  list?: string,
  completed_at?: string,
  shelved_at?: string,
  recurrence?: number
}

export interface Wish {
  status: 'dream' | 'bought' | 'nvm',
  bought_at?: string,
  shelved_at?: string
}
