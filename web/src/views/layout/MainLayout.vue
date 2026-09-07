<template>
  <div class="main-layout">
    <!-- ── 側邊欄 ─────────────────────────────────── -->
    <aside class="sidebar" :class="{ collapsed: isCollapsed, 'mobile-open': mobileOpen }">
      <div class="sidebar-glow" aria-hidden="true"></div>
      <div class="sidebar-noise" aria-hidden="true"></div>

      <div class="logo-area">
        <div class="logo-icon-wrap">
          <svg width="30" height="30" viewBox="0 0 30 30" fill="none">
            <defs>
              <linearGradient id="sideLogoGrad" x1="0" y1="0" x2="30" y2="30" gradientUnits="userSpaceOnUse">
                <stop offset="0%" stop-color="#6366F1" />
                <stop offset="100%" stop-color="#8B5CF6" />
              </linearGradient>
            </defs>
            <rect width="30" height="30" rx="8" fill="url(#sideLogoGrad)" />
            <circle cx="15" cy="11.5" r="4.6" fill="white" opacity="0.95" />
            <path d="M6.8 24 C6.8 18.8 11.2 16.4 15 16.4 C18.8 16.4 23.2 18.8 23.2 24" fill="white" opacity="0.95" />
          </svg>
        </div>
        <transition name="logo-text">
          <div v-show="!isCollapsed" class="logo-name-wrap">
            <span class="logo-name">會員<em>中心</em></span>
            <span class="logo-sub">MEMBER CENTER</span>
          </div>
        </transition>
      </div>

      <nav class="menu-wrap">
        <SideMenu :collapsed="isCollapsed" @navigate="mobileOpen = false" />
      </nav>

      <div class="collapse-trigger" @click="isCollapsed = !isCollapsed">
        <div class="collapse-icon-ring">
          <ChevronLeft :size="14" :stroke-width="2.2" class="collapse-icon" :class="{ rotated: isCollapsed }" />
        </div>
      </div>
    </aside>

    <div v-if="mobileOpen" class="sidebar-overlay" @click="mobileOpen = false" />

    <!-- ── 主體 ───────────────────────────────────── -->
    <div class="main-body">
      <header class="topbar">
        <div class="topbar-left">
          <button class="mobile-menu-btn" aria-label="開啟選單" @click="mobileOpen = !mobileOpen">
            <Menu :size="20" :stroke-width="2" />
          </button>

          <div class="topbar-chip">
            <Globe :size="14" :stroke-width="1.75" class="chip-icon" />
            <span class="chip-value">{{ auth.user?.last_login_ip ?? '—' }}</span>
          </div>

          <div class="topbar-divider" aria-hidden="true"></div>

          <div class="topbar-chip">
            <Clock :size="14" :stroke-width="1.75" class="chip-icon" />
            <span class="chip-date">{{ clockDate }}</span>
            <span class="chip-value">{{ clockTime }}</span>
          </div>
        </div>

        <div class="topbar-right">
          <!-- 信箱未驗證時的常駐提醒 -->
          <button v-if="!auth.emailVerified" class="verify-btn" :disabled="resending" @click="resendVerification">
            <MailWarning :size="13" :stroke-width="1.75" />
            {{ resending ? '寄送中…' : '信箱待驗證' }}
          </button>

          <el-dropdown trigger="click" @command="handleCommand">
            <div class="user-info">
              <el-avatar :size="28" :src="auth.user?.avatar_url ?? undefined" class="user-avatar">
                {{ initial }}
              </el-avatar>
              <span class="user-name">{{ auth.user?.display_name }}</span>
              <ChevronDown :size="12" :stroke-width="2" class="user-caret" />
            </div>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item command="profile">
                  <User :size="14" :stroke-width="1.75" /> 會員資料
                </el-dropdown-item>
                <el-dropdown-item command="security">
                  <Shield :size="14" :stroke-width="1.75" /> 帳號安全
                </el-dropdown-item>
                <el-dropdown-item command="logout" divided>
                  <LogOut :size="14" :stroke-width="1.75" /> 登出
                </el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>
      </header>

      <main class="content-area">
        <!-- 内层限制最大宽度：超宽萤幕下内容才不会被拉散成一条 -->
        <div class="content-inner">
          <router-view v-slot="{ Component, route }">
            <transition name="page" mode="out-in">
              <component :is="Component" :key="route.fullPath" />
            </transition>
          </router-view>
        </div>
      </main>

      <footer class="app-footer">
        <span>會員登入系統 · Laravel {{ laravelVersion }} + Vue 3</span>
        <span class="footer-dot">·</span>
        <span>{{ new Date().getFullYear() }}</span>
      </footer>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  ChevronDown,
  ChevronLeft,
  Clock,
  Globe,
  LogOut,
  MailWarning,
  Menu,
  Shield,
  User,
} from 'lucide-vue-next'
import SideMenu from '@/components/SideMenu.vue'
import { authApi } from '@/api/auth'
import { ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

const laravelVersion = '13'
const isCollapsed = ref(false)
const mobileOpen = ref(false)
const resending = ref(false)

const clockDate = ref('')
const clockTime = ref('')
let timer: ReturnType<typeof setInterval> | undefined

const initial = computed(() => auth.user?.display_name?.charAt(0)?.toUpperCase() ?? 'U')

function tick(): void {
  const now = new Date()
  clockDate.value = `${now.getMonth() + 1}/${now.getDate()}`
  clockTime.value = now.toLocaleTimeString('zh-CN', { hour12: false })
}

onMounted(() => {
  tick()
  timer = setInterval(tick, 1000)
})

onBeforeUnmount(() => {
  if (timer) clearInterval(timer)
})

async function resendVerification(): Promise<void> {
  resending.value = true

  try {
    const { message } = await authApi.resendVerification()
    ElMessage.success(message)
  } catch (error) {
    ElMessage.error(error instanceof ApiError ? error.message : '寄送失敗，請稍後再試')
  } finally {
    resending.value = false
  }
}

async function handleCommand(command: string): Promise<void> {
  if (command === 'logout') {
    try {
      await ElMessageBox.confirm('確定要登出嗎？', '登出確認', {
        confirmButtonText: '登出',
        cancelButtonText: '取消',
        type: 'warning',
      })
    } catch {
      return // 使用者取消
    }

    await auth.logout()
    ElMessage.success('已登出')
    router.push({ name: 'login' })

    return
  }

  router.push({ name: command })
}
</script>

<style scoped lang="scss">
@use '@/styles/tokens.scss' as *;

.main-layout {
  display: flex;
  height: 100vh;
  height: 100dvh;
  background: $bg-page;
  overflow: hidden;
}

// ── 側邊欄 ─────────────────────────────────────────
.sidebar {
  position: relative;
  display: flex;
  flex-direction: column;
  width: $sidebar-width;
  min-width: $sidebar-width;
  background: $sidebar-bg;
  border-right: 1px solid $sidebar-border;
  box-shadow: 2px 0 20px rgba(0, 0, 0, 0.35);
  transition:
    width 0.28s cubic-bezier(0.4, 0, 0.2, 1),
    min-width 0.28s cubic-bezier(0.4, 0, 0.2, 1);
  z-index: 10;
  overflow: hidden;
}

.sidebar.collapsed {
  width: $sidebar-collapsed;
  min-width: $sidebar-collapsed;
}

.sidebar-glow {
  position: absolute;
  top: -80px;
  left: -40px;
  width: 200px;
  height: 200px;
  background: radial-gradient(ellipse at center, rgba(99, 102, 241, 0.12) 0%, transparent 70%);
  pointer-events: none;
}

.sidebar-noise {
  position: absolute;
  bottom: -40px;
  right: -30px;
  width: 160px;
  height: 160px;
  background: radial-gradient(ellipse at center, rgba(139, 92, 246, 0.07) 0%, transparent 70%);
  pointer-events: none;
}

.logo-area {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 0 16px;
  height: $topbar-height;
  border-bottom: 1px solid $sidebar-border;
  overflow: hidden;
  flex-shrink: 0;
}

.logo-icon-wrap {
  flex-shrink: 0;
  transition: transform 0.3s ease;

  &:hover {
    transform: scale(1.08) rotate(-4deg);
  }
}

.logo-name-wrap {
  display: flex;
  flex-direction: column;
  gap: 5px;
  white-space: nowrap;
}

.logo-name {
  font-size: 15px;
  font-weight: 700;
  color: rgba(255, 255, 255, 0.92);
  line-height: 1;

  em {
    font-style: normal;
    background: linear-gradient(135deg, #818cf8, #a78bfa);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    font-weight: 800;
  }
}

.logo-sub {
  font-size: 10px;
  color: rgba(255, 255, 255, 0.28);
  letter-spacing: 0.08em;
  line-height: 1;
}

.logo-text-enter-active,
.logo-text-leave-active {
  transition:
    opacity 0.18s,
    transform 0.18s;
}

.logo-text-enter-from,
.logo-text-leave-to {
  opacity: 0;
  transform: translateX(-8px);
}

.menu-wrap {
  position: relative;
  z-index: 1;
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 6px 0;
  scrollbar-width: none;

  &::-webkit-scrollbar {
    width: 3px;
  }

  &::-webkit-scrollbar-thumb {
    background: transparent;
    border-radius: 3px;
  }

  &:hover::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.18);
  }
}

