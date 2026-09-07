<template>
  <AuthShell title="重设密码" subtitle="请设定一组新的密码" narrow>
    <el-alert
      v-if="!token || !email"
      type="error"
      :closable="false"
      title="连结无效"
      description="缺少必要参数，请重新从邮件中的连结进入。"
      show-icon
    />

    <template v-else-if="!done">
      <el-form ref="formRef" :model="form" :rules="rules" label-position="top" @submit.prevent="onSubmit">
        <el-form-item label="帐号">
          <el-input :model-value="email" size="large" disabled :prefix-icon="Message" />
        </el-form-item>

        <el-form-item label="新密码" prop="password" :error="serverErrors.password">
          <el-input
            v-model="form.password"
            type="password"
            size="large"
            placeholder="至少 8 位，需含英文字母与数字"
            autocomplete="new-password"
            show-password
            :prefix-icon="Lock"
            @input="clearFieldError('password')"
          />
          <PasswordStrength :password="form.password" />
        </el-form-item>

        <el-form-item label="确认新密码" prop="password_confirmation">
          <el-input
            v-model="form.password_confirmation"
            type="password"
            size="large"
            placeholder="请再次输入新密码"
            autocomplete="new-password"
            show-password
            :prefix-icon="Lock"
            @keyup.enter="onSubmit"
          />
        </el-form-item>

        <el-button type="primary" size="large" class="submit-btn" :loading="submitting" @click="onSubmit">
          确认重设
        </el-button>
      </el-form>
    </template>

    <el-result v-else icon="success" title="密码已重设">
      <template #sub-title>
        <p class="result-desc">请使用新密码重新登入。</p>
      </template>
      <template #extra>
        <el-button type="primary" @click="$router.push('/login')">前往登入</el-button>
      </template>
    </el-result>
  </AuthShell>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { Lock, Message } from '@element-plus/icons-vue'
import type { FormInstance, FormRules } from 'element-plus'
import AuthShell from '@/components/AuthShell.vue'
import PasswordStrength from '@/components/PasswordStrength.vue'
import { authApi } from '@/api/auth'
import { useApiForm } from '@/composables/useApiForm'

const route = useRoute()
const { submitting, serverErrors, submit, clearFieldError } = useApiForm()

const formRef = ref<FormInstance>()
const done = ref(false)

const token = computed(() => (typeof route.query.token === 'string' ? route.query.token : ''))
const email = computed(() => (typeof route.query.email === 'string' ? route.query.email : ''))

const form = reactive({ password: '', password_confirmation: '' })

const rules: FormRules = {
  password: [
    { required: true, message: '请输入新密码', trigger: 'blur' },
    { min: 8, message: '密码至少 8 个字符', trigger: 'blur' },
    {
      validator: (_rule, value: string, callback) => {
        if (!value) return callback()
        if (!/[a-zA-Z]/.test(value)) return callback(new Error('密码需包含英文字母'))
        if (!/\d/.test(value)) return callback(new Error('密码需包含数字'))
        callback()
      },
      trigger: 'blur',
    },
  ],
  password_confirmation: [
    { required: true, message: '请再次输入新密码', trigger: 'blur' },
    {
      validator: (_rule, value: string, callback) => {
        if (value !== form.password) return callback(new Error('两次输入的密码不一致'))
        callback()
      },
      trigger: 'blur',
    },
  ],
}

async function onSubmit(): Promise<void> {
  await submit(
    formRef.value,
    () =>
      authApi.resetPassword({
        token: token.value,
        email: email.value,
        password: form.password,
        password_confirmation: form.password_confirmation,
      }),
    {
      onSuccess: () => {
        done.value = true
      },
    },
  )
}
</script>

<style scoped lang="scss">
.submit-btn {
  width: 100%;
  height: 44px;
  font-size: 15px;
}

.result-desc {
  font-size: 13px;
  color: #86909c;
}
</style>
