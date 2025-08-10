export interface Note {
  id: string,
  type: string, // 'note', 'task', 'wish'
  title: string | null,
  headline: string,
  html: string,
  text: string,
  raw: string,
  task: Task | null
}

export interface Task {
  status: string, // 'done', 'todo' or 'nvm'
  deadline?: string,
  list?: string,
  completed_at?: string,
  shelved_at?: string
}
