import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '@/api/client'
import type { ChatMessage } from '@/types'

export const useChatStore = defineStore('chat', () => {
  const messagesByTask = ref<Record<number, ChatMessage[]>>({})

  async function fetchMessages(taskId: number) {
    const { data } = await apiClient.get<ChatMessage[]>(`/tasks/${taskId}/messages`)
    messagesByTask.value[taskId] = data
    return data
  }

  async function sendMessage(taskId: number, isi: string) {
    const { data } = await apiClient.post<ChatMessage>(`/tasks/${taskId}/messages`, { isi })
    if (!messagesByTask.value[taskId]) messagesByTask.value[taskId] = []
    messagesByTask.value[taskId].push(data)
    return data
  }

  return { messagesByTask, fetchMessages, sendMessage }
})
