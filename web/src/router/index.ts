import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import NProgress from 'nprogress'
import { useAuthStore } from '@/stores/auth'

const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/auth/LoginView.vue'),
    meta: { guestOnly: true, title: '登入' },
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('@/views/auth/RegisterView.vue'),
    meta: { guestOnly: true, title: '註冊' },
  },
  {
    path: '/forgot-password',
    name: 'forgot-password',
    component: () => import('@/views/auth/ForgotPasswordView.vue'),
    meta: { guestOnly: true, title: '忘記密碼' },
  },
  {
    path: '/reset-password',
    name: 'reset-password',
    component: () => import('@/views/auth/ResetPasswordView.vue'),
    meta: { guestOnly: true, title: '重設密碼' },
  },
  {
    path: '/verify-email',
    name: 'verify-email',
    component: () => import('@/views/auth/VerifyEmailView.vue'),
    meta: { title: '信箱驗證' },
  },
  {
    path: '/email-change/confirm',
    name: 'email-change-confirm',
    component: () => import('@/views/auth/EmailChangeConfirmView.vue'),
    meta: { title: '確認變更信箱' },
  },
  {
    path: '/',
    component: () => import('@/views/layout/MainLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'dashboard',
        component: () => import('@/views/dashboard/IndexView.vue'),
        meta: { title: '會員總覽' },
      },
      {
        path: 'profile',
        name: 'profile',
        component: () => import('@/views/profile/IndexView.vue'),
        meta: { title: '會員資料' },
      },
      {
        path: 'security',
        name: 'security',
        component: () => import('@/views/security/IndexView.vue'),
        meta: { title: '帳號安全' },
      },
      {
        path: 'activity',
        name: 'activity',
        component: () => import('@/views/activity/IndexView.vue'),
        meta: { title: '操作紀錄' },
      },

      // ── 管理端：路由守衛擋一層，後端 middleware 再擋一層 ──
      {
        path: 'admin/dashboard',
        name: 'admin-dashboard',
        component: () => import('@/views/admin/DashboardView.vue'),
        meta: { title: '數據看板', requiresAdmin: true },
      },
      {
        path: 'admin/users',
        name: 'admin-users',
        component: () => import('@/views/admin/UsersView.vue'),
        meta: { title: '會員管理', requiresAdmin: true },
      },
      {
        path: 'admin/audit-logs',
        name: 'admin-audit-logs',
        component: () => import('@/views/admin/AuditLogsView.vue'),
        meta: { title: '全站稽核', requiresAdmin: true },
      },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/views/error/NotFoundView.vue'),
    meta: { title: '頁面不存在' },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

NProgress.configure({ showSpinner: false })

router.beforeEach(async (to) => {
  NProgress.start()

  const auth = useAuthStore()

  // 只在首次導航時打一次 /api/me 恢復登入態
  if (!auth.initialized) {
    await auth.bootstrap()
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    // 記下原本要去的頁面，登入後直接跳回去
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }

  // 前端守衛只是體驗優化，真正的權限邊界在後端 middleware
  if (to.meta.requiresAdmin && !auth.isAdmin) {
    return { name: 'dashboard' }
  }

  return true
})

router.afterEach((to) => {
  NProgress.done()

  const appName = import.meta.env.VITE_APP_NAME || '會員中心'
  document.title = to.meta.title ? `${to.meta.title} · ${appName}` : appName
})

export default router
