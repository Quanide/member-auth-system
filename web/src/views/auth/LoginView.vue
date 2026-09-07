<template>
  <AuthShell
    :title="stage === 'password' ? '登入會員中心' : '雙因素驗證'"
    :subtitle="stage === 'password' ? '使用您註冊時的信箱與密碼登入' : '請輸入驗證 App 上顯示的 6 位數驗證碼'"
  >
    <el-form v-if="stage === 'password'" ref="formRef" :model="form" :rules="rules" label-position="top" @submit.prevent="onSubmit">
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
          placeholder="請輸入密碼"
          autocomplete="current-password"
          show-password
          :prefix-icon="Lock"
          @keyup.enter="onSubmit"
          @input="clearFieldError('password')"
        />
      </el-form-item>

      <div class="form-row">
        <el-checkbox v-model="form.remember">記住我</el-checkbox>
        <router-link to="/forgot-password" class="link">忘記密碼？</router-link>
      </div>

      <el-button
        type="primary"
        size="large"
        class="submit-btn"
        :loading="submitting"
        @click="onSubmit"
      >
        登入
      </el-button>

      <p class="foot-hint">
        還沒有帳號？
        <router-link to="/register" class="link">立即註冊</router-link>
      </p>
    </el-form>

    <!-- 第二關：TOTP 驗證碼或恢復碼 -->
    <el-form v-else ref="codeFormRef" :model="codeForm" :rules="codeRules" @submit.prevent="onChallenge">
      <el-form-item prop="code" :error="serverErrors.code">
        <el-input
          v-model="codeForm.code"
          size="large"
          maxlength="17"
          placeholder="6 位數驗證碼"
          class="code-input"
          autofocus
          @input="clearFieldError('code')"
          @keyup.enter="onChallenge"
        />
      </el-form-item>

      <el-button type="primary" size="large" class="submit-btn" :loading="submitting" @click="onChallenge">
        驗證並登入
      </el-button>

      <p class="foot-hint">
        無法使用驗證 App？可改輸入其中一組恢復碼。
        <br />
        <el-button link type="primary" @click="backToPassword">返回重新登入</el-button>
      </p>
    </el-form>

    <div v-if="stage === 'password'" class="demo-tip">
      <span class="demo-tip-title">評審可用示範帳號</span>
      <code>demo@wanghui.aipod.works</code>
      <code>Demo12345</code>
      <el-button link type="primary" size="small" @click="fillDemo">一鍵填入</el-button>
    </div>
  </AuthShell>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import { Lock, Message } from '@element-plus/icons-vue'
import AuthShell from '@/components/AuthShell.vue'
import { twoFactorApi } from '@/api/twoFactor'
import { useApiForm } from '@/composables/useApiForm'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const { submitting, serverErrors, submit, clearFieldError } = useApiForm()

const formRef = ref<FormInstance>()
const codeFormRef = ref<FormInstance>()

/** password：帳密關；two-factor：驗證碼關 */
const stage = ref<'password' | 'two-factor'>('password')
const codeForm = reactive({ code: '' })

const form = reactive({
  email: '',
  password: '',
  remember: false,
})

const codeRules: FormRules = {
  code: [
    { required: true, message: '請輸入驗證碼', trigger: 'blur' },
    {
      // 6 位數字是 TOTP，含連字號的是恢復碼，兩種都放行
      validator: (_rule, value: string, callback) => {
        if (!value) return callback()
        if (/^\d{6}$/.test(value) || /^[A-Za-z0-9]{8}-[A-Za-z0-9]{8}$/.test(value.trim())) {
          return callback()
        }
        callback(new Error('請輸入 6 位數驗證碼，或一組恢復碼'))
      },
      trigger: 'blur',
    },
  ],
}

const rules: FormRules = {
  email: [
    { required: true, message: '請輸入信箱', trigger: 'blur' },
    { type: 'email', message: '信箱格式不正確', trigger: 'blur' },
  ],
  password: [{ required: true, message: '請輸入密碼', trigger: 'blur' }],
}

function fillDemo(): void {
  form.email = 'demo@wanghui.aipod.works'
  form.password = 'Demo12345'
}

function goAfterLogin(): void {
  // 回到被攔截前想去的頁面
  const redirect = route.query.redirect
  router.push(typeof redirect === 'string' ? redirect : { name: 'dashboard' })
}

async function onSubmit(): Promise<void> {
  await submit(formRef.value, () => auth.login({ ...form }), {
    onSuccess: (result) => {
      // 啟用 2FA 的帳號，密碼正確只是通過第一關
      if (result?.twoFactorRequired) {
        stage.value = 'two-factor'

        return
      }

      ElMessage.success('登入成功')
      goAfterLogin()
    },
  })
}

async function onChallenge(): Promise<void> {
  await submit(codeFormRef.value, () => twoFactorApi.challenge(codeForm.code.trim()), {
    onSuccess: ({ user }) => {
      auth.setUser(user)
      ElMessage.success('登入成功')
      goAfterLogin()
    },
  })
}

function backToPassword(): void {
  stage.value = 'password'
  codeForm.code = ''
  form.password = ''
}
</script>

<style scoped lang="scss">
.form-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
  margin-top: -6px;
}

.link {
  font-size: 13px;
  color: #6366f1;
  text-decoration: none;
  transition: opacity 0.16s;

  &:hover {
    opacity: 0.75;
  }
}

.submit-btn {
  width: 100%;
  height: 44px;
  font-size: 15px;
}

.code-input {
  :deep(input) {
    text-align: center;
    letter-spacing: 6px;
    font-size: 18px;
    font-variant-numeric: tabular-nums;
  }
}

.foot-hint {
  margin: 18px 0 0;
  text-align: center;
  font-size: 13px;
  color: #86909c;
}

.demo-tip {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 22px;
  padding: 12px 14px;
  border: 1px dashed #ede9fe;
  border-radius: 10px;
  background: #f5f4ff;
  font-size: 12px;
  color: #86909c;

  code {
    padding: 2px 7px;
    border-radius: 5px;
    background: #fff;
    border: 1px solid #ecedf5;
    color: #4e5969;
    font-size: 11.5px;
  }
}

.demo-tip-title {
  color: #6366f1;
  font-weight: 600;
}
</style>
