<template>
  <div class="notfound">
    <div class="code text-gradient">404</div>
    <h1 class="title">頁面不存在</h1>
    <p class="desc">您訪問的頁面可能已被移除，或網址輸入有誤。</p>
    <el-button type="primary" @click="goHome">回到首頁</el-button>
  </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

function goHome(): void {
  router.push(auth.isAuthenticated ? { name: 'dashboard' } : { name: 'login' })
}
</script>

<style scoped lang="scss">
@use '@/styles/tokens.scss' as *;

.notfound {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  padding: 24px;
  background: $bg-page;
  text-align: center;
}

.code {
  font-size: 96px;
  font-weight: 800;
  line-height: 1;
  letter-spacing: 2px;
}

.title {
  margin: 18px 0 8px;
  font-size: 22px;
  font-weight: 650;
  color: $text-title;
}

.desc {
  margin: 0 0 26px;
  font-size: 14px;
  color: $text-muted;
}
</style>
