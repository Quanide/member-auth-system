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
    meta: { guestOnly: true, title: '注册' },
  },
  {
    path: '/forgot-password',
    name: 'forgot-password',
    component: () => import('@/views/auth/ForgotPasswordView.vue'),
    meta: { guestOnly: true, title: '忘记密码' },
  },
  {
    path: '/reset-password',
    name: 'reset-password',
    component: () => import('@/views/auth/ResetPasswordView.vue'),
    meta: { guestOnly: true, title: '重设密码' },
  },
  {
    path: '/verify-email',
    name: 'verify-email',
    component: () => import('@/views/auth/VerifyEmailView.vue'),
    meta: { title: '邮箱验证' },
  },
  {
    path: '/email-change/confirm',
    name: 'email-change-confirm',
    component: () => import('@/views/auth/EmailChangeConfirmView.vue'),
    meta: { title: '确认变更邮箱' },
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
        meta: { title: '会员总览' },
      },
      {
        path: 'profile',
        name: 'profile',
        component: () => import('@/views/profile/IndexView.vue'),
        meta: { title: '会员资料' },
      },
      {
        path: 'security',
        name: 'security',
        component: () => import('@/views/security/IndexView.vue'),
        meta: { title: '帐号安全' },
      },
      {
        path: 'activity',
        name: 'activity',
        component: () => import('@/views/activity/IndexView.vue'),
        meta: { title: '操作纪录' },
      },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/views/error/NotFoundView.vue'),
    meta: { title: '页面不存在' },
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

  // 只在首次导航时打一次 /api/me 恢复登入态
  if (!auth.initialized) {
    await auth.bootstrap()
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    // 记下原本要去的页面，登入后直接跳回去
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }

  return true
})

router.afterEach((to) => {
  NProgress.done()

  const appName = import.meta.env.VITE_APP_NAME || '会员中心'
  document.title = to.meta.title ? `${to.meta.title} · ${appName}` : appName
})

export default router
