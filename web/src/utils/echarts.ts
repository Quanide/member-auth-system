/**
 * ECharts 按需注册。
 * 只引入实际用到的图表与元件，完整 echarts 包超过 1 MB，
 * 这样打包后只会带上折线、圆环两种图。
 */
import { use } from 'echarts/core'
import { LineChart, PieChart } from 'echarts/charts'
import {
  GridComponent,
  LegendComponent,
  TitleComponent,
  TooltipComponent,
} from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'

use([
  LineChart,
  PieChart,
  GridComponent,
  TooltipComponent,
  LegendComponent,
  TitleComponent,
  CanvasRenderer,
])

/** 与全站蓝紫主题一致的图表配色 */
export const CHART_COLORS = {
  primary: '#6366F1',
  secondary: '#8B5CF6',
  success: '#00B42A',
  danger: '#F53F3F',
  warning: '#FF7D00',
  muted: '#C9CDD4',
} as const

export const CHART_PALETTE = [
  CHART_COLORS.primary,
  CHART_COLORS.secondary,
  '#22D3EE',
  CHART_COLORS.warning,
  CHART_COLORS.success,
  CHART_COLORS.danger,
]

/** 图表共用的基础样式，避免每个 option 重复写一遍 */
export const baseTextStyle = {
  fontFamily: 'Inter, -apple-system, "PingFang SC", sans-serif',
  color: '#86909C',
  fontSize: 12,
}

export const baseTooltip = {
  trigger: 'axis' as const,
  backgroundColor: 'rgba(19, 20, 31, 0.92)',
  borderWidth: 0,
  padding: [8, 12],
  textStyle: { color: '#fff', fontSize: 12 },
  axisPointer: {
    type: 'line' as const,
    lineStyle: { color: 'rgba(99, 102, 241, 0.35)' },
  },
}
