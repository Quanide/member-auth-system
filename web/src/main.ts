import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'

// Element Plus 采按需引入（见 vite.config.ts 的 ElementPlusResolver），
// 模板里的 el-* 组件与其样式由插件自动处理，这里只补三类例外：
// 1) 基础变量与暗色变量：所有组件共用
// 2) 函数式 API（ElMessage / ElMessageBox / ElLoading）：程式码里显式 import，resolver 扫不到
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
