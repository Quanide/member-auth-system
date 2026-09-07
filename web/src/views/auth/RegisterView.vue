<template>
  <AuthShell title="建立会员帐号" subtitle="填写以下资料完成注册，我们会寄出一封验证信">
    <el-form ref="formRef" :model="form" :rules="rules" label-position="top" @submit.prevent="onSubmit">
      <el-form-item label="姓名" prop="name" :error="serverErrors.name">
        <el-input
          v-model="form.name"
          size="large"
          placeholder="请输入您的姓名"
          autocomplete="name"
          :prefix-icon="User"
          @input="clearFieldError('name')"
        />
      </el-form-item>

      <el-form-item label="邮箱" prop="email" :error="serverErrors.email">
        <el-input
          v-model="form.email"
          type="email"
          size="large"
          placeholder="you@example.com"
          autocomplete="email"
          :prefix-icon="Message"
          @input="clearFieldError('email')"
        />
      </el-form-item>

      <el-form-item label="密码" prop="password" :error="serverErrors.password">
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

      <el-form-item label="确认密码" prop="password_confirmation">
        <el-input
          v-model="form.password_confirmation"
          type="password"
          size="large"
          placeholder="请再次输入密码"
          autocomplete="new-password"
          show-password
          :prefix-icon="Lock"
          @keyup.enter="onSubmit"
        />
      </el-form-item>

      <el-form-item prop="agree_terms" :error="serverErrors.agree_terms">
        <el-checkbox v-model="form.agree_terms">
          我已阅读并同意
          <el-button link type="primary" @click.prevent="termsVisible = true">服务条款</el-button>
        </el-checkbox>
      </el-form-item>

      <el-button
        type="primary"
        size="large"
        class="submit-btn"
        :loading="submitting"
        @click="onSubmit"
      >
        建立帐号
      </el-button>

      <p class="foot-hint">
        已经有帐号了？
        <router-link to="/login" class="link">前往登入</router-link>
      </p>
    </el-form>

    <el-dialog v-model="termsVisible" title="服务条款" width="min(520px, 92vw)">
      <div class="terms">
        <p>本站为面试作品展示用途，提供会员注册、登入与资料管理功能。</p>
        <p>我们以 bcrypt 演算法储存您的密码，绝不保存明文；登入凭证放在 httpOnly Cookie 中。</p>
        <p>系统会记录您的登入时间、IP 与操作纪录，仅用于帐号安全稽核，您可随时在「操作纪录」中查看。</p>
        <p>您可随时修改或清除自己的会员资料。</p>
      </div>
      <template #footer>
        <el-button type="primary" @click="acceptTerms">我已了解</el-button>
      </template>
    </el-dialog>
  </AuthShell>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import { Lock, Message, User } from '@element-plus/icons-vue'
import AuthShell from '@/components/AuthShell.vue'
import PasswordStrength from '@/components/PasswordStrength.vue'
import { useApiForm } from '@/composables/useApiForm'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()
const { submitting, serverErrors, submit, clearFieldError } = useApiForm()

const formRef = ref<FormInstance>()
const termsVisible = ref(false)

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  agree_terms: false,
})

const rules: FormRules = {
  name: [
    { required: true, message: '请输入姓名', trigger: 'blur' },
    { min: 2, max: 50, message: '姓名长度需介于 2 到 50 个字符', trigger: 'blur' },
  ],
  email: [
    { required: true, message: '请输入邮箱', trigger: 'blur' },
    { type: 'email', message: '邮箱格式不正确', trigger: 'blur' },
  ],
  password: [
    { required: true, message: '请输入密码', trigger: 'blur' },
    { min: 8, message: '密码至少 8 个字符', trigger: 'blur' },
    {
      // 与后端 Password::min(8)->letters()->numbers() 保持一致
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
    { required: true, message: '请再次输入密码', trigger: 'blur' },
    {
      validator: (_rule, value: string, callback) => {
        if (value !== form.password) return callback(new Error('两次输入的密码不一致'))
        callback()
      },
      trigger: 'blur',
    },
  ],
  agree_terms: [
    {
      validator: (_rule, value: boolean, callback) => {
        if (!value) return callback(new Error('请先阅读并同意服务条款'))
        callback()
      },
      trigger: 'change',
    },
  ],
}

function acceptTerms(): void {
  form.agree_terms = true
  termsVisible.value = false
}

async function onSubmit(): Promise<void> {
  await submit(formRef.value, () => auth.register({ ...form }), {
    onSuccess: () => {
      ElMessage.success('注册成功，验证信已寄出')
      router.push({ name: 'dashboard' })
    },
  })
}
</script>

<style scoped lang="scss">
.submit-btn {
  width: 100%;
  height: 44px;
  font-size: 15px;
  margin-top: 4px;
}

.link {
  color: #6366f1;
  text-decoration: none;

  &:hover {
    opacity: 0.75;
  }
}

.foot-hint {
  margin: 18px 0 0;
  text-align: center;
  font-size: 13px;
  color: #86909c;
}

.terms {
  font-size: 13.5px;
  line-height: 1.85;
  color: #4e5969;

  p {
    margin: 0 0 12px;
  }
}
</style>
