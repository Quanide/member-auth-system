<template>
  <div>
    <h1 class="page-title">会员资料</h1>
    <p class="page-subtitle">修改您的个人资料与头像</p>

    <div class="profile-grid">
      <!-- ── 头像 ───────────────────────────────── -->
      <el-card shadow="never" class="avatar-card">
        <template #header>头像</template>

        <div class="avatar-box">
          <el-avatar :size="112" :src="auth.user?.avatar_url ?? undefined" class="avatar-preview">
            {{ initial }}
          </el-avatar>

          <div class="avatar-actions">
            <!-- 用隐藏 input 而非 el-upload：需要在送出前先做客户端压缩 -->
            <input
              ref="fileInput"
              type="file"
              accept="image/jpeg,image/png,image/webp"
              hidden
              @change="onFileChange"
            />

            <el-button type="primary" size="small" :loading="uploading" @click="fileInput?.click()">
              {{ auth.user?.avatar_url ? '更换头像' : '上传头像' }}
            </el-button>

            <el-button
              v-if="auth.user?.avatar_url"
              size="small"
              :loading="removing"
              @click="removeAvatar"
            >
              移除
            </el-button>
          </div>

          <p class="avatar-hint">支援 JPG / PNG / WebP，上传后会自动裁切为正方形</p>
        </div>
      </el-card>

      <!-- ── 基本资料 ───────────────────────────── -->
      <el-card shadow="never">
        <template #header>基本资料</template>

        <el-form
          ref="formRef"
          :model="form"
          :rules="rules"
          label-position="top"
          @submit.prevent="onSubmit"
        >
          <div class="form-grid">
            <el-form-item label="姓名" prop="name" :error="serverErrors.name">
              <el-input
                v-model="form.name"
                placeholder="请输入姓名"
                maxlength="50"
                show-word-limit
                @input="clearFieldError('name')"
              />
            </el-form-item>

            <el-form-item label="昵称" prop="nickname" :error="serverErrors.nickname">
              <el-input
                v-model="form.nickname"
                placeholder="选填，显示在页面上的称呼"
                maxlength="50"
                @input="clearFieldError('nickname')"
              />
            </el-form-item>

            <el-form-item label="手机号码" prop="phone" :error="serverErrors.phone">
              <el-input
                v-model="form.phone"
                placeholder="选填，如 0912345678"
                maxlength="32"
                @input="clearFieldError('phone')"
              />
            </el-form-item>

            <el-form-item label="生日" prop="birthday" :error="serverErrors.birthday">
              <el-date-picker
                v-model="form.birthday"
                type="date"
                placeholder="选填"
                value-format="YYYY-MM-DD"
                :disabled-date="disableFutureDate"
                style="width: 100%"
              />
            </el-form-item>

            <el-form-item label="性别" prop="gender" :error="serverErrors.gender">
              <el-radio-group v-model="form.gender">
                <el-radio value="male">男</el-radio>
                <el-radio value="female">女</el-radio>
                <el-radio value="other">其他</el-radio>
              </el-radio-group>
            </el-form-item>
          </div>

          <el-form-item label="个人简介" prop="bio" :error="serverErrors.bio">
            <el-input
              v-model="form.bio"
              type="textarea"
              :rows="3"
              maxlength="500"
              show-word-limit
              placeholder="选填，简单介绍一下自己"
              @input="clearFieldError('bio')"
            />
          </el-form-item>

          <div class="form-actions">
            <el-button type="primary" :loading="submitting" @click="onSubmit">储存变更</el-button>
            <el-button :disabled="submitting" @click="reset">还原</el-button>
          </div>
        </el-form>
      </el-card>

      <!-- ── 帐号资讯（只读）─────────────────────── -->
      <el-card shadow="never" class="meta-card">
        <template #header>帐号资讯</template>

        <el-descriptions :column="1" border size="small">
          <el-descriptions-item label="邮箱">
            <div class="email-cell">
              <span>{{ auth.user?.email }}</span>
              <el-tag v-if="auth.user?.email_verified" type="success" size="small">已验证</el-tag>
              <el-tag v-else type="warning" size="small">未验证</el-tag>
            </div>
          </el-descriptions-item>
          <el-descriptions-item label="会员编号">#{{ auth.user?.id }}</el-descriptions-item>
          <el-descriptions-item label="角色">{{ auth.user?.role_label }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag :type="auth.user?.status === 'active' ? 'success' : 'danger'" size="small">
              {{ auth.user?.status_label }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="注册时间">
            {{ formatDateTime(auth.user?.created_at) }}
          </el-descriptions-item>
          <el-descriptions-item label="上次登入">
            {{ formatDateTime(auth.user?.last_login_at) }}
          </el-descriptions-item>
        </el-descriptions>

        <p class="meta-hint">
          需要修改邮箱或密码？请前往
          <router-link to="/security" class="link">帐号安全</router-link>
        </p>
      </el-card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox, type FormInstance, type FormRules } from 'element-plus'
import { profileApi } from '@/api/profile'
import { ApiError } from '@/api/client'
import { useApiForm } from '@/composables/useApiForm'
import { useAuthStore } from '@/stores/auth'
import { compressImage } from '@/utils/image'
import { formatDateTime } from '@/utils/format'

const auth = useAuthStore()
const { submitting, serverErrors, submit, clearFieldError, handleError } = useApiForm()

const formRef = ref<FormInstance>()
const fileInput = ref<HTMLInputElement>()
const uploading = ref(false)
const removing = ref(false)

const form = reactive({
  name: '',
  nickname: '' as string | null,
  phone: '' as string | null,
  birthday: '' as string | null,
  gender: '' as string | null,
  bio: '' as string | null,
})

const initial = computed(() => auth.user?.display_name?.charAt(0)?.toUpperCase() ?? 'U')

const rules: FormRules = {
  name: [
    { required: true, message: '请输入姓名', trigger: 'blur' },
    { min: 2, max: 50, message: '姓名长度需介于 2 到 50 个字符', trigger: 'blur' },
  ],
  phone: [
    {
      // 与后端 regex 规则保持一致，避免前端放行、后端才报错
      pattern: /^[+]?[0-9\s\-()]{6,32}$/,
      message: '手机号码格式不正确',
      trigger: 'blur',
    },
  ],
}

function disableFutureDate(date: Date): boolean {
  return date.getTime() > Date.now()
}

function reset(): void {
  const user = auth.user
  if (!user) return

  form.name = user.name
  form.nickname = user.nickname ?? ''
  form.phone = user.phone ?? ''
  form.birthday = user.birthday ?? ''
  form.gender = user.gender ?? ''
  form.bio = user.bio ?? ''

  formRef.value?.clearValidate()
}

async function onSubmit(): Promise<void> {
  await submit(
    formRef.value,
    () =>
      profileApi.update({
        name: form.name,
        nickname: form.nickname || null,
        phone: form.phone || null,
        birthday: form.birthday || null,
        gender: form.gender || null,
        bio: form.bio || null,
      }),
    {
      successMessage: '资料已更新',
      onSuccess: ({ user }) => auth.setUser(user),
    },
  )
}

async function onFileChange(event: Event): Promise<void> {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]

  // 清空 value，否则连选两次同一张图不会触发 change
  input.value = ''

  if (!file) return

  uploading.value = true

  try {
    // 客户端先压到 512px：省流量，也避免大图触发后端的尺寸上限
    const compressed = await compressImage(file, 512)
    const { user, message } = await profileApi.uploadAvatar(compressed)

    auth.setUser(user)
    ElMessage.success(message)
  } catch (error) {
    handleError(error)
  } finally {
    uploading.value = false
  }
}

