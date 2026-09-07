import { request } from './client'

export const twoFactorApi = {
  /** 產生密鑰與 QR Code，此時尚未啟用 */
  generate: () =>
    request<{ secret: string; qr_code: string; otpauth_url: string }>({
      url: '/me/two-factor/generate',
      method: 'post',
    }),

  /** 輸入驗證碼確認綁定，回傳一次性恢復碼 */
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

  /** 登入第二關 */
  challenge: (code: string) =>
    request<{ user: import('@/types').User; message: string }>({
      url: '/auth/two-factor-challenge',
      method: 'post',
      data: { code },
    }),
}
