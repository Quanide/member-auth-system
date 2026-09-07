<template>
  <div>
    <div class="page-head">
      <div>
        <h1 class="page-title">会员管理</h1>
        <p class="page-subtitle">搜寻、停权、调整角色与强制登出</p>
      </div>
    </div>

    <el-card shadow="never" v-loading="loading">
      <template #header>
        <div class="filters">
          <el-input
            v-model="filters.keyword"
            placeholder="搜寻邮箱 / 姓名 / 手机"
            clearable
            size="small"
            style="width: 230px"
            :prefix-icon="Search"
            @keyup.enter="reload"
            @clear="reload"
          />
          <el-select v-model="filters.status" placeholder="全部状态" clearable size="small" style="width: 120px" @change="reload">
            <el-option label="正常" value="active" />
            <el-option label="已停权" value="disabled" />
          </el-select>
          <el-select v-model="filters.role" placeholder="全部角色" clearable size="small" style="width: 120px" @change="reload">
            <el-option label="会员" value="member" />
            <el-option label="管理员" value="admin" />
          </el-select>
          <el-select v-model="filters.verified" placeholder="验证状态" clearable size="small" style="width: 130px" @change="reload">
            <el-option label="已验证" :value="true" />
            <el-option label="未验证" :value="false" />
          </el-select>
          <el-button size="small" :icon="RefreshCw" @click="reload">重新整理</el-button>
        </div>
      </template>

      <el-table :data="items" size="small" @row-click="openDetail">
        <el-table-column label="会员" min-width="220">
          <template #default="{ row }">
            <div class="member-cell">
              <el-avatar :size="30" :src="row.avatar_url ?? undefined" class="member-avatar">
                {{ row.display_name?.charAt(0)?.toUpperCase() }}
              </el-avatar>
              <div class="member-text">
                <span class="member-name">{{ row.display_name }}</span>
                <span class="member-email">{{ row.email }}</span>
              </div>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="角色" width="90">
          <template #default="{ row }">
            <el-tag :type="row.role === 'admin' ? 'warning' : 'info'" size="small" effect="light">
              {{ row.role_label }}
            </el-tag>
          </template>
        </el-table-column>

        <el-table-column label="状态" width="150">
          <template #default="{ row }">
            <el-tag :type="row.status === 'active' ? 'success' : 'danger'" size="small" effect="light">
              {{ row.status_label }}
            </el-tag>
            <el-tag v-if="!row.email_verified" type="warning" size="small" effect="plain" class="ml-4">
              未验证
            </el-tag>
          </template>
        </el-table-column>

        <el-table-column label="2FA" width="70" align="center">
          <template #default="{ row }">
            <ShieldCheck v-if="row.two_factor_enabled" :size="15" class="icon-on" />
            <span v-else class="text-faint">—</span>
          </template>
        </el-table-column>

        <el-table-column label="上次登入" width="165">
          <template #default="{ row }">
            <span class="dim">{{ row.last_login_at ? formatDateTime(row.last_login_at) : '从未登入' }}</span>
          </template>
        </el-table-column>

        <el-table-column label="操作" width="190" align="right">
          <template #default="{ row }">
            <div class="row-actions" @click.stop>
              <el-button link type="primary" size="small" @click="openDetail(row)">详情</el-button>

              <el-dropdown trigger="click" @command="(cmd: string) => handleCommand(cmd, row)">
                <el-button link type="primary" size="small">
                  更多<ChevronDown :size="12" style="margin-left: 2px" />
                </el-button>
                <template #dropdown>
                  <el-dropdown-menu>
                    <el-dropdown-item v-if="row.status === 'active'" command="disable" :disabled="row.id === auth.user?.id">
                      停权
                    </el-dropdown-item>
                    <el-dropdown-item v-else command="enable" :disabled="row.id === auth.user?.id">
                      恢复正常
                    </el-dropdown-item>
                    <el-dropdown-item command="role" :disabled="row.id === auth.user?.id">
                      {{ row.role === 'admin' ? '降为会员' : '设为管理员' }}
                    </el-dropdown-item>
                    <el-dropdown-item command="unlock" divided>解除锁定</el-dropdown-item>
                    <el-dropdown-item command="logout">强制登出</el-dropdown-item>
                    <el-dropdown-item command="delete" divided :disabled="row.id === auth.user?.id">
                      <span class="danger-text">删除会员</span>
                    </el-dropdown-item>
                  </el-dropdown-menu>
                </template>
              </el-dropdown>
            </div>
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
    </el-card>

    <!-- 会员详情 -->
    <el-drawer v-model="detailVisible" size="480px" :title="detail?.user.display_name ?? '会员详情'">
      <div v-if="detail" v-loading="detailLoading">
        <el-descriptions :column="1" border size="small">
          <el-descriptions-item label="邮箱">{{ detail.user.email }}</el-descriptions-item>
          <el-descriptions-item label="会员编号">#{{ detail.user.id }}</el-descriptions-item>
          <el-descriptions-item label="姓名">{{ detail.user.name }}</el-descriptions-item>
          <el-descriptions-item label="手机">{{ detail.user.phone ?? '—' }}</el-descriptions-item>
          <el-descriptions-item label="角色">{{ detail.user.role_label }}</el-descriptions-item>
          <el-descriptions-item label="状态">{{ detail.user.status_label }}</el-descriptions-item>
          <el-descriptions-item label="双因素">
            {{ detail.user.two_factor_enabled ? '已启用' : '未启用' }}
          </el-descriptions-item>
          <el-descriptions-item label="登入中装置">{{ detail.sessions_count }}</el-descriptions-item>
          <el-descriptions-item label="注册时间">{{ formatDateTime(detail.user.created_at) }}</el-descriptions-item>
          <el-descriptions-item label="上次登入">
            {{ detail.user.last_login_at ? formatDateTime(detail.user.last_login_at) : '从未登入' }}
          </el-descriptions-item>
        </el-descriptions>

        <h3 class="drawer-section">最近操作</h3>
        <el-empty v-if="!detail.recent_activities.length" description="暂无纪录" :image-size="60" />
        <ul v-else class="activity-list">
          <li v-for="log in detail.recent_activities" :key="log.id" class="activity-item">
            <el-tag :type="tagType(log.level)" size="small" effect="light">{{ log.action_label }}</el-tag>
            <span class="activity-meta">{{ log.ip_address ?? '—' }}</span>
            <span class="activity-time">{{ fromNow(log.created_at) }}</span>
          </li>
        </ul>
      </div>
    </el-drawer>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search } from '@element-plus/icons-vue'
