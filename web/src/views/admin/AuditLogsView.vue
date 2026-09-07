<template>
  <div>
    <h1 class="page-title">全站稽核</h1>
    <p class="page-subtitle">跨會員檢索所有敏感操作紀錄</p>

    <el-card shadow="never" v-loading="loading">
      <template #header>
        <div class="filters">
          <el-input
            v-model="filters.keyword"
            placeholder="搜尋信箱 / IP"
            clearable
            size="small"
            style="width: 200px"
            :prefix-icon="Search"
            @keyup.enter="reload"
            @clear="reload"
          />
          <el-select
            v-model="filters.action"
            placeholder="全部型別"
            clearable
            filterable
            size="small"
            style="width: 170px"
            @change="reload"
          >
            <el-option
              v-for="a in actions"
              :key="a.value"
              :label="a.label"
              :value="a.value"
            />
          </el-select>
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            size="small"
            range-separator="至"
            start-placeholder="開始日期"
            end-placeholder="結束日期"
            value-format="YYYY-MM-DD"
            style="width: 240px"
            @change="reload"
          />
          <el-button size="small" :icon="RefreshCw" @click="reload">重新整理</el-button>
        </div>
      </template>

      <el-empty v-if="!items.length && !loading" description="沒有符合條件的紀錄" :image-size="80" />

      <template v-else>
        <el-table :data="items" size="small">
          <el-table-column label="操作" width="150">
            <template #default="{ row }">
              <el-tag :type="tagType(row.level)" size="small" effect="light">
                {{ row.action_label }}
              </el-tag>
            </template>
          </el-table-column>

          <el-table-column label="會員" min-width="190">
            <template #default="{ row }">
              <div v-if="row.user" class="user-cell">
                <span class="user-name">{{ row.user.display_name }}</span>
                <span class="user-email">{{ row.user.email }}</span>
              </div>
              <span v-else class="text-faint">（已刪除或訪客）</span>
            </template>
          </el-table-column>

          <el-table-column prop="ip_address" label="IP" width="135">
            <template #default="{ row }">
              <span class="mono">{{ row.ip_address ?? '—' }}</span>
            </template>
          </el-table-column>

          <el-table-column label="裝置" min-width="150">
            <template #default="{ row }">
              <span class="dim" :title="row.user_agent ?? ''">{{ shortUserAgent(row.user_agent) }}</span>
            </template>
          </el-table-column>

          <el-table-column label="詳情" min-width="150">
            <template #default="{ row }">
              <span class="dim">{{ describeMeta(row.meta) }}</span>
            </template>
          </el-table-column>

          <el-table-column label="時間" width="175" align="right">
            <template #default="{ row }">
              <span class="mono dim">{{ formatDateTime(row.created_at) }}</span>
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
          @current-change="load"
        />
      </template>
    </el-card>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Search } from '@element-plus/icons-vue'
import { RefreshCw } from 'lucide-vue-next'
import { adminApi, type AdminAuditLog } from '@/api/admin'
import { profileApi } from '@/api/profile'
import { ApiError } from '@/api/client'
import { formatDateTime } from '@/utils/format'
import { describeMeta, shortUserAgent, tagType } from '@/utils/audit'
import type { Pagination } from '@/types'

const items = ref<AdminAuditLog[]>([])
const actions = ref<Array<{ value: string; label: string; level: string }>>([])
const loading = ref(false)
const dateRange = ref<[string, string] | null>(null)

const pagination = ref<Pagination>({ total: 0, per_page: 20, current_page: 1, last_page: 1 })

const filters = reactive<{ keyword: string; action?: string }>({ keyword: '' })

async function load(page = 1): Promise<void> {
  loading.value = true

  try {
    const result = await adminApi.auditLogs({
      page,
      per_page: pagination.value.per_page,
      keyword: filters.keyword || undefined,
      action: filters.action,
      from: dateRange.value?.[0],
      to: dateRange.value?.[1],
    })

    items.value = result.items
    pagination.value = result.pagination
  } catch (error) {
    ElMessage.error(error instanceof ApiError ? error.message : '載入失敗')
  } finally {
    loading.value = false
  }
}

function reload(): void {
  load(1)
}

onMounted(async () => {
  try {
    // 動作清單與會員端共用同一個端點
    actions.value = (await profileApi.activityActions()).actions
  } catch {
    // 篩選項載入失敗不影響主列表，靜默降級
  }

  await load()
})
</script>

<style scoped lang="scss">
@use '@/styles/tokens.scss' as *;

.filters {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.user-cell {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.user-name {
  font-size: 13px;
  color: $text-title;
}

.user-email {
  font-size: 12px;
  color: $text-muted;
}

.text-faint {
  color: $text-faint;
  font-size: 12.5px;
}

.dim {
  font-size: 12.5px;
  color: $text-muted;
}

.mono {
  font-variant-numeric: tabular-nums;
}
</style>
