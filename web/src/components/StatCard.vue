<template>
  <div class="stat-card">
    <div class="stat-icon" :style="{ background: tint, color }">
      <component :is="icon" :size="19" :stroke-width="1.9" />
    </div>
    <div class="stat-body">
      <div class="stat-value">
        {{ display }}
        <span v-if="suffix" class="stat-suffix">{{ suffix }}</span>
      </div>
      <div class="stat-label">{{ label }}</div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch, type Component } from 'vue'

const props = withDefaults(
  defineProps<{
    label: string
    value: number
    icon: Component
    color?: string
    tint?: string
    suffix?: string
    /** 數字滾動動畫時長（毫秒），設 0 關閉 */
    duration?: number
  }>(),
  {
    color: '#6366F1',
    tint: '#EFF0FE',
    suffix: '',
    duration: 700,
  },
)

// 數字滾動：讓看板載入時有生命感，但不影響可讀性
const current = ref(0)
const display = computed(() => Math.round(current.value).toLocaleString('zh-CN'))

function animate(to: number): void {
  if (props.duration <= 0) {
    current.value = to

    return
  }

  const from = current.value
  const start = performance.now()

  const step = (now: number): void => {
    const progress = Math.min(1, (now - start) / props.duration)
    // easeOutCubic：開頭快、結尾穩
    current.value = from + (to - from) * (1 - Math.pow(1 - progress, 3))

    if (progress < 1) requestAnimationFrame(step)
  }

  requestAnimationFrame(step)
}

onMounted(() => animate(props.value))
watch(() => props.value, animate)
</script>

<style scoped lang="scss">
@use '@/styles/tokens.scss' as *;

.stat-card {
  display: flex;
  align-items: center;
  gap: 13px;
  padding: 17px 18px;
  background: $bg-card;
  border: 1px solid $border-base;
  border-radius: $radius-md;
  box-shadow: $shadow-card;
  transition:
    transform 0.18s,
    box-shadow 0.18s;

  &:hover {
    transform: translateY(-2px);
    box-shadow: $shadow-float;
  }
}

.stat-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: $radius-md;
  flex-shrink: 0;
}

.stat-value {
  font-size: 22px;
  font-weight: 650;
  line-height: 1.2;
  color: $text-title;
  font-variant-numeric: tabular-nums;
}

.stat-suffix {
  font-size: 13px;
  font-weight: 500;
  color: $text-muted;
  margin-left: 2px;
}

.stat-label {
  margin-top: 3px;
  font-size: 12.5px;
  color: $text-muted;
}
</style>
