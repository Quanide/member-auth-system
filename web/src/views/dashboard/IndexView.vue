<template>
  <div v-loading="loading">
    <h1 class="page-title">会员总览</h1>
    <p class="page-subtitle">您的帐号状态与近期活动一览</p>

    <!-- 邮箱未验证提示 -->
    <el-alert
      v-if="overview && !overview.security.email_verified"
      class="verify-alert"
      type="warning"
      :closable="false"
      show-icon
      title="邮箱尚未验证"
    >
      <template #default>
        <div class="alert-body">
          <span>验证后才能变更邮箱等敏感操作。没收到信？可以重新寄送。</span>
          <el-button size="small" type="warning" plain :loading="resending" @click="resend">
            重寄验证信
          </el-button>
        </div>
      </template>
    </el-alert>

    <!-- 统计卡片 -->
    <div class="stat-grid">
      <div v-for="stat in stats" :key="stat.label" class="stat-card">
        <div class="stat-icon" :style="{ background: stat.bg, color: stat.color }">
          <component :is="stat.icon" :size="19" :stroke-width="1.9" />
        </div>
        <div class="stat-body">
          <div class="stat-value">{{ stat.value }}</div>
          <div class="stat-label">{{ stat.label }}</div>
        </div>
      </div>
    </div>

    <div class="grid-2">
      <!-- 资料完整度 -->
      <el-card shadow="never">
        <template #header>资料完整度</template>

        <div class="completeness">
          <el-progress
            type="dashboard"
            :percentage="overview?.security.profile_completeness ?? 0"
            :color="progressColor"
            :width="128"
          />
          <div class="completeness-hint">
            <p v-if="(overview?.security.profile_completeness ?? 0) >= 100" class="hint-good">
              资料已填写完整，很棒！
            </p>
            <template v-else>
              <p class="hint-text">补全以下资料让帐号更完整：</p>
              <ul class="missing-list">
                <li v-for="field in missingFields" :key="field">{{ field }}</li>
              </ul>
              <el-button type="primary" size="small" @click="$router.push('/profile')">
                前往完善
              </el-button>
            </template>
          </div>
        </div>
      </el-card>

      <!-- 近期登入 -->
      <el-card shadow="never">
        <template #header>
          <div class="card-header-row">
            <span>近期登入</span>
            <el-button link type="primary" size="small" @click="$router.push('/activity')">
              查看全部
            </el-button>
          </div>
        </template>

        <el-empty v-if="!overview?.recent_logins.length" description="暂无登入纪录" :image-size="70" />

        <ul v-else class="login-list">
          <li v-for="log in overview.recent_logins" :key="log.id" class="login-item">
            <span class="login-dot"></span>
            <div class="login-main">
              <span class="login-time">{{ formatDateTime(log.created_at) }}</span>
              <span class="login-meta">{{ log.ip_address ?? '未知 IP' }}</span>
            </div>
          </li>
        </ul>
      </el-card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { CalendarDays, MonitorSmartphone, ShieldAlert, ScrollText } from 'lucide-vue-next'
import { profileApi } from '@/api/profile'
import { authApi } from '@/api/auth'
import { ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { formatDateTime } from '@/utils/format'
import type { Overview } from '@/types'

const auth = useAuthStore()

const overview = ref<Overview | null>(null)
const loading = ref(true)
const resending = ref(false)

const stats = computed(() => [
  {
    label: '登入中的装置',
    value: overview.value?.stats.active_sessions ?? 0,
    icon: MonitorSmartphone,
    bg: '#EFF0FE',
    color: '#6366F1',
  },
  {
    label: '近 7 天登入失败',
    value: overview.value?.stats.failed_logins_7d ?? 0,
    icon: ShieldAlert,
    bg: '#FFF1F0',
    color: '#F53F3F',
  },
  {
    label: '操作纪录总数',
    value: overview.value?.stats.total_activities ?? 0,
    icon: ScrollText,
    bg: '#F0FDF4',
    color: '#16A34A',
  },
  {
    label: '帐号已建立（天）',
    value: overview.value?.stats.account_age_days ?? 0,
    icon: CalendarDays,
    bg: '#FFF7ED',
    color: '#EA580C',
  },
])

const progressColor = computed(() => {
  const value = overview.value?.security.profile_completeness ?? 0
  if (value >= 100) return '#00B42A'
  if (value >= 60) return '#6366F1'

  return '#FF7D00'
})

/** 提示用户还差哪些资料没填 */
const missingFields = computed(() => {
  const user = overview.value?.user
  if (!user) return []

  const checks: Array<[string, unknown]> = [
    ['昵称', user.nickname],
    ['手机号码', user.phone],
    ['生日', user.birthday],
    ['性别', user.gender],
    ['个人简介', user.bio],
    ['头像', user.avatar_url],
  ]

  return checks.filter(([, value]) => !value).map(([label]) => label)
})

async function load(): Promise<void> {
  loading.value = true

  try {
    overview.value = await profileApi.overview()
    auth.setUser(overview.value.user)
  } catch (error) {
    ElMessage.error(error instanceof ApiError ? error.message : '载入失败')
  } finally {
    loading.value = false
  }
}

async function resend(): Promise<void> {
  resending.value = true

  try {
    const { message } = await authApi.resendVerification()
    ElMessage.success(message)
  } catch (error) {
    ElMessage.error(error instanceof ApiError ? error.message : '寄送失败')
  } finally {
    resending.value = false
  }
}

onMounted(load)
</script>

<style scoped lang="scss">
@use '@/styles/tokens.scss' as *;

.verify-alert {
  margin-bottom: 18px;
  border-radius: $radius-md;
}

.alert-body {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.stat-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 14px;
  margin-bottom: 18px;
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 13px;
  padding: 17px 18px;
  background: $bg-card;
  border: 1px solid $border-base;
  border-radius: $radius-md;
  box-shadow: $shadow-card;
  transition:
    transform 0.18s,
    box-shadow 0.18s;

  &:hover {
    transform: translateY(-2px);
    box-shadow: $shadow-float;
  }
}

.stat-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: $radius-md;
  flex-shrink: 0;
}

.stat-value {
  font-size: 22px;
  font-weight: 650;
  line-height: 1.2;
  color: $text-title;
  font-variant-numeric: tabular-nums;
}

.stat-label {
  margin-top: 3px;
  font-size: 12.5px;
  color: $text-muted;
}

.grid-2 {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 14px;
}

.card-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.completeness {
  display: flex;
  align-items: center;
  gap: 24px;
  flex-wrap: wrap;
}

.completeness-hint {
  flex: 1;
  min-width: 160px;
}

.hint-good {
  margin: 0;
  font-size: 13.5px;
  color: $success;
}

.hint-text {
  margin: 0 0 8px;
  font-size: 13px;
  color: $text-muted;
}

.missing-list {
  margin: 0 0 14px;
  padding-left: 18px;
  font-size: 13px;
  color: $text-body;
  line-height: 1.9;
}

.login-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.login-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 0;
  border-bottom: 1px solid $border-light;

  &:last-child {
    border-bottom: none;
  }
}

.login-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: $brand-gradient;
  flex-shrink: 0;
}

.login-main {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex: 1;
  gap: 12px;
  min-width: 0;
}

.login-time {
  font-size: 13px;
  color: $text-body;
}

.login-meta {
  font-size: 12px;
  color: $text-muted;
  font-variant-numeric: tabular-nums;
}
</style>
