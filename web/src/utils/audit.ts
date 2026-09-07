/** 稽核紀錄的顯示輔助：會員端「操作紀錄」與管理端「全站稽核」共用 */

export function tagType(level: string): 'success' | 'warning' | 'danger' | 'info' {
  const allowed = ['success', 'warning', 'danger', 'info'] as const

  return (allowed as readonly string[]).includes(level)
    ? (level as (typeof allowed)[number])
    : 'info'
}

/** UA 字串太長，列表裡只顯示能辨認裝置的關鍵片段 */
export function shortUserAgent(ua: string | null): string {
  if (!ua) return '—'

  const os = /Windows/.test(ua)
    ? 'Windows'
    : /iPhone/.test(ua)
      ? 'iPhone'
      : /iPad/.test(ua)
        ? 'iPad'
        : /Android/.test(ua)
          ? 'Android'
          : /Mac OS X/.test(ua)
            ? 'macOS'
            : /Linux/.test(ua)
              ? 'Linux'
              : '未知系統'

  const browser = /Edg\//.test(ua)
    ? 'Edge'
    : /Chrome\//.test(ua) && !/Chromium/.test(ua)
      ? 'Chrome'
      : /Firefox\//.test(ua)
        ? 'Firefox'
        : /Safari\//.test(ua) && !/Chrome/.test(ua)
          ? 'Safari'
          : '未知瀏覽器'

  return `${os} · ${browser}`
}

const FIELD_LABELS: Record<string, string> = {
  name: '姓名',
  nickname: '暱稱',
  phone: '手機',
  birthday: '生日',
  gender: '性別',
  bio: '簡介',
}

const FAIL_REASONS: Record<string, string> = {
  bad_password: '密碼錯誤',
  user_not_found: '帳號不存在',
  locked: '帳號鎖定中',
  disabled: '帳號已停權',
}

const ADMIN_ACTIONS: Record<string, string> = {
  update_status: '變更狀態',
  update_role: '變更角色',
  unlock: '解除鎖定',
  force_logout: '強制登出',
  delete: '刪除會員',
}

/** 把後端存的 meta 翻成人看得懂的一句話 */
export function describeMeta(meta: Record<string, unknown> | null): string {
  if (!meta) return '—'

  if (typeof meta.admin_action === 'string') {
    const label = ADMIN_ACTIONS[meta.admin_action] ?? meta.admin_action
    const target = meta.target_user_id ? `　會員 #${meta.target_user_id}` : ''

    return `${label}${target}`
  }

  if (Array.isArray(meta.fields)) {
    return '修改了：' + meta.fields.map((f) => FIELD_LABELS[String(f)] ?? String(f)).join('、')
  }

  if (typeof meta.reason === 'string') {
    return FAIL_REASONS[meta.reason] ?? meta.reason
  }

  if (typeof meta.new_email === 'string') return `新信箱：${meta.new_email}`
  if (typeof meta.locked_until === 'string') return '帳號已被暫時鎖定'
  if (typeof meta.remaining === 'number') return `恢復碼剩餘 ${meta.remaining} 組`
  if (typeof meta.count === 'number') return `共 ${meta.count} 個裝置`

  return '—'
}