.collapse-trigger {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  height: 46px;
  cursor: pointer;
  border-top: 1px solid $sidebar-border;
  transition: background 0.18s;

  &:hover {
    background: rgba(255, 255, 255, 0.05);
  }
}

.collapse-icon-ring {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 26px;
  height: 26px;
  border-radius: 7px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  color: rgba(255, 255, 255, 0.3);
  transition: all 0.2s;

  .collapse-trigger:hover & {
    border-color: rgba(99, 102, 241, 0.5);
    color: #a78bfa;
    background: rgba(99, 102, 241, 0.12);
  }
}

.collapse-icon {
  transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1);

  &.rotated {
    transform: rotate(180deg);
  }
}

.sidebar-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  z-index: 9;
}

// ── 主體 ───────────────────────────────────────────
.main-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
  background: $bg-page;
}

.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: $topbar-height;
  padding: 0 24px;
  background: rgba(255, 255, 255, 0.96);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid rgba(99, 102, 241, 0.08);
  box-shadow:
    0 1px 0 rgba(99, 102, 241, 0.06),
    0 4px 16px rgba(0, 0, 0, 0.04);
  flex-shrink: 0;
}

.topbar-left,
.topbar-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.mobile-menu-btn {
  display: none;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border: none;
  border-radius: $radius-sm;
  background: transparent;
  color: $text-body;
  cursor: pointer;

  &:hover {
    background: $bg-tint;
  }
}

