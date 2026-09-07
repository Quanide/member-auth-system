<template>
  <!-- 統一設定 Element Plus 的中文語系與尺寸，取代全量 app.use(ElementPlus) 時的 options -->
  <el-config-provider :locale="zhCn" size="default">
    <router-view />
  </el-config-provider>
</template>

<script setup lang="ts">
import zhCn from 'element-plus/es/locale/lang/zh-cn'
</script>
