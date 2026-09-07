<template>
  <AuthShell title="變更信箱" narrow>
    <div v-if="state === 'pending'" class="state-box">
      <el-icon class="is-loading spin" :size="34"><Loading /></el-icon>
      <p class="state-text">正在確認您的新信箱…</p>
    </div>

    <el-result v-else-if="state === 'success'" icon="success" title="信箱變更成功">
      <template #sub-title>
        <p class="state-desc">
          您的帳號信箱已更新為 <strong>{{ newEmail }}</strong>，往後請使用新信箱登入。
        </p>
      </template>
      <template #extra>
        <el-button type="primary" @click="$router.push('/login')">前往登入</el-button>
      </template>
    </el-result>

    <el-result v-else icon="error" title="變更失敗">
      <template #sub-title>
        <p class="state-desc">{{ errorMessage }}</p>
      </template>
      <template #extra>
        <el-button @click="$router.push('/security')">返回帳號安全</el-button>
      </template>
    </el-result>
  </AuthShell>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { Loading } from '@element-plus/icons-vue'
import AuthShell from '@/components/AuthShell.vue'
import { authApi } from '@/api/auth'
import { ApiError } from '@/api/client'

const route = useRoute()

const state = ref<'pending' | 'success' | 'error'>('pending')
const newEmail = ref('')
const errorMessage = ref('驗證連結無效或已過期，請重新申請。')

onMounted(async () => {
  const token = route.query.token

  if (typeof token !== 'string' || token === '') {
    state.value = 'error'
    errorMessage.value = '連結缺少驗證參數，請重新從郵件中點擊連結。'

    return
  }

  try {
    const { user } = await authApi.confirmEmailChange(token)
    newEmail.value = user.email
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