.topbar-chip {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  background: $bg-tint;
  border: 1px solid $border-tint;
  border-radius: $radius-sm;
  white-space: nowrap;
}

.chip-icon {
  color: $brand-primary;
  flex-shrink: 0;
}

.chip-date {
  font-size: 12.5px;
  line-height: 1;
  color: rgba(99, 102, 241, 0.7);
}

.chip-value {
  font-size: 14px;
  font-weight: 650;
  line-height: 1;
  background: $brand-gradient;
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
  font-variant-numeric: tabular-nums;
}

.topbar-divider {
  width: 1px;
  height: 20px;
  background: rgba(99, 102, 241, 0.15);
  flex-shrink: 0;
}

.verify-btn {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 5px 12px;
  background: #fff7ed;
  border: 1px solid #fed7aa;
  border-radius: $radius-sm;
  font-size: 12px;
  color: #ea580c;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.18s;

  &:hover:not(:disabled) {
    background: #ffedd5;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(234, 88, 12, 0.15);
  }

  &:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
}

.user-info {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 4px 8px 4px 4px;
  border-radius: $radius-sm;
  cursor: pointer;
  transition: background 0.18s;

  &:hover {
    background: $bg-tint;
  }
}

.user-avatar {
  background: $brand-gradient !important;
  color: #fff;
  font-weight: 600;
  flex-shrink: 0;
}

.user-name {
  font-size: 13.5px;
  color: $text-body;
  max-width: 140px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.user-caret {
  color: $text-faint;
  flex-shrink: 0;
}

.content-area {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
}

.content-inner {
  max-width: 1440px;
  margin: 0 auto;
}

.app-footer {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  height: 42px;
  border-top: 1px solid rgba(99, 102, 241, 0.07);
  font-size: 12px;
  color: $text-faint;
  flex-shrink: 0;
}

.footer-dot {
  opacity: 0.6;
}

// ── 響應式 ─────────────────────────────────────────
@media (max-width: 768px) {
  .sidebar {
    position: fixed;
    top: 0;
    bottom: 0;
    left: 0;
    transform: translateX(-100%);
    transition: transform 0.28s cubic-bezier(0.4, 0, 0.2, 1);

    &.mobile-open {
      transform: translateX(0);
    }
  }

  .mobile-menu-btn {
    display: flex;
  }

  .topbar {
    padding: 0 14px;
  }

  .topbar-chip,
  .topbar-divider {
    display: none;
  }

  .content-area {
    padding: 16px;
  }

  .user-name {
    display: none;
  }
}
</style>
