import axios, { AxiosError, type AxiosInstance } from 'axios'
import type { ApiFailure } from '@/types'

/**
 * Axios 實例。
 *
 * 認證走 Sanctum 的 SPA 模式：憑證在 httpOnly Cookie 裡，
 * 前端 JS 讀不到 token，因此 XSS 也偷不走登入態。
 * 代價是必須處理 CSRF —— 見 ensureCsrfCookie()。
 */
const http: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  withCredentials: true, // 帶上 Cookie
  withXSRFToken: true, // 自動把 XSRF-TOKEN cookie 放進 X-XSRF-TOKEN 頭
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

/** 業務錯誤：攜帶後端約定的錯誤碼與欄位級錯誤 */
export class ApiError extends Error {
  constructor(
    readonly code: string,
    message: string,
    readonly status: number,
    readonly fieldErrors: Record<string, string[]> = {},
    readonly retryAfter?: number,
  ) {
    super(message)
    this.name = 'ApiError'
  }

  /** 取某個欄位的第一條錯誤，用於表單內聯提示 */
  fieldError(field: string): string | undefined {
    return this.fieldErrors[field]?.[0]
  }
}

let csrfReady: Promise<void> | null = null

/**
 * 首次寫操作前取一次 CSRF cookie。
 * 用 Promise 快取，避免並發請求時重複打這個接口。
 */
export function ensureCsrfCookie(): Promise<void> {
  csrfReady ??= axios
    .get('/sanctum/csrf-cookie', { withCredentials: true })
    .then(() => undefined)
    .catch((error) => {
      csrfReady = null // 失敗就允許下次重試
      throw error
    })

  return csrfReady
}

/** CSRF token 過期後需要重新獲取 */
export function resetCsrfCookie(): void {
  csrfReady = null
}

http.interceptors.request.use(async (config) => {
  const method = (config.method ?? 'get').toLowerCase()

  if (['post', 'put', 'patch', 'delete'].includes(method)) {
    await ensureCsrfCookie()
  }

  return config
})

http.interceptors.response.use(
  (response) => response,
  async (error: AxiosError<ApiFailure>) => {
    // 網路層錯誤：請求根本沒到伺服器
    if (!error.response) {
      throw new ApiError('NETWORK_ERROR', '網路連線異常，請檢查網路後重試', 0)
    }

    const { status, data } = error.response

    // 419：session 過期導致 CSRF token 失效，清掉快取讓下次重新獲取
    if (status === 419) {
      resetCsrfCookie()
      throw new ApiError('CSRF_EXPIRED', '頁面已過期，請重新整理後再試', 419)
    }

    if (data && typeof data === 'object' && 'code' in data) {
      throw new ApiError(data.code, data.message, status, data.errors ?? {}, data.retry_after)
    }

    throw new ApiError('UNKNOWN_ERROR', '發生未預期的錯誤，請稍後再試', status)
  },
)

/** 拆掉統一響應外殼，業務層直接拿 data */
export async function request<T>(config: Parameters<AxiosInstance['request']>[0]): Promise<T> {
  const response = await http.request<{ ok: true; data: T }>(config)

  return response.data.data
}

export default http
