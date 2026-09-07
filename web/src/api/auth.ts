import { request } from './client'
import type { User } from '@/types'

export interface RegisterPayload {
  name: string
  email: string
  password: string
  password_confirmation: string
  agree_terms: boolean
}

export interface LoginPayload {
  email: string
  password: string
  remember?: boolean
}

export const authApi = {
  register: (payload: RegisterPayload) =>
    request<{ user: User; message: string }>({
      url: '/auth/register',
      method: 'post',
      data: payload,
    }),

  login: (payload: LoginPayload) =>
    request<{ user: User; message: string }>({
      url: '/auth/login',
      method: 'post',
      data: payload,
    }),

  logout: () => request<{ message: string }>({ url: '/auth/logout', method: 'post' }),

  me: () => request<{ user: User }>({ url: '/me' }),

  resendVerification: () =>
    request<{ message: string }>({ url: '/auth/email/resend', method: 'post' }),

  /** 验证连结带的签名参数原样转发给后端 */
  verifyEmail: (params: { id: string; hash: string; expires: string; signature: string }) =>
    request<{ user: User; message: string }>({
      url: `/auth/email/verify/${params.id}/${params.hash}`,
      method: 'get',
      params: { expires: params.expires, signature: params.signature },
    }),

  forgotPassword: (email: string) =>
    request<{ message: string }>({
      url: '/auth/password/forgot',
      method: 'post',
      data: { email },
    }),

  resetPassword: (payload: {
    token: string
    email: string
    password: string
    password_confirmation: string
  }) => request<{ message: string }>({ url: '/auth/password/reset', method: 'post', data: payload }),

  confirmEmailChange: (token: string) =>
    request<{ user: User; message: string }>({
      url: '/email-change/confirm',
      method: 'post',
      data: { token },
    }),
}
