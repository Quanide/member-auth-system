/** 與後端 App\Http\Resources\UserResource 一一對應 */
export interface User {
  id: number
  email: string
  email_verified: boolean
  email_verified_at: string | null
  name: string
  nickname: string | null
  display_name: string
  phone: string | null
  birthday: string | null
  gender: 'male' | 'female' | 'other' | null
  bio: string | null
  avatar_url: string | null
  two_factor_enabled: boolean
  recovery_codes_remaining: number
  role: string
  role_label: string
  status: string
  status_label: string
  last_login_at: string | null
  last_login_ip: string | null
  created_at: string | null
}

export interface AuditLog {
  id: number
  action: string
  action_label: string
  level: 'success' | 'warning' | 'danger' | 'info'
  ip_address: string | null
  user_agent: string | null
  meta: Record<string, unknown> | null
  created_at: string | null
}

export interface LoginSession {
  id: string
  is_current: boolean
  ip_address: string | null
  device: string
  user_agent: string | null
  last_active_at: string
}

export interface Overview {
  user: User
  stats: {
    active_sessions: number
    failed_logins_7d: number
    total_activities: number
    account_age_days: number
  }
  security: {
    email_verified: boolean
    has_avatar: boolean
    profile_completeness: number
  }
  recent_logins: AuditLog[]
}

/** 後端統一響應外殼 */
export interface ApiSuccess<T> {
  ok: true
  data: T
}

export interface ApiFailure {
  ok: false
  code: string
  message: string
  errors?: Record<string, string[]>
  retry_after?: number
}

export interface Pagination {
  total: number
  per_page: number
  current_page: number
  last_page: number
}
