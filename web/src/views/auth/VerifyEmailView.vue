<template>
  <AuthShell title="信箱驗證" narrow>
    <div v-if="state === 'pending'" class="state-box">
      <el-icon class="is-loading spin" :size="34"><Loading /></el-icon>
      <p class="state-text">正在驗證您的信箱…</p>
    </div>

    <el-result v-else-if="state === 'success'" icon="success" title="驗證成功">
      <template #sub-title>
        <p class="state-desc">您的信箱已完成驗證，現在可以使用完整功能。</p>
      </template>
      <template #extra>
        <el-button type="primary" @click="goHome">進入會員中心</el-button>
      </template>
    </el-result>

    <el-result v-else icon="error" title="驗證失敗">
      <template #sub-title>
        <p class="state-desc">{{ errorMessage }}</p>
      </template>
      <template #extra>
        <el-button @click="goHome">返回會員中心</el-button>
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
const errorMessage = ref('驗證連結無效或已過期，請重新申請驗證信。')

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
    errorMessage.value = '連結缺少必要參數，請重新從郵件中點擊驗證連結。'

    return
  }

  try {
    await authApi.verifyEmail({ id, hash, expires, signature })

    // 驗證後同步登入態裡的 email_verified，頁面上的提示條才會消失
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
