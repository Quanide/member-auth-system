<template>
  <div class="side-nav">
    <template v-for="section in sections" :key="section.label">
      <div class="nav-section" :class="{ 'ns-collapsed': collapsed }">
        <span v-if="!collapsed" class="ns-label">{{ section.label }}</span>
        <span class="ns-line"></span>
      </div>

      <router-link
        v-for="item in section.items"
        :key="item.name"
        :to="{ name: item.name }"
        custom
        v-slot="{ navigate, isExactActive }"
      >
        <div
          class="nav-item"
          :class="{ active: isExactActive }"
          :title="collapsed ? item.label : undefined"
          @click="handleClick(navigate)"
        >
          <span class="nav-icon">
            <component :is="item.icon" :size="18" :stroke-width="1.75" />
          </span>
          <span v-if="!collapsed" class="nav-label">{{ item.label }}</span>
        </div>
      </router-link>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, type Component } from 'vue'
import {
  Activity,
  BarChart3,
  LayoutDashboard,
  ScrollText,
  Shield,
  UserCog,
  Users,
} from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'

defineProps<{ collapsed: boolean }>()

const auth = useAuthStore()

const emit = defineEmits<{ navigate: [] }>()

interface MenuItem {
  name: string
  label: string
  icon: Component
}

// 管理端選單只對 admin 顯示；這是體驗層的處理，
// 一般會員就算手動打 URL，後端 middleware 一樣會擋下來。
const sections = computed<Array<{ label: string; items: MenuItem[] }>>(() => {
  const base = [
    {
      label: '總覽',
      items: [{ name: 'dashboard', label: '會員總覽', icon: LayoutDashboard }],
    },
    {
      label: '帳號管理',
      items: [
        { name: 'profile', label: '會員資料', icon: UserCog },
        { name: 'security', label: '帳號安全', icon: Shield },
        { name: 'activity', label: '操作紀錄', icon: Activity },
      ],
    },
  ]

  if (auth.isAdmin) {
    base.push({
      label: '系統管理',
      items: [
        { name: 'admin-dashboard', label: '數據看板', icon: BarChart3 },
        { name: 'admin-users', label: '會員管理', icon: Users },
        { name: 'admin-audit-logs', label: '全站稽核', icon: ScrollText },
      ],
    })
  }

  return base
})

function handleClick(navigate: () => void): void {
  navigate()
  // 手機版點完自動收起抽屜
  emit('navigate')
}
</script>

<style scoped lang="scss">
.side-nav {
  padding: 6px 0 20px;
}

.nav-section {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 16px 14px 4px;

  &.ns-collapsed {
    padding: 12px 16px 4px;
    gap: 0;
  }
}

.ns-line {
  flex: 1;
  height: 1px;
  background: rgba(255, 255, 255, 0.08);
}

.ns-label {
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.7px;
  color: rgba(255, 255, 255, 0.22);
  white-space: nowrap;
  flex-shrink: 0;
}

.nav-item {
  position: relative;
  display: flex;
  align-items: center;
  gap: 10px;
  height: 42px;
  padding: 0 16px;
  cursor: pointer;
  color: rgba(255, 255, 255, 0.52);
  transition:
    background 0.14s,
    color 0.14s;
  user-select: none;
  overflow: hidden;

  // 左側激活豎條
  &::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%) scaleY(0);
    width: 3px;
    height: 22px;
    background: linear-gradient(180deg, #818cf8 0%, #6366f1 100%);
    border-radius: 0 3px 3px 0;
    transition: transform 0.22s cubic-bezier(0.4, 0, 0.2, 1);
  }

  &:hover {
    background: rgba(255, 255, 255, 0.06);
    color: rgba(255, 255, 255, 0.86);
  }

  &.active {
    background: rgba(99, 102, 241, 0.16);
    color: #fff;

    &::before {
      transform: translateY(-50%) scaleY(1);
    }
  }
}

.nav-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18px;
  flex-shrink: 0;
  color: inherit;
}

.nav-label {
  font-size: 13px;
  font-weight: 400;
  white-space: nowrap;
  flex: 1;
  color: inherit;
  letter-spacing: 0.1px;

  .nav-item.active & {
    font-weight: 500;
  }
}
</style>
