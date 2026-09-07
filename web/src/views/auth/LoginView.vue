<template>
  <AuthShell title="登入会员中心" subtitle="使用您注册时的邮箱与密码登入">
    <el-form ref="formRef" :model="form" :rules="rules" label-position="top" @submit.prevent="onSubmit">
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
          placeholder="请输入密码"
          autocomplete="current-password"
          show-password
          :prefix-icon="Lock"
          @keyup.enter="onSubmit"
          @input="clearFieldError('password')"
        />
      </el-form-item>

      <div class="form-row">
        <el-checkbox v-model="form.remember">记住我</el-checkbox>
        <router-link to="/forgot-password" class="link">忘记密码？</router-link>
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
        还没有帐号？
        <router-link to="/register" class="link">立即注册</router-link>
      </p>
    </el-form>

    <div class="demo-tip">
      <span class="demo-tip-title">评审可用示范帐号</span>
      <code>demo@wanghui.aipod.works</code>
      <code>Demo12345</code>
      <el-button link type="primary" size="small" @click="fillDemo">一键填入</el-button>
    </div>
  </AuthShell>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import { Lock, Message } from '@element-plus/icons-vue'
import AuthShell from '@/components/AuthShell.vue'
import { useApiForm } from '@/composables/useApiForm'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const { submitting, serverErrors, submit, clearFieldError } = useApiForm()

const formRef = ref<FormInstance>()

const form = reactive({
  email: '',
  password: '',
  remember: false,
})

const rules: FormRules = {
  email: [
    { required: true, message: '请输入邮箱', trigger: 'blur' },
    { type: 'email', message: '邮箱格式不正确', trigger: 'blur' },
  ],
  password: [{ required: true, message: '请输入密码', trigger: 'blur' }],
}

function fillDemo(): void {
  form.email = 'demo@wanghui.aipod.works'
  form.password = 'Demo12345'
}

async function onSubmit(): Promise<void> {
  await submit(formRef.value, () => auth.login({ ...form }), {
    onSuccess: () => {
      ElMessage.success('登入成功')

      // 回到被拦截前想去的页面
      const redirect = route.query.redirect
      router.push(typeof redirect === 'string' ? redirect : { name: 'dashboard' })
    },
  })
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
