<template>
  <div v-loading="loading">
    <div class="page-head">
      <div>
        <h1 class="page-title">数据看板</h1>
        <p class="page-subtitle">全站会员规模、登入状况与装置分布</p>
      </div>
      <el-button :icon="RefreshCw" @click="load">重新整理</el-button>
    </div>

    <!-- 统计卡片 -->
    <div class="stat-grid">
      <StatCard
        v-for="s in statCards"
        :key="s.label"
        :label="s.label"
        :value="s.value"
        :icon="s.icon"
        :color="s.color"
        :tint="s.tint"
      />
    </div>

    <!-- 趋势图 -->
    <div class="chart-grid">
      <el-card shadow="never" class="chart-card">
        <template #header>
          <div class="card-head">
            <span>注册趋势</span>
            <span class="card-head-hint">近 30 天</span>
          </div>
        </template>
        <VChart v-if="stats" class="chart" :option="registrationOption" autoresize />
      </el-card>

      <el-card shadow="never" class="chart-card">
        <template #header>
          <div class="card-head">
            <span>登入状况</span>
            <span class="card-head-hint">近 14 天</span>
          </div>
        </template>
        <VChart v-if="stats" class="chart" :option="loginOption" autoresize />
      </el-card>
    </div>

    <div class="chart-grid">
      <el-card shadow="never" class="chart-card">
        <template #header>会员状态分布</template>
        <VChart
          v-if="stats && stats.status_distribution.length"
          class="chart chart--sm"
          :option="statusOption"
          autoresize
        />
        <el-empty v-else description="暂无资料" :image-size="70" />
      </el-card>

      <el-card shadow="never" class="chart-card">
        <template #header>
          <div class="card-head">
            <span>登入装置分布</span>
            <span class="card-head-hint">近 30 天</span>
          </div>
        </template>
        <VChart
          v-if="stats && stats.device_distribution.length"
          class="chart chart--sm"
          :option="deviceOption"
          autoresize
        />
        <el-empty v-else description="暂无登入纪录" :image-size="70" />
      </el-card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import VChart from 'vue-echarts'
import {
  CheckCircle2,
  Lock,
  MailWarning,
  RefreshCw,
  UserPlus,
  Users,
  Wifi,
} from 'lucide-vue-next'
import StatCard from '@/components/StatCard.vue'
import { adminApi, type AdminStats } from '@/api/admin'
import { ApiError } from '@/api/client'
import '@/utils/echarts'
import { CHART_PALETTE, baseTextStyle, baseTooltip, CHART_COLORS } from '@/utils/echarts'

const stats = ref<AdminStats | null>(null)
const loading = ref(true)

const statCards = computed(() => {
  const o = stats.value?.overview

  return [
    { label: '会员总数', value: o?.total_users ?? 0, icon: Users, color: '#6366F1', tint: '#EFF0FE' },
    { label: '今日新增', value: o?.new_today ?? 0, icon: UserPlus, color: '#8B5CF6', tint: '#F3EEFE' },
    { label: '在线装置', value: o?.online ?? 0, icon: Wifi, color: '#16A34A', tint: '#F0FDF4' },
    { label: '已验证邮箱', value: o?.verified ?? 0, icon: CheckCircle2, color: '#0891B2', tint: '#ECFEFF' },
    { label: '待验证', value: o?.unverified ?? 0, icon: MailWarning, color: '#EA580C', tint: '#FFF7ED' },
    { label: '锁定 / 停权', value: (o?.locked ?? 0) + (o?.disabled ?? 0), icon: Lock, color: '#F53F3F', tint: '#FFF1F0' },
  ]
})

/** 折线图共用的坐标轴设定，避免四张图各写一遍 */
const axisBase = {
  xAxis: {
    type: 'category' as const,
    boundaryGap: false,
    axisLine: { lineStyle: { color: '#ECEDF5' } },
    axisTick: { show: false },
    axisLabel: { ...baseTextStyle, interval: 'auto' as const },
  },
  yAxis: {
    type: 'value' as const,
    minInterval: 1, // 人数是整数，避免出现 0.5 这种刻度
    splitLine: { lineStyle: { color: '#F4F4F9' } },
    axisLabel: baseTextStyle,
  },
  grid: { left: 8, right: 16, top: 24, bottom: 8, containLabel: true },
}

