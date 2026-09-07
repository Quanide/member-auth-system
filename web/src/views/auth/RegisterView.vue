<template>
  <AuthShell title="建立會員帳號" subtitle="填寫以下資料完成註冊，我們會寄出一封驗證信">
    <el-form ref="formRef" :model="form" :rules="rules" label-position="top" @submit.prevent="onSubmit">
      <el-form-item label="姓名" prop="name" :error="serverErrors.name">
        <el-input
          v-model="form.name"
          size="large"
          placeholder="請輸入您的姓名"
          autocomplete="name"
          :prefix-icon="User"
          @input="clearFieldError('name')"
        />
      </el-form-item>

      <el-form-item label="信箱" prop="email" :error="serverErrors.email">
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

      <el-form-item label="密碼" prop="password" :error="serverErrors.password">
        <el-input
          v-model="form.password"
          type="password"
          size="large"
          placeholder="至少 8 位，需含英文字母與數字"
          autocomplete="new-password"
          show-password
          :prefix-icon="Lock"
          @input="clearFieldError('password')"
        />
        <PasswordStrength :password="form.password" />
      </el-form-item>

      <el-form-item label="確認密碼" prop="password_confirmation">
        <el-input
          v-model="form.password_confirmation"
          type="password"
          size="large"
          placeholder="請再次輸入密碼"
          autocomplete="new-password"
          show-password
          :prefix-icon="Lock"
          @keyup.enter="onSubmit"
        />
      </el-form-item>

      <el-form-item prop="agree_terms" :error="serverErrors.agree_terms">
        <el-checkbox v-model="form.agree_terms">
          我已閱讀並同意
          <el-button link type="primary" @click.prevent="termsVisible = true">服務條款</el-button>
        </el-checkbox>
      </el-form-item>

      <el-button
        type="primary"
        size="large"
        class="submit-btn"
        :loading="submitting"
        @click="onSubmit"
      >
        建立帳號
      </el-button>

      <p class="foot-hint">
        已經有帳號了？
        <router-link to="/login" class="link">前往登入</router-link>
      </p>
    </el-form>

    <el-dialog v-model="termsVisible" title="服務條款" width="min(520px, 92vw)">
      <div class="terms">
        <p>本站為面試作品展示用途，提供會員註冊、登入與資料管理功能。</p>
        <p>我們以 bcrypt 演算法儲存您的密碼，絕不保存明文；登入憑證放在 httpOnly Cookie 中。</p>
        <p>系統會記錄您的登入時間、IP 與操作紀錄，僅用於帳號安全稽核，您可隨時在「操作紀錄」中查看。</p>
        <p>您可隨時修改或清除自己的會員資料。</p>
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
    { required: true, message: '請輸入姓名', trigger: 'blur' },
    { min: 2, max: 50, message: '姓名長度需介於 2 到 50 個字符', trigger: 'blur' },
  ],
  email: [
    { required: true, message: '請輸入信箱', trigger: 'blur' },
    { type: 'email', message: '信箱格式不正確', trigger: 'blur' },
  ],
  password: [
    { required: true, message: '請輸入密碼', trigger: 'blur' },
    { min: 8, message: '密碼至少 8 個字符', trigger: 'blur' },
    {
      // 與後端 Password::min(8)->letters()->numbers() 保持一致
      validator: (_rule, value: string, callback) => {
        if (!value) return callback()
        if (!/[a-zA-Z]/.test(value)) return callback(new Error('密碼需包含英文字母'))
        if (!/\d/.test(value)) return callback(new Error('密碼需包含數字'))
        callback()
      },
      trigger: 'blur',
    },
  ],
  password_confirmation: [
    { required: true, message: '請再次輸入密碼', trigger: 'blur' },
    {
      validator: (_rule, value: string, callback) => {
        if (value !== form.password) return callback(new Error('兩次輸入的密碼不一致'))
        callback()
      },
      trigger: 'blur',
    },
  ],
  agree_terms: [
    {
      validator: (_rule, value: boolean, callback) => {
        if (!value) return callback(new Error('請先閱讀並同意服務條款'))
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
      ElMessage.success('註冊成功，驗證信已寄出')
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
