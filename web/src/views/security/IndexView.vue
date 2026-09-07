<template>
  <div>
    <h1 class="page-title">帐号安全</h1>
    <p class="page-subtitle">管理密码、登入邮箱与已登入的装置</p>

    <div class="security-grid">
      <!-- ── 修改密码 ───────────────────────────── -->
      <el-card shadow="never">
        <template #header>修改密码</template>

        <el-form
          ref="passwordFormRef"
          :model="passwordForm"
          :rules="passwordRules"
          label-position="top"
          @submit.prevent="changePassword"
        >
          <el-form-item label="目前密码" prop="current_password" :error="pwErrors.current_password">
            <el-input
              v-model="passwordForm.current_password"
              type="password"
              show-password
              autocomplete="current-password"
              placeholder="请输入目前密码"
              @input="clearPwError('current_password')"
            />
          </el-form-item>

          <el-form-item label="新密码" prop="password" :error="pwErrors.password">
            <el-input
              v-model="passwordForm.password"
              type="password"
              show-password
              autocomplete="new-password"
              placeholder="至少 8 位，需含英文字母与数字"
              @input="clearPwError('password')"
            />
            <PasswordStrength :password="passwordForm.password" />
          </el-form-item>

          <el-form-item label="确认新密码" prop="password_confirmation">
            <el-input
              v-model="passwordForm.password_confirmation"
              type="password"
              show-password
              autocomplete="new-password"
              placeholder="请再次输入新密码"
            />
          </el-form-item>

          <el-alert
            type="info"
            :closable="false"
            show-icon
            title="修改密码后，其他装置上的登入状态会被清除"
            class="tip-alert"
          />

          <el-button type="primary" :loading="pwSubmitting" @click="changePassword">
            更新密码
          </el-button>
        </el-form>
      </el-card>

      <!-- ── 变更邮箱 ───────────────────────────── -->
      <el-card shadow="never">
        <template #header>变更登入邮箱</template>

        <el-alert
          v-if="!auth.emailVerified"
          type="warning"
          :closable="false"
          show-icon
          title="请先完成目前邮箱的验证，才能申请变更"
          class="tip-alert"
        />

        <el-form
          ref="emailFormRef"
          :model="emailForm"
          :rules="emailRules"
          label-position="top"
          :disabled="!auth.emailVerified"
          @submit.prevent="requestEmailChange"
        >
          <el-form-item label="目前邮箱">
            <el-input :model-value="auth.user?.email" disabled />
          </el-form-item>

          <el-form-item label="新邮箱" prop="new_email" :error="emailErrors.new_email">
            <el-input
              v-model="emailForm.new_email"
              type="email"
              placeholder="new@example.com"
              @input="clearEmailError('new_email')"
            />
          </el-form-item>

          <el-form-item label="目前密码" prop="current_password" :error="emailErrors.current_password">
            <el-input
              v-model="emailForm.current_password"
              type="password"
              show-password
              placeholder="为确认身份，请输入目前密码"
              @input="clearEmailError('current_password')"
            />
          </el-form-item>

          <el-alert
            type="info"
            :closable="false"
            show-icon
            title="我们会寄验证信到新邮箱，点击连结后才会正式生效"
            class="tip-alert"
          />

          <el-button type="primary" :loading="emailSubmitting" @click="requestEmailChange">
            寄出验证信
          </el-button>
        </el-form>
      </el-card>

      <!-- ── 登入装置 ───────────────────────────── -->
      <el-card shadow="never" class="session-card" v-loading="sessionsLoading">
        <template #header>
          <div class="card-header-row">
            <span>登入中的装置</span>
            <el-button
              v-if="otherSessionCount > 0"
              link
              type="danger"
              size="small"
              @click="revokeOthers"
            >
              登出其他装置（{{ otherSessionCount }}）
            </el-button>
          </div>
        </template>

        <el-empty v-if="!sessions.length" description="没有登入纪录" :image-size="70" />

        <el-table v-else :data="sessions" size="small">
          <el-table-column label="装置" min-width="170">
            <template #default="{ row }">
              <div class="device-cell">
                <span>{{ row.device }}</span>
                <el-tag v-if="row.is_current" type="success" size="small">目前装置</el-tag>
              </div>
            </template>
          </el-table-column>

          <el-table-column prop="ip_address" label="IP" width="140" />

          <el-table-column label="最后活动" width="180">
            <template #default="{ row }">{{ formatDateTime(row.last_active_at) }}</template>
          </el-table-column>

          <el-table-column label="操作" width="90" align="right">
            <template #default="{ row }">
              <el-button
                v-if="!row.is_current"
                link
                type="danger"
                size="small"
                @click="revokeSession(row.id)"
              >
                登出
              </el-button>
              <span v-else class="text-faint">—</span>
            </template>
          </el-table-column>
        </el-table>
      </el-card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox, type FormInstance, type FormRules } from 'element-plus'
