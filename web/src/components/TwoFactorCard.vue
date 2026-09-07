<template>
  <el-card shadow="never">
    <template #header>
      <div class="card-head">
        <span>雙因素認證</span>
        <el-tag :type="enabled ? 'success' : 'info'" size="small" effect="light">
          {{ enabled ? '已啟用' : '未啟用' }}
        </el-tag>
      </div>
    </template>

    <!-- 未啟用 -->
    <template v-if="!enabled">
      <p class="desc">
        啟用後，除了密碼之外還需要輸入驗證 App 上的 6 位數驗證碼才能登入。
        即使密碼外洩，他人也無法進入您的帳號。
      </p>
      <p class="desc desc--hint">
        支援 Google Authenticator、Microsoft Authenticator、1Password 等標準 TOTP App。
      </p>
      <el-button type="primary" :loading="generating" @click="startBinding">啟用雙因素認證</el-button>
    </template>

    <!-- 已啟用 -->
    <template v-else>
      <el-descriptions :column="1" border size="small">
        <el-descriptions-item label="狀態">
          <span class="on-text">保護中</span>
        </el-descriptions-item>
        <el-descriptions-item label="剩餘恢復碼">
          <span :class="{ 'low-codes': remaining <= 2 }">{{ remaining }} 組</span>
          <span v-if="remaining <= 2" class="low-hint">建議重新產生</span>
        </el-descriptions-item>
      </el-descriptions>

      <div class="actions">
        <el-button size="small" @click="regenerateVisible = true">重新產生恢復碼</el-button>
        <el-button size="small" type="danger" plain @click="disableVisible = true">
          關閉雙因素認證
        </el-button>
      </div>
    </template>

    <!-- 綁定流程 -->
    <el-dialog
      v-model="bindVisible"
      title="啟用雙因素認證"
      width="min(440px, 92vw)"
      :close-on-click-modal="false"
      @closed="resetBinding"
    >
      <template v-if="!recoveryCodes.length">
        <ol class="steps">
          <li>用驗證 App 掃描下方 QR Code</li>
          <li>輸入 App 上顯示的 6 位數驗證碼</li>
        </ol>

        <div class="qr-box">
          <img v-if="qrCode" :src="qrCode" alt="TOTP QR Code" class="qr" />
        </div>

        <div class="secret-box">
          <span class="secret-label">無法掃描？手動輸入這組密鑰：</span>
          <code class="secret">{{ secret }}</code>
        </div>

        <el-form ref="confirmFormRef" :model="confirmForm" :rules="confirmRules" @submit.prevent="confirmBinding">
          <el-form-item prop="code" :error="serverErrors.code">
            <el-input
              v-model="confirmForm.code"
              size="large"
              maxlength="6"
              placeholder="請輸入 6 位數驗證碼"
              class="code-input"
              @input="clearFieldError('code')"
              @keyup.enter="confirmBinding"
            />
          </el-form-item>
        </el-form>
      </template>

      <!-- 恢復碼 -->
      <template v-else>
        <el-alert
          type="warning"
          :closable="false"
          show-icon
          title="請立即保存以下恢復碼"
          description="每組只能使用一次。當您無法使用驗證 App 時，可用恢復碼登入。此頁關閉後將無法再次查看。"
          class="mb-16"
        />

        <div class="codes">
          <code v-for="code in recoveryCodes" :key="code" class="code-item">{{ code }}</code>
        </div>

        <el-button size="small" class="mt-12" @click="copyCodes">
          <Copy :size="14" style="margin-right: 4px" />複製全部
        </el-button>
      </template>

      <template #footer>
        <template v-if="!recoveryCodes.length">
          <el-button @click="bindVisible = false">取消</el-button>
          <el-button type="primary" :loading="submitting" @click="confirmBinding">確認啟用</el-button>
        </template>
        <el-button v-else type="primary" @click="finishBinding">我已保存，完成</el-button>
      </template>
    </el-dialog>

    <!-- 關閉 2FA -->
    <el-dialog v-model="disableVisible" title="關閉雙因素認證" width="min(400px, 92vw)">
      <el-alert
        type="warning"
        :closable="false"
        show-icon
        title="關閉後，帳號將只靠密碼保護"
        class="mb-16"
      />
      <el-form @submit.prevent="doDisable">
        <el-form-item label="請輸入目前密碼以確認身分" :error="serverErrors.password">
          <el-input
            v-model="passwordInput"
            type="password"
            show-password
            placeholder="目前密碼"
            @keyup.enter="doDisable"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="disableVisible = false">取消</el-button>
        <el-button type="danger" :loading="submitting" @click="doDisable">確認關閉</el-button>
      </template>
    </el-dialog>

    <!-- 重產恢復碼 -->
    <el-dialog v-model="regenerateVisible" title="重新產生恢復碼" width="min(400px, 92vw)">
      <template v-if="!recoveryCodes.length">
        <el-alert type="info" :closable="false" show-icon title="舊的恢復碼會立即失效" class="mb-16" />
        <el-form @submit.prevent="doRegenerate">
          <el-form-item label="請輸入目前密碼以確認身分" :error="serverErrors.password">
            <el-input
              v-model="passwordInput"
              type="password"
              show-password
              placeholder="目前密碼"
              @keyup.enter="doRegenerate"
            />
          </el-form-item>
        </el-form>
      </template>
      <template v-else>
        <div class="codes">
          <code v-for="code in recoveryCodes" :key="code" class="code-item">{{ code }}</code>
        </div>
        <el-button size="small" class="mt-12" @click="copyCodes">
          <Copy :size="14" style="margin-right: 4px" />複製全部
        </el-button>
      </template>

      <template #footer>
        <template v-if="!recoveryCodes.length">
          <el-button @click="regenerateVisible = false">取消</el-button>
          <el-button type="primary" :loading="submitting" @click="doRegenerate">確認產生</el-button>
        </template>
        <el-button v-else type="primary" @click="finishRegenerate">我已保存，完成</el-button>
      </template>
    </el-dialog>
  </el-card>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import { Copy } from 'lucide-vue-next'
