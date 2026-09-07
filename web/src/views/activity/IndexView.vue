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
import { describeMeta, shortUserAgent, tagType } from '@/utils/audit'
import type { AuditLog, Pagination } from '@/types'

const items = ref<AuditLog[]>([])
const actions = ref<Array<{ value: string; label: string; level: string }>>([])
const filterAction = ref<string>()
const loading = ref(false)

const pagination = ref<Pagination>({ total: 0, per_page: 20, current_page: 1, last_page: 1 })

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
