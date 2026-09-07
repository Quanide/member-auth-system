/** 稽核纪录的显示辅助：会员端「操作纪录」与管理端「全站稽核」共用 */

export function tagType(level: string): 'success' | 'warning' | 'danger' | 'info' {
  const allowed = ['success', 'warning', 'danger', 'info'] as const

  return (allowed as readonly string[]).includes(level)
    ? (level as (typeof allowed)[number])
    : 'info'
}

/** UA 字串太长，列表里只显示能辨认装置的关键片段 */
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
              : '未知系统'

  const browser = /Edg\//.test(ua)
    ? 'Edge'
    : /Chrome\//.test(ua) && !/Chromium/.test(ua)
      ? 'Chrome'
      : /Firefox\//.test(ua)
        ? 'Firefox'
        : /Safari\//.test(ua) && !/Chrome/.test(ua)
          ? 'Safari'
          : '未知浏览器'

  return `${os} · ${browser}`
}

const FIELD_LABELS: Record<string, string> = {
  name: '姓名',
  nickname: '昵称',
  phone: '手机',
  birthday: '生日',
  gender: '性别',
  bio: '简介',
}

const FAIL_REASONS: Record<string, string> = {
  bad_password: '密码错误',
  user_not_found: '帐号不存在',
  locked: '帐号锁定中',
  disabled: '帐号已停权',
}

const ADMIN_ACTIONS: Record<string, string> = {
  update_status: '变更状态',
  update_role: '变更角色',
  unlock: '解除锁定',
  force_logout: '强制登出',
  delete: '删除会员',
}

/** 把后端存的 meta 翻成人看得懂的一句话 */
export function describeMeta(meta: Record<string, unknown> | null): string {
  if (!meta) return '—'

  if (typeof meta.admin_action === 'string') {
    const label = ADMIN_ACTIONS[meta.admin_action] ?? meta.admin_action
    const target = meta.target_user_id ? `　会员 #${meta.target_user_id}` : ''

    return `${label}${target}`
  }

  if (Array.isArray(meta.fields)) {
    return '修改了：' + meta.fields.map((f) => FIELD_LABELS[String(f)] ?? String(f)).join('、')
  }

  if (typeof meta.reason === 'string') {
    return FAIL_REASONS[meta.reason] ?? meta.reason
  }

  if (typeof meta.new_email === 'string') return `新邮箱：${meta.new_email}`
  if (typeof meta.locked_until === 'string') return '帐号已被暂时锁定'
  if (typeof meta.remaining === 'number') return `恢复码剩余 ${meta.remaining} 组`
  if (typeof meta.count === 'number') return `共 ${meta.count} 个装置`

  return '—'
}
