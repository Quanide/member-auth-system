import dayjs from 'dayjs'
import relativeTime from 'dayjs/plugin/relativeTime'
import 'dayjs/locale/zh-cn'

dayjs.extend(relativeTime)
dayjs.locale('zh-cn')

export function formatDateTime(value: string | null | undefined): string {
  if (!value) return '—'

  return dayjs(value).format('YYYY-MM-DD HH:mm:ss')
}

export function formatDate(value: string | null | undefined): string {
  if (!value) return '—'

  return dayjs(value).format('YYYY-MM-DD')
}

/** 「3 分钟前」这类相对时间，用于活动列表 */
export function fromNow(value: string | null | undefined): string {
  if (!value) return '—'

  return dayjs(value).fromNow()
}
