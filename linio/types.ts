export interface Note {
  id: string,
  title: string | null,
  headline: string,
  html: string,
  md: string,
  task: Task | null
}

export interface Task {
  status: string, // 'done', 'todo' or 'nvm'
  deadline?: string,
  list?: string,
  completed_at?: string,
  shelved_at?: string
}
