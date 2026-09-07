import { ref } from 'vue'
import { ElMessage } from 'element-plus'
import type { FormInstance } from 'element-plus'
import { ApiError } from '@/api/client'

/**
 * 表单提交的通用处理：
 * 统一负责 loading 状态、把后端的字段级错误回填到表单、以及错误提示。
 * 每个页面各写一遍 try/catch 既啰嗦又容易漏掉分支。
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
      ElMessage.error('发生未预期的错误，请稍后再试')
      console.error(error)

      return
    }

    // 字段级错误回填到对应输入框下方，而不是弹一个笼统的提示
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