async function removeAvatar(): Promise<void> {
  try {
    await ElMessageBox.confirm('确定要移除目前的头像吗？', '移除头像', {
      confirmButtonText: '移除',
      cancelButtonText: '取消',
      type: 'warning',
    })
  } catch {
    return
  }

  removing.value = true

  try {
    const { user, message } = await profileApi.removeAvatar()
    auth.setUser(user)
    ElMessage.success(message)
  } catch (error) {
    ElMessage.error(error instanceof ApiError ? error.message : '移除失败')
  } finally {
    removing.value = false
  }
}

onMounted(reset)
</script>

<style scoped lang="scss">
@use '@/styles/tokens.scss' as *;

.profile-grid {
  display: grid;
  grid-template-columns: 300px 1fr;
  grid-template-areas:
    'avatar basic'
    'meta basic';
  align-items: start;
  gap: 14px;
}

.avatar-card {
  grid-area: avatar;
}

.meta-card {
  grid-area: meta;
}

.profile-grid > :nth-child(2) {
  grid-area: basic;
}

.avatar-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
}

.avatar-preview {
  background: $brand-gradient !important;
  color: #fff;
  font-size: 36px;
  font-weight: 600;
  box-shadow: 0 6px 20px rgba(99, 102, 241, 0.22);
}

.avatar-actions {
  display: flex;
  gap: 8px;
}

.avatar-hint {
  margin: 0;
  font-size: 12px;
  color: $text-muted;
  text-align: center;
  line-height: 1.6;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 0 18px;
}

.form-actions {
  display: flex;
  gap: 10px;
  margin-top: 6px;
}

.email-cell {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.meta-hint {
  margin: 14px 0 0;
  font-size: 12.5px;
  color: $text-muted;
}

.link {
  color: $brand-primary;
  text-decoration: none;

  &:hover {
    opacity: 0.75;
  }
}

@media (max-width: 900px) {
  .profile-grid {
    grid-template-columns: 1fr;
    grid-template-areas:
      'avatar'
      'basic'
      'meta';
  }
}
</style>
