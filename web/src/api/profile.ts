import { request } from './client'
import type { AuditLog, LoginSession, Overview, Pagination, User } from '@/types'

export interface ProfilePayload {
  name: string
  nickname: string | null
  phone: string | null
  birthday: string | null
  gender: string | null
  bio: string | null
}

export const profileApi = {
  overview: () => request<Overview>({ url: '/me/overview' }),

  update: (payload: ProfilePayload) =>
    request<{ user: User; message: string }>({ url: '/me', method: 'patch', data: payload }),

  uploadAvatar: (file: File) => {
    const form = new FormData()
    form.append('avatar', file)

    return request<{ user: User; message: string }>({
      url: '/me/avatar',
      method: 'post',
      data: form,
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },

  removeAvatar: () =>
    request<{ user: User; message: string }>({ url: '/me/avatar', method: 'delete' }),

  changePassword: (payload: {
    current_password: string
    password: string
    password_confirmation: string
  }) => request<{ message: string }>({ url: '/me/password', method: 'put', data: payload }),

  requestEmailChange: (payload: { new_email: string; current_password: string }) =>
    request<{ message: string }>({ url: '/me/email-change', method: 'post', data: payload }),

  sessions: () => request<{ sessions: LoginSession[] }>({ url: '/me/sessions' }),

  revokeSession: (id: string) =>
    request<{ message: string }>({ url: `/me/sessions/${id}`, method: 'delete' }),

  revokeOtherSessions: () =>
    request<{ message: string }>({ url: '/me/sessions/others', method: 'delete' }),

  activities: (params: { page?: number; per_page?: number; action?: string }) =>
    request<{ items: AuditLog[]; pagination: Pagination }>({ url: '/me/activities', params }),

  activityActions: () =>
    request<{ actions: Array<{ value: string; label: string; level: string }> }>({
      url: '/me/activities/actions',
    }),
}
