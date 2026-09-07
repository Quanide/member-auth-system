import { request } from './client'
import type { AuditLog, Pagination, User } from '@/types'

export interface AdminUserFilters {
  keyword?: string
  status?: string
  role?: string
  verified?: boolean
  page?: number
  per_page?: number
}

export interface AdminOverview {
  total_users: number
  new_today: number
  new_7d: number
  verified: number
  unverified: number
  locked: number
  disabled: number
  online: number
  active_7d: number
}

export interface AdminStats {
  overview: AdminOverview
  registration_trend: Array<{ date: string; count: number }>
  login_trend: { dates: string[]; success: number[]; failed: number[] }
  status_distribution: Array<{ name: string; value: number }>
  device_distribution: Array<{ name: string; value: number }>
}

export interface AdminAuditLog extends AuditLog {
  user: { id: number; email: string; display_name: string } | null
}

export const adminApi = {
  stats: () => request<AdminStats>({ url: '/admin/stats' }),

  users: (params: AdminUserFilters) =>
    request<{ items: User[]; pagination: Pagination }>({ url: '/admin/users', params }),

  user: (id: number) =>
    request<{ user: User; sessions_count: number; recent_activities: AuditLog[] }>({
      url: `/admin/users/${id}`,
    }),

  updateStatus: (id: number, status: string) =>
    request<{ user: User; message: string }>({
      url: `/admin/users/${id}/status`,
      method: 'patch',
      data: { status },
    }),

  updateRole: (id: number, role: string) =>
    request<{ user: User; message: string }>({
      url: `/admin/users/${id}/role`,
      method: 'patch',
      data: { role },
    }),

  unlock: (id: number) =>
    request<{ user: User; message: string }>({ url: `/admin/users/${id}/unlock`, method: 'post' }),

  forceLogout: (id: number) =>
    request<{ message: string }>({ url: `/admin/users/${id}/force-logout`, method: 'post' }),

  remove: (id: number) =>
    request<{ message: string }>({ url: `/admin/users/${id}`, method: 'delete' }),

  auditLogs: (params: {
    action?: string
    user_id?: number
    keyword?: string
    from?: string
    to?: string
    page?: number
    per_page?: number
  }) => request<{ items: AdminAuditLog[]; pagination: Pagination }>({ url: '/admin/audit-logs', params }),
}
