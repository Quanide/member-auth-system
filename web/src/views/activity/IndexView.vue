<template>
  <div>
    <h1 class="page-title">操作纪录</h1>
    <p class="page-subtitle">您帐号上所有敏感操作的稽核轨迹</p>

    <el-card shadow="never" v-loading="loading">
      <template #header>
        <div class="card-header-row">
          <span>纪录列表</span>
          <div class="filters">
            <el-select
              v-model="filterAction"
              placeholder="全部类型"
              clearable
              size="small"
              style="width: 170px"
              @change="reload"
            >
              <el-option
                v-for="action in actions"
                :key="action.value"
                :label="action.label"
                :value="action.value"
              />
            </el-select>
            <el-button size="small" :icon="RefreshCw" @click="reload">重新整理</el-button>
          </div>
        </div>
      </template>

      <el-empty v-if="!items.length && !loading" description="暂无纪录" :image-size="80" />

      <template v-else>
        <el-table :data="items" size="small">
          <el-table-column label="操作" min-width="150">
            <template #default="{ row }">
              <el-tag :type="tagType(row.level)" size="small" effect="light">
                {{ row.action_label }}
              </el-tag>
            </template>
          </el-table-column>

          <el-table-column prop="ip_address" label="IP" width="140">
            <template #default="{ row }">{{ row.ip_address ?? '—' }}</template>
          </el-table-column>

          <el-table-column label="装置" min-width="180">
            <template #default="{ row }">
              <span class="ua-cell" :title="row.user_agent ?? ''">
                {{ shortUserAgent(row.user_agent) }}
              </span>
            </template>
          </el-table-column>

          <el-table-column label="详情" min-width="150">
            <template #default="{ row }">
              <span class="meta-cell">{{ describeMeta(row.meta) }}</span>
            </template>
          </el-table-column>

          <el-table-column label="时间" width="230" align="right">
            <template #default="{ row }">
              <span class="time-cell">{{ formatDateTime(row.created_at) }}</span>
              <span class="time-rel">{{ fromNow(row.created_at) }}</span>
            </template>
          </el-table-column>
        </el-table>

        <el-pagination
          v-if="pagination.last_page > 1"
          background
          layout="prev, pager, next, total"
          :current-page="pagination.current_page"
          :page-size="pagination.per_page"
          :total="pagination.total"
          @current-change="onPageChange"
        />
      </template>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { RefreshCw } from 'lucide-vue-next'
import { profileApi } from '@/api/profile'
import { ApiError } from '@/api/client'
import { formatDateTime, fromNow } from '@/utils/format'
import type { AuditLog, Pagination } from '@/types'

const items = ref<AuditLog[]>([])
const actions = ref<Array<{ value: string; label: string; level: string }>>([])
const filterAction = ref<string>()
const loading = ref(false)

const pagination = ref<Pagination>({ total: 0, per_page: 20, current_page: 1, last_page: 1 })

function tagType(level: string): 'success' | 'warning' | 'danger' | 'info' {
  return (['success', 'warning', 'danger', 'info'] as const).includes(
    level as 'success' | 'warning' | 'danger' | 'info',
  )
    ? (level as 'success' | 'warning' | 'danger' | 'info')
    : 'info'
}

/** UA 字串太长，列表里只显示能辨认装置的关键片段 */
function shortUserAgent(ua: string | null): string {
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

/** 把后端存的 meta 翻成人看得懂的一句话 */
function describeMeta(meta: Record<string, unknown> | null): string {
  if (!meta) return '—'

  if (Array.isArray(meta.fields)) {
    const labels: Record<string, string> = {
      name: '姓名',
      nickname: '昵称',
      phone: '手机',
      birthday: '生日',
      gender: '性别',
      bio: '简介',
    }

    return '修改了：' + meta.fields.map((f) => labels[String(f)] ?? String(f)).join('、')
  }

  if (typeof meta.reason === 'string') {
    const reasons: Record<string, string> = {
      bad_password: '密码错误',
      user_not_found: '帐号不存在',
      locked: '帐号锁定中',
      disabled: '帐号已停权',
    }

    return reasons[meta.reason] ?? meta.reason
  }

  if (typeof meta.new_email === 'string') return `新邮箱：${meta.new_email}`
  if (typeof meta.locked_until === 'string') return '帐号已被暂时锁定'
  if (typeof meta.count === 'number') return `共 ${meta.count} 个装置`

  return '—'
}

async function load(page = 1): Promise<void> {
  loading.value = true

  try {
    const result = await profileApi.activities({
      page,
      per_page: pagination.value.per_page,
      action: filterAction.value,
    })

    items.value = result.items
    pagination.value = result.pagination
  } catch (error) {
    ElMessage.error(error instanceof ApiError ? error.message : '载入失败')
  } finally {
    loading.value = false
  }
}

function reload(): void {
  load(1)
}

function onPageChange(page: number): void {
  load(page)
}

onMounted(async () => {
  try {
    const { actions: list } = await profileApi.activityActions()
    actions.value = list
  } catch {
    // 筛选项载入失败不影响主列表，静默降级
  }

  await load()
})
</script>

<style scoped lang="scss">
@use '@/styles/tokens.scss' as *;

.card-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.filters {
  display: flex;
  align-items: center;
  gap: 8px;
}

.ua-cell,
.meta-cell {
  font-size: 12.5px;
  color: $text-muted;
}

.time-cell {
  display: block;
  font-size: 12.5px;
  color: $text-body;
  font-variant-numeric: tabular-nums;
}

.time-rel {
  display: block;
  font-size: 11.5px;
  color: $text-faint;
}
</style>
