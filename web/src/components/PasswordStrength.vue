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
 * 前端強度提示。
 * 只是給使用者的即時反饋，真正的強度門檻由後端 Password::defaults() 把關，
 * 繞過前端也無法提交弱密碼。
 */
const score = computed(() => {
  const pw = props.password
  if (!pw) return 0

  let s = 0
  if (pw.length >= 8) s++
  if (pw.length >= 12) s++
  if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) s++
  if (/\d/.test(pw) && /[^a-zA-Z0-9]/.test(pw)) s++

  // 不滿足基本要求時直接壓到最低檔，避免給出誤導性的「中等」
  if (pw.length < 8 || !/[a-zA-Z]/.test(pw) || !/\d/.test(pw)) return 1

  return Math.max(1, s)
})

const levelText = computed(() => ['', '弱', '一般', '良好', '很強'][score.value] ?? '')

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
