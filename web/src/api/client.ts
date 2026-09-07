import axios, { AxiosError, type AxiosInstance } from 'axios'
import type { ApiFailure } from '@/types'

/**
 * Axios 实例。
 *
 * 认证走 Sanctum 的 SPA 模式：凭证在 httpOnly Cookie 里，
 * 前端 JS 读不到 token，因此 XSS 也偷不走登入态。
 * 代价是必须处理 CSRF —— 见 ensureCsrfCookie()。
 */
const http: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  withCredentials: true, // 带上 Cookie
  withXSRFToken: true, // 自动把 XSRF-TOKEN cookie 放进 X-XSRF-TOKEN 头
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

/** 业务错误：携带后端约定的错误码与字段级错误 */
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

  /** 取某个字段的第一条错误，用于表单内联提示 */
  fieldError(field: string): string | undefined {
    return this.fieldErrors[field]?.[0]
  }
}

let csrfReady: Promise<void> | null = null

/**
 * 首次写操作前取一次 CSRF cookie。
 * 用 Promise 缓存，避免并发请求时重复打这个接口。
 */
export function ensureCsrfCookie(): Promise<void> {
  csrfReady ??= axios
    .get('/sanctum/csrf-cookie', { withCredentials: true })
    .then(() => undefined)
    .catch((error) => {
      csrfReady = null // 失败就允许下次重试
      throw error
    })

  return csrfReady
}

/** CSRF token 过期后需要重新获取 */
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
    // 网络层错误：请求根本没到服务器
    if (!error.response) {
      throw new ApiError('NETWORK_ERROR', '网络连线异常，请检查网络后重试', 0)
    }

    const { status, data } = error.response

    // 419：session 过期导致 CSRF token 失效，清掉缓存让下次重新获取
    if (status === 419) {
      resetCsrfCookie()
      throw new ApiError('CSRF_EXPIRED', '页面已过期，请重新整理后再试', 419)
    }

    if (data && typeof data === 'object' && 'code' in data) {
      throw new ApiError(data.code, data.message, status, data.errors ?? {}, data.retry_after)
    }

    throw new ApiError('UNKNOWN_ERROR', '发生未预期的错误，请稍后再试', status)
  },
)

/** 拆掉统一响应外壳，业务层直接拿 data */
export async function request<T>(config: Parameters<AxiosInstance['request']>[0]): Promise<T> {
  const response = await http.request<{ ok: true; data: T }>(config)

  return response.data.data
}

export default http