const registrationOption = computed(() => {
  const trend = stats.value?.registration_trend ?? []

  return {
    ...axisBase,
    tooltip: baseTooltip,
    xAxis: { ...axisBase.xAxis, data: trend.map((d) => d.date.slice(5)) },
    series: [
      {
        name: '注册人数',
        type: 'line',
        smooth: true,
        symbol: 'circle',
        symbolSize: 6,
        showSymbol: false,
        data: trend.map((d) => d.count),
        lineStyle: { width: 2.5, color: CHART_COLORS.primary },
        itemStyle: { color: CHART_COLORS.primary },
        areaStyle: {
          color: {
            type: 'linear',
            x: 0, y: 0, x2: 0, y2: 1,
            colorStops: [
              { offset: 0, color: 'rgba(99,102,241,0.24)' },
              { offset: 1, color: 'rgba(99,102,241,0.02)' },
            ],
          },
        },
      },
    ],
  }
})

const loginOption = computed(() => {
  const trend = stats.value?.login_trend

  return {
    ...axisBase,
    tooltip: baseTooltip,
    legend: {
      data: ['成功', '失败'],
      top: 0,
      right: 0,
      icon: 'roundRect',
      itemWidth: 10,
      itemHeight: 3,
      textStyle: baseTextStyle,
    },
    grid: { ...axisBase.grid, top: 34 },
    xAxis: { ...axisBase.xAxis, data: (trend?.dates ?? []).map((d) => d.slice(5)) },
    series: [
      {
        name: '成功',
        type: 'line',
        smooth: true,
        showSymbol: false,
        data: trend?.success ?? [],
        lineStyle: { width: 2.5, color: CHART_COLORS.success },
        itemStyle: { color: CHART_COLORS.success },
      },
      {
        name: '失败',
        type: 'line',
        smooth: true,
        showSymbol: false,
        data: trend?.failed ?? [],
        lineStyle: { width: 2.5, color: CHART_COLORS.danger },
        itemStyle: { color: CHART_COLORS.danger },
      },
    ],
  }
})

/** 圆环图共用设定 */
function donutOption(data: Array<{ name: string; value: number }>) {
  return {
    color: CHART_PALETTE,
    tooltip: { ...baseTooltip, trigger: 'item' as const },
    legend: {
      orient: 'vertical' as const,
      right: 0,
      top: 'center' as const,
      icon: 'circle',
      itemWidth: 8,
      itemHeight: 8,
      textStyle: baseTextStyle,
    },
    series: [
      {
        type: 'pie',
        radius: ['52%', '74%'],
        center: ['38%', '50%'],
        avoidLabelOverlap: true,
        itemStyle: { borderRadius: 6, borderColor: '#fff', borderWidth: 2 },
        label: { show: false },
        emphasis: {
          label: { show: true, fontSize: 15, fontWeight: 600, color: '#1D2129' },
        },
        data,
      },
    ],
  }
}

const statusOption = computed(() => donutOption(stats.value?.status_distribution ?? []))
const deviceOption = computed(() => donutOption(stats.value?.device_distribution ?? []))

async function load(): Promise<void> {
  loading.value = true

  try {
    stats.value = await adminApi.stats()
  } catch (error) {
    ElMessage.error(error instanceof ApiError ? error.message : '载入失败')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<style scoped lang="scss">
@use '@/styles/tokens.scss' as *;

.page-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.stat-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 14px;
  margin-bottom: 18px;
}

.chart-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
  gap: 14px;
  margin-bottom: 14px;
}

.card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.card-head-hint {
  font-size: 12px;
  font-weight: 400;
  color: $text-faint;
}

.chart {
  height: 260px;
  width: 100%;
}

.chart--sm {
  height: 210px;
}
</style>
