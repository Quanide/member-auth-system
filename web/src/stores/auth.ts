import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { authApi, type LoginPayload, type RegisterPayload } from '@/api/auth'
import { ApiError, resetCsrfCookie } from '@/api/client'
import type { User } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  /** 首次进站是否已尝试过恢复登入态，避免路由守卫重复请求 */
  const initialized = ref(false)
  const loading = ref(false)

  const isAuthenticated = computed(() => user.value !== null)
  const emailVerified = computed(() => user.value?.email_verified === true)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const twoFactorEnabled = computed(() => user.value?.two_factor_enabled === true)

  /** 应用启动时用 Cookie 换回当前用户；401 属预期结果，不当错误处理 */
  async function bootstrap(): Promise<void> {
    if (initialized.value) return

    try {
      const { user: me } = await authApi.me()
      user.value = me
    } catch (error) {
      if (!(error instanceof ApiError) || error.status !== 401) {
        console.error('恢复登入状态失败', error)
      }
      user.value = null
    } finally {
      initialized.value = true
    }
  }

  async function login(payload: LoginPayload): Promise<void> {
    loading.value = true
    try {
      const { user: me } = await authApi.login(payload)
      user.value = me
      initialized.value = true
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
      // 无论后端是否成功，前端一律清空本地状态
      user.value = null
      resetCsrfCookie()
    }
  }

  /** 资料更新后同步 store，避免各页面各自维护一份副本 */
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