import { ChevronDown, RefreshCw, ShieldCheck } from 'lucide-vue-next'
import { adminApi } from '@/api/admin'
import { ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { formatDateTime, fromNow } from '@/utils/format'
import type { AuditLog, Pagination, User } from '@/types'

const auth = useAuthStore()

const items = ref<User[]>([])
const loading = ref(false)
const pagination = ref<Pagination>({ total: 0, per_page: 20, current_page: 1, last_page: 1 })

const filters = reactive<{
  keyword: string
  status?: string
  role?: string
  verified?: boolean
}>({ keyword: '' })

const detailVisible = ref(false)
const detailLoading = ref(false)
const detail = ref<{ user: User; sessions_count: number; recent_activities: AuditLog[] } | null>(null)

function tagType(level: string): 'success' | 'warning' | 'danger' | 'info' {
  return (['success', 'warning', 'danger', 'info'] as const).includes(level as never)
    ? (level as 'success' | 'warning' | 'danger' | 'info')
    : 'info'
}

async function load(page = 1): Promise<void> {
  loading.value = true

  try {
    const result = await adminApi.users({
      page,
      per_page: pagination.value.per_page,
      keyword: filters.keyword || undefined,
      status: filters.status,
      role: filters.role,
      verified: filters.verified,
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

async function openDetail(row: User): Promise<void> {
  detailVisible.value = true
  detailLoading.value = true

  try {
    detail.value = await adminApi.user(row.id)
  } catch (error) {
    ElMessage.error(error instanceof ApiError ? error.message : '载入失败')
    detailVisible.value = false
  } finally {
    detailLoading.value = false
  }
}

/** 破坏性操作一律先确认，避免手滑 */
async function confirm(message: string, title: string, danger = false): Promise<boolean> {
  try {
    await ElMessageBox.confirm(message, title, {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: danger ? 'error' : 'warning',
    })

    return true
  } catch {
    return false
  }
}

async function handleCommand(command: string, row: User): Promise<void> {
  try {
    switch (command) {
      case 'disable':
        if (!(await confirm(`确定要停权「${row.display_name}」吗？该会员将立即被登出。`, '停权会员'))) return
        await adminApi.updateStatus(row.id, 'disabled')
        ElMessage.success('已停权')
        break

      case 'enable':
        await adminApi.updateStatus(row.id, 'active')
        ElMessage.success('已恢复正常')
        break

      case 'role': {
        const next = row.role === 'admin' ? 'member' : 'admin'
        const label = next === 'admin' ? '设为管理员' : '降为会员'
        if (!(await confirm(`确定要将「${row.display_name}」${label}吗？`, '变更角色'))) return
        await adminApi.updateRole(row.id, next)
        ElMessage.success('角色已变更')
        break
      }

      case 'unlock':
        await adminApi.unlock(row.id)
        ElMessage.success('已解除锁定')
        break

      case 'logout':
        if (!(await confirm(`确定要强制「${row.display_name}」在所有装置登出吗？`, '强制登出'))) return
        ElMessage.success((await adminApi.forceLogout(row.id)).message)
        break

      case 'delete':
        if (!(await confirm(`确定要删除「${row.display_name}」吗？此操作为软删除，稽核纪录会保留。`, '删除会员', true))) return
        await adminApi.remove(row.id)
        ElMessage.success('已删除')
        break
    }

    await load(pagination.value.current_page)
  } catch (error) {
    ElMessage.error(error instanceof ApiError ? error.message : '操作失败')
  }
}

onMounted(() => load())
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

.filters {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.member-cell {
  display: flex;
  align-items: center;
  gap: 10px;
}

.member-avatar {
  background: $brand-gradient !important;
  color: #fff;
  font-weight: 600;
  flex-shrink: 0;
}

.member-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.member-name {
  font-size: 13px;
  color: $text-title;
  font-weight: 500;
}

.member-email {
  font-size: 12px;
  color: $text-muted;
}

.ml-4 {
  margin-left: 4px;
}

.icon-on {
  color: $success;
  vertical-align: middle;
}

.text-faint {
  color: $text-faint;
}

.dim {
  font-size: 12.5px;
  color: $text-muted;
  font-variant-numeric: tabular-nums;
}

.row-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
}

.danger-text {
  color: $danger;
}

.drawer-section {
  margin: 22px 0 12px;
  font-size: 14px;
  font-weight: 600;
  color: $text-title;
}

.activity-list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.activity-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 0;
  border-bottom: 1px solid $border-light;

  &:last-child {
    border-bottom: none;
  }
}

.activity-meta {
  flex: 1;
  font-size: 12px;
  color: $text-muted;
}

.activity-time {
  font-size: 12px;
  color: $text-faint;
}

:deep(.el-table__row) {
  cursor: pointer;
}
</style>
