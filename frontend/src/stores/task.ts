import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '@/api/client'
import type { Category, Task } from '@/types'

export const useTaskStore = defineStore('task', () => {
  const categories = ref<Category[]>([])
  const tasks = ref<Task[]>([])
  const currentTask = ref<Task | null>(null)

  async function fetchCategories() {
    const { data } = await apiClient.get<Category[]>('/categories')
    categories.value = data
    return data
  }

  async function fetchTasks(params?: Record<string, unknown>) {
    const { data } = await apiClient.get<Task[]>('/tasks', { params })
    tasks.value = data
    return data
  }

  async function fetchTask(id: number) {
    const { data } = await apiClient.get<Task>(`/tasks/${id}`)
    currentTask.value = data
    return data
  }

  async function createTask(payload: Partial<Task>) {
    const { data } = await apiClient.post<Task>('/tasks', payload)
    tasks.value.unshift(data)
    return data
  }

  async function updateTaskStatus(id: number, status: Task['status']) {
    const { data } = await apiClient.put<Task>(`/tasks/${id}`, { status })
    if (currentTask.value?.id === id) currentTask.value = data
    return data
  }

  return { categories, tasks, currentTask, fetchCategories, fetchTasks, fetchTask, createTask, updateTaskStatus }
})
