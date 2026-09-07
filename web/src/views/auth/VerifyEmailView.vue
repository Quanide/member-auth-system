<template>
  <AuthShell title="邮箱验证" narrow>
    <div v-if="state === 'pending'" class="state-box">
      <el-icon class="is-loading spin" :size="34"><Loading /></el-icon>
      <p class="state-text">正在验证您的邮箱…</p>
    </div>

    <el-result v-else-if="state === 'success'" icon="success" title="验证成功">
      <template #sub-title>
        <p class="state-desc">您的邮箱已完成验证，现在可以使用完整功能。</p>
      </template>
      <template #extra>
        <el-button type="primary" @click="goHome">进入会员中心</el-button>
      </template>
    </el-result>

    <el-result v-else icon="error" title="验证失败">
      <template #sub-title>
        <p class="state-desc">{{ errorMessage }}</p>
      </template>
      <template #extra>
        <el-button @click="goHome">返回会员中心</el-button>
      </template>
    </el-result>
  </AuthShell>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Loading } from '@element-plus/icons-vue'
import AuthShell from '@/components/AuthShell.vue'
import { authApi } from '@/api/auth'
import { ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const state = ref<'pending' | 'success' | 'error'>('pending')
const errorMessage = ref('验证连结无效或已过期，请重新申请验证信。')

function goHome(): void {
  router.push(auth.isAuthenticated ? { name: 'dashboard' } : { name: 'login' })
}

onMounted(async () => {
  const { id, hash, expires, signature } = route.query

  if (
    typeof id !== 'string' ||
    typeof hash !== 'string' ||
    typeof expires !== 'string' ||
    typeof signature !== 'string'
  ) {
    state.value = 'error'
    errorMessage.value = '连结缺少必要参数，请重新从邮件中点击验证连结。'

    return
  }

  try {
    await authApi.verifyEmail({ id, hash, expires, signature })

    // 验证后同步登入态里的 email_verified，页面上的提示条才会消失
    if (auth.isAuthenticated) {
      await auth.refresh().catch(() => undefined)
    }

    state.value = 'success'
  } catch (error) {
    state.value = 'error'

    if (error instanceof ApiError) {
      errorMessage.value = error.message
    }
  }
})
</script>

<style scoped lang="scss">
.state-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
  padding: 28px 0;
}

.spin {
  color: #6366f1;
}

.state-text {
  margin: 0;
  font-size: 14px;
  color: #4e5969;
}

.state-desc {
  font-size: 13px;
  color: #86909c;
  line-height: 1.7;
}
</style>
