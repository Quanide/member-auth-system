<template>
  <!-- 统一设定 Element Plus 的中文语系与尺寸，取代全量 app.use(ElementPlus) 时的 options -->
  <el-config-provider :locale="zhCn" size="default">
    <router-view />
  </el-config-provider>
</template>

<script setup lang="ts">
import zhCn from 'element-plus/es/locale/lang/zh-cn'
</script>
