<template>
  <AuthShell
    title="忘记密码"
    subtitle="输入注册时使用的邮箱，我们会寄出重设密码的连结"
    narrow
  >
    <template v-if="!sent">
      <el-form ref="formRef" :model="form" :rules="rules" label-position="top" @submit.prevent="onSubmit">
        <el-form-item label="邮箱" prop="email" :error="serverErrors.email">
          <el-input
            v-model="form.email"
            type="email"
            size="large"
            placeholder="you@example.com"
            :prefix-icon="Message"
            @keyup.enter="onSubmit"
            @input="clearFieldError('email')"
          />
        </el-form-item>

        <el-button type="primary" size="large" class="submit-btn" :loading="submitting" @click="onSubmit">
          寄出重设连结
        </el-button>
      </el-form>
    </template>

    <el-result v-else icon="success" title="邮件已寄出">
      <template #sub-title>
        <p class="result-desc">
          若 <strong>{{ form.email }}</strong> 已注册，您会在几分钟内收到重设密码的连结。
        </p>
        <p class="result-desc">没收到？请检查垃圾邮件匣，或稍后再试一次。</p>
      </template>
    </el-result>

    <p class="foot-hint">
      <router-link to="/login" class="link">返回登入</router-link>
    </p>
  </AuthShell>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { Message } from '@element-plus/icons-vue'
import type { FormInstance, FormRules } from 'element-plus'
import AuthShell from '@/components/AuthShell.vue'
import { authApi } from '@/api/auth'
import { useApiForm } from '@/composables/useApiForm'

const { submitting, serverErrors, submit, clearFieldError } = useApiForm()

const formRef = ref<FormInstance>()
const sent = ref(false)
const form = reactive({ email: '' })

const rules: FormRules = {
  email: [
    { required: true, message: '请输入邮箱', trigger: 'blur' },
    { type: 'email', message: '邮箱格式不正确', trigger: 'blur' },
  ],
}

async function onSubmit(): Promise<void> {
  await submit(formRef.value, () => authApi.forgotPassword(form.email), {
    onSuccess: () => {
      sent.value = true
    },
  })
}
</script>

<style scoped lang="scss">
.submit-btn {
  width: 100%;
  height: 44px;
  font-size: 15px;
}

.result-desc {
  margin: 6px 0;
  font-size: 13px;
  color: #86909c;
  line-height: 1.7;
}

.foot-hint {
  margin: 16px 0 0;
  text-align: center;
  font-size: 13px;
}

.link {
  color: #6366f1;
  text-decoration: none;

  &:hover {
    opacity: 0.75;
  }
}
</style>
