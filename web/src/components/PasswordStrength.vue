<template>
  <div v-if="password" class="pw-strength">
    <div class="pw-bars">
      <span
        v-for="i in 4"
        :key="i"
        class="pw-bar"
        :class="{ active: i <= score }"
        :style="i <= score ? { background: levelColor } : undefined"
      />
    </div>
    <span class="pw-label" :style="{ color: levelColor }">{{ levelText }}</span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ password: string }>()

/**
 * 前端强度提示。
 * 只是给用户的即时反馈，真正的强度门槛由后端 Password::defaults() 把关，
 * 绕过前端也无法提交弱密码。
 */
const score = computed(() => {
  const pw = props.password
  if (!pw) return 0

  let s = 0
  if (pw.length >= 8) s++
  if (pw.length >= 12) s++
  if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) s++
  if (/\d/.test(pw) && /[^a-zA-Z0-9]/.test(pw)) s++

  // 不满足基本要求时直接压到最低档，避免给出误导性的「中等」
  if (pw.length < 8 || !/[a-zA-Z]/.test(pw) || !/\d/.test(pw)) return 1

  return Math.max(1, s)
})

const levelText = computed(() => ['', '弱', '一般', '良好', '很强'][score.value] ?? '')

const levelColor = computed(
  () => ['', '#F53F3F', '#FF7D00', '#6366F1', '#00B42A'][score.value] ?? '#C9CDD4',
)
</script>

<style scoped lang="scss">
.pw-strength {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 8px;
}

.pw-bars {
  display: flex;
  gap: 4px;
  flex: 1;
}

.pw-bar {
  height: 3px;
  flex: 1;
  border-radius: 2px;
  background: #ecedf5;
  transition: background 0.25s;
}

.pw-label {
  font-size: 12px;
  font-weight: 500;
  min-width: 28px;
  text-align: right;
}
</style>