import { twoFactorApi } from '@/api/twoFactor'
import { useApiForm } from '@/composables/useApiForm'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const { submitting, serverErrors, handleError, clearFieldError } = useApiForm()

const enabled = computed(() => auth.user?.two_factor_enabled === true)
const remaining = computed(() => auth.user?.recovery_codes_remaining ?? 0)

const bindVisible = ref(false)
const disableVisible = ref(false)
const regenerateVisible = ref(false)
const generating = ref(false)

const qrCode = ref('')
const secret = ref('')
const recoveryCodes = ref<string[]>([])
const passwordInput = ref('')

const confirmFormRef = ref<FormInstance>()
const confirmForm = ref({ code: '' })

const confirmRules: FormRules = {
  code: [
    { required: true, message: '請輸入驗證碼', trigger: 'blur' },
    { pattern: /^\d{6}$/, message: '驗證碼為 6 位數字', trigger: 'blur' },
  ],
}

async function startBinding(): Promise<void> {
  generating.value = true

  try {
    const result = await twoFactorApi.generate()
    qrCode.value = result.qr_code
    secret.value = result.secret
    bindVisible.value = true
  } catch (error) {
    handleError(error)
  } finally {
    generating.value = false
  }
}

async function confirmBinding(): Promise<void> {
  const valid = await confirmFormRef.value?.validate().catch(() => false)
  if (!valid) return

  submitting.value = true

  try {
    const result = await twoFactorApi.confirm(confirmForm.value.code)
    recoveryCodes.value = result.recovery_codes
    await auth.refresh()
    ElMessage.success('雙因素認證已啟用')
  } catch (error) {
    handleError(error)
  } finally {
    submitting.value = false
  }
}

async function doDisable(): Promise<void> {
  if (!passwordInput.value) return

  submitting.value = true

  try {
    const { message } = await twoFactorApi.disable(passwordInput.value)
    await auth.refresh()
    ElMessage.success(message)
    disableVisible.value = false
    passwordInput.value = ''
  } catch (error) {
    handleError(error)
  } finally {
    submitting.value = false
  }
}

async function doRegenerate(): Promise<void> {
  if (!passwordInput.value) return

  submitting.value = true

  try {
    const result = await twoFactorApi.regenerateRecoveryCodes(passwordInput.value)
    recoveryCodes.value = result.recovery_codes
    await auth.refresh()
  } catch (error) {
    handleError(error)
  } finally {
    submitting.value = false
  }
}

async function copyCodes(): Promise<void> {
  try {
    await navigator.clipboard.writeText(recoveryCodes.value.join('\n'))
    ElMessage.success('已複製到剪貼簿')
  } catch {
    // 非 HTTPS 或瀏覽器不支援時，請使用者手動選取
    ElMessage.warning('瀏覽器不允許自動複製，請手動選取')
  }
}

function resetBinding(): void {
  qrCode.value = ''
  secret.value = ''
  recoveryCodes.value = []
  confirmForm.value.code = ''
  confirmFormRef.value?.clearValidate()
}

function finishBinding(): void {
  bindVisible.value = false
}

function finishRegenerate(): void {
  regenerateVisible.value = false
  recoveryCodes.value = []
  passwordInput.value = ''
}
</script>

<style scoped lang="scss">
@use '@/styles/tokens.scss' as *;

.card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.desc {
  margin: 0 0 10px;
  font-size: 13.5px;
  line-height: 1.75;
  color: $text-body;
}

.desc--hint {
  font-size: 12.5px;
  color: $text-muted;
  margin-bottom: 16px;
}

.on-text {
  color: $success;
  font-weight: 500;
}

.low-codes {
  color: $warning;
  font-weight: 500;
}

.low-hint {
  margin-left: 8px;
  font-size: 12px;
  color: $text-muted;
}

.actions {
  display: flex;
  gap: 10px;
  margin-top: 16px;
  flex-wrap: wrap;
}

.steps {
  margin: 0 0 16px;
  padding-left: 20px;
  font-size: 13.5px;
  line-height: 1.9;
  color: $text-body;
}

.qr-box {
  display: flex;
  justify-content: center;
  padding: 14px;
  background: $bg-tint;
  border: 1px solid $border-tint;
  border-radius: $radius-md;
}

.qr {
  width: 180px;
  height: 180px;
  display: block;
}

.secret-box {
  margin: 14px 0 16px;
  text-align: center;
}

.secret-label {
  display: block;
  font-size: 12px;
  color: $text-muted;
  margin-bottom: 6px;
}

.secret {
  display: inline-block;
  padding: 5px 10px;
  background: $bg-subtle;
  border: 1px solid $border-base;
  border-radius: 6px;
  font-size: 12.5px;
  letter-spacing: 1px;
  color: $text-body;
  word-break: break-all;
}

.code-input {
  :deep(input) {
    text-align: center;
    letter-spacing: 8px;
    font-size: 18px;
    font-variant-numeric: tabular-nums;
  }
}

.codes {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px;
}

.code-item {
  padding: 8px 10px;
  background: $bg-subtle;
  border: 1px solid $border-base;
  border-radius: 6px;
  font-size: 12.5px;
  text-align: center;
  color: $text-body;
  letter-spacing: 0.5px;
}

.mb-16 {
  margin-bottom: 16px;
}

.mt-12 {
  margin-top: 12px;
}
</style>