import PasswordStrength from '@/components/PasswordStrength.vue'
import { profileApi } from '@/api/profile'
import { ApiError } from '@/api/client'
import { useApiForm } from '@/composables/useApiForm'
import { useAuthStore } from '@/stores/auth'
import { formatDateTime } from '@/utils/format'
import type { LoginSession } from '@/types'

const auth = useAuthStore()

// 两个表单各自独立的提交状态，避免互相干扰
const {
  submitting: pwSubmitting,
  serverErrors: pwErrors,
  submit: submitPassword,
  clearFieldError: clearPwError,
} = useApiForm()

const {
  submitting: emailSubmitting,
  serverErrors: emailErrors,
  submit: submitEmail,
  clearFieldError: clearEmailError,
} = useApiForm()

const passwordFormRef = ref<FormInstance>()
const emailFormRef = ref<FormInstance>()

const passwordForm = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const emailForm = reactive({
  new_email: '',
  current_password: '',
})

const sessions = ref<LoginSession[]>([])
const sessionsLoading = ref(false)

const otherSessionCount = computed(() => sessions.value.filter((s) => !s.is_current).length)

const passwordRules: FormRules = {
  current_password: [{ required: true, message: '请输入目前密码', trigger: 'blur' }],
  password: [
    { required: true, message: '请输入新密码', trigger: 'blur' },
    { min: 8, message: '密码至少 8 个字符', trigger: 'blur' },
    {
      validator: (_rule, value: string, callback) => {
        if (!value) return callback()
        if (!/[a-zA-Z]/.test(value)) return callback(new Error('密码需包含英文字母'))
        if (!/\d/.test(value)) return callback(new Error('密码需包含数字'))
        if (value === passwordForm.current_password) {
          return callback(new Error('新密码不能与目前密码相同'))
        }
        callback()
      },
      trigger: 'blur',
    },
  ],
  password_confirmation: [
    { required: true, message: '请再次输入新密码', trigger: 'blur' },
    {
      validator: (_rule, value: string, callback) => {
        if (value !== passwordForm.password) return callback(new Error('两次输入的密码不一致'))
        callback()
      },
      trigger: 'blur',
    },
  ],
}

const emailRules: FormRules = {
  new_email: [
    { required: true, message: '请输入新邮箱', trigger: 'blur' },
    { type: 'email', message: '邮箱格式不正确', trigger: 'blur' },
    {
      validator: (_rule, value: string, callback) => {
        if (value && value === auth.user?.email) {
          return callback(new Error('新邮箱与目前邮箱相同'))
        }
        callback()
      },
      trigger: 'blur',
    },
  ],
  current_password: [{ required: true, message: '请输入目前密码', trigger: 'blur' }],
}

async function changePassword(): Promise<void> {
  await submitPassword(passwordFormRef.value, () => profileApi.changePassword({ ...passwordForm }), {
    successMessage: '密码已更新',
    onSuccess: () => {
      passwordFormRef.value?.resetFields()
      loadSessions()
    },
  })
}

async function requestEmailChange(): Promise<void> {
  await submitEmail(emailFormRef.value, () => profileApi.requestEmailChange({ ...emailForm }), {
    successMessage: '验证信已寄至新邮箱',
    onSuccess: () => emailFormRef.value?.resetFields(),
  })
}

async function loadSessions(): Promise<void> {
  sessionsLoading.value = true

  try {
    const { sessions: list } = await profileApi.sessions()
    sessions.value = list
  } catch (error) {
    ElMessage.error(error instanceof ApiError ? error.message : '载入登入纪录失败')
  } finally {
    sessionsLoading.value = false
  }
}

async function revokeSession(id: string): Promise<void> {
  try {
    await ElMessageBox.confirm('确定要登出这台装置吗？', '登出装置', {
      confirmButtonText: '登出',
      cancelButtonText: '取消',
      type: 'warning',
    })
  } catch {
    return
  }

  try {
    const { message } = await profileApi.revokeSession(id)
    ElMessage.success(message)
    await loadSessions()
  } catch (error) {
    ElMessage.error(error instanceof ApiError ? error.message : '操作失败')
  }
}

async function revokeOthers(): Promise<void> {
  try {
    await ElMessageBox.confirm('确定要登出其他所有装置吗？', '登出其他装置', {
      confirmButtonText: '全部登出',
      cancelButtonText: '取消',
      type: 'warning',
    })
  } catch {
    return
  }

  try {
    const { message } = await profileApi.revokeOtherSessions()
    ElMessage.success(message)
    await loadSessions()
  } catch (error) {
    ElMessage.error(error instanceof ApiError ? error.message : '操作失败')
  }
}

onMounted(loadSessions)
</script>

<style scoped lang="scss">
@use '@/styles/tokens.scss' as *;

.security-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
  gap: 14px;
  align-items: start;
}

.session-card {
  grid-column: 1 / -1;
}

.card-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.tip-alert {
  margin-bottom: 16px;
  border-radius: $radius-sm;
}

.device-cell {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.text-faint {
  color: $text-faint;
}
</style>
