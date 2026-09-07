import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { authApi, type LoginPayload, type RegisterPayload } from '@/api/auth'
import { ApiError, resetCsrfCookie } from '@/api/client'
import type { User } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  /** 首次進站是否已嘗試過恢復登入態，避免路由守衛重複請求 */
  const initialized = ref(false)
  const loading = ref(false)

  const isAuthenticated = computed(() => user.value !== null)
  const emailVerified = computed(() => user.value?.email_verified === true)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const twoFactorEnabled = computed(() => user.value?.two_factor_enabled === true)

  /** 應用啟動時用 Cookie 換回當前使用者；401 屬預期結果，不當錯誤處理 */
  async function bootstrap(): Promise<void> {
    if (initialized.value) return

    try {
      const { user: me } = await authApi.me()
      user.value = me
    } catch (error) {
      if (!(error instanceof ApiError) || error.status !== 401) {
        console.error('恢復登入狀態失敗', error)
      }
      user.value = null
    } finally {
      initialized.value = true
    }
  }

  /**
   * 登入。
   * 啟用 2FA 的帳號在這一步不會建立完整登入狀態，
   * 回傳 twoFactorRequired 讓頁面切到驗證碼那一關。
   */
  async function login(payload: LoginPayload): Promise<{ twoFactorRequired: boolean }> {
    loading.value = true

    try {
      const result = await authApi.login(payload)

      if (result.two_factor_required) {
        return { twoFactorRequired: true }
      }

      user.value = result.user
      initialized.value = true

      return { twoFactorRequired: false }
    } finally {
      loading.value = false
    }
  }

  async function register(payload: RegisterPayload): Promise<void> {
    loading.value = true
    try {
      const { user: me } = await authApi.register(payload)
      user.value = me
      initialized.value = true
    } finally {
      loading.value = false
    }
  }

  async function logout(): Promise<void> {
    try {
      await authApi.logout()
    } finally {
      // 無論後端是否成功，前端一律清空本地狀態
      user.value = null
      resetCsrfCookie()
    }
  }

  /** 資料更新後同步 store，避免各頁面各自維護一份副本 */
  function setUser(next: User): void {
    user.value = next
  }

  async function refresh(): Promise<void> {
    const { user: me } = await authApi.me()
    user.value = me
  }

  return {
    user,
    initialized,
    loading,
    isAuthenticated,
    emailVerified,
    isAdmin,
    twoFactorEnabled,
    bootstrap,
    login,
    register,
    logout,
    setUser,
    refresh,
  }
})
