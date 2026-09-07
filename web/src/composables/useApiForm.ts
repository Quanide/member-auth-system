import { ref } from 'vue'
import { ElMessage } from 'element-plus'
import type { FormInstance } from 'element-plus'
import { ApiError } from '@/api/client'

/**
 * 表單提交的通用處理：
 * 統一負責 loading 狀態、把後端的欄位級錯誤回填到表單、以及錯誤提示。
 * 每個頁面各寫一遍 try/catch 既囉嗦又容易漏掉分支。
 */
export function useApiForm() {
  const submitting = ref(false)
  const serverErrors = ref<Record<string, string>>({})

  async function submit<T>(
    formRef: FormInstance | undefined,
    action: () => Promise<T>,
    options: { onSuccess?: (result: T) => void; successMessage?: string } = {},
  ): Promise<T | undefined> {
    if (formRef) {
      const valid = await formRef.validate().catch(() => false)
      if (!valid) return undefined
    }

    submitting.value = true
    serverErrors.value = {}

    try {
      const result = await action()

      if (options.successMessage) {
        ElMessage.success(options.successMessage)
      }

      options.onSuccess?.(result)

      return result
    } catch (error) {
      handleError(error)

      return undefined
    } finally {
      submitting.value = false
    }
  }

  function handleError(error: unknown): void {
    if (!(error instanceof ApiError)) {
      ElMessage.error('發生未預期的錯誤，請稍後再試')
      console.error(error)

      return
    }

    // 欄位級錯誤回填到對應輸入框下方，而不是彈一個籠統的提示
    if (Object.keys(error.fieldErrors).length > 0) {
      const mapped: Record<string, string> = {}

      for (const [field, messages] of Object.entries(error.fieldErrors)) {
        if (messages[0]) mapped[field] = messages[0]
      }

      serverErrors.value = mapped
    }

    ElMessage.error(error.message)
  }

  function clearFieldError(field: string): void {
    if (serverErrors.value[field]) {
      const next = { ...serverErrors.value }
      delete next[field]
      serverErrors.value = next
    }
  }

  return { submitting, serverErrors, submit, handleError, clearFieldError }
}
