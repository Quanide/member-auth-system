import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'

// Element Plus 採按需引入（見 vite.config.ts 的 ElementPlusResolver），
// 模板裡的 el-* 組件與其樣式由插件自動處理，這裡只補三類例外：
// 1) 基礎變量與暗色變量：所有組件共用
// 2) 函數式 API（ElMessage / ElMessageBox / ElLoading）：程式碼裡顯式 import，resolver 掃不到
import 'element-plus/theme-chalk/base.css'
import 'element-plus/theme-chalk/el-message.css'
import 'element-plus/theme-chalk/el-message-box.css'
import 'element-plus/theme-chalk/el-loading.css'
import 'element-plus/theme-chalk/el-overlay.css'
import 'element-plus/theme-chalk/el-popper.css'

import 'nprogress/nprogress.css'
import './styles/global.scss'

const app = createApp(App)

app.use(createPinia())
app.use(router)

app.mount('#app')
