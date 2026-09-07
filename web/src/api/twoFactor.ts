import { request } from './client'

export const twoFactorApi = {
  /** 产生密钥与 QR Code，此时尚未启用 */
  generate: () =>
    request<{ secret: string; qr_code: string; otpauth_url: string }>({
      url: '/me/two-factor/generate',
      method: 'post',
    }),

  /** 输入验证码确认绑定，回传一次性恢复码 */
  confirm: (code: string) =>
    request<{ recovery_codes: string[]; message: string }>({
      url: '/me/two-factor/confirm',
      method: 'post',
      data: { code },
    }),

  disable: (password: string) =>
    request<{ message: string }>({
      url: '/me/two-factor/disable',
      method: 'post',
      data: { password },
    }),

  regenerateRecoveryCodes: (password: string) =>
    request<{ recovery_codes: string[]; message: string }>({
      url: '/me/two-factor/recovery-codes',
      method: 'post',
      data: { password },
    }),

  /** 登入第二关 */
  challenge: (code: string) =>
    request<{ user: import('@/types').User; message: string }>({
      url: '/auth/two-factor-challenge',
      method: 'post',
      data: { code },
    }),
}
