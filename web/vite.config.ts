import { fileURLToPath, URL } from 'node:url'
import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import AutoImport from 'unplugin-auto-import/vite'
import Components from 'unplugin-vue-components/vite'
import { ElementPlusResolver } from 'unplugin-vue-components/resolvers'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')

  return {
    plugins: [
      vue(),
      // 按需引入 Element Plus，产物体积只含用到的组件
      AutoImport({ resolvers: [ElementPlusResolver()] }),
      Components({ resolvers: [ElementPlusResolver()] }),
    ],
    resolve: {
      alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
    },
    server: {
      port: 5273,
      proxy: {
        // 开发期直连本地 Laravel，避免跨域配置分叉
        '/api': {
          target: env.VITE_DEV_API_TARGET || 'http://127.0.0.1:8000',
          changeOrigin: true,
        },
      },
    },
    build: {
      target: 'es2020',
      chunkSizeWarningLimit: 900,
      rollupOptions: {
        output: {
          // 用函式而非物件形式：物件形式一旦点名套件名（例如 'element-plus'），
          // rollup 会把该套件完整打进产物，按需引入的 tree-shaking 全白做。
          // 函式形式只归类「真的被引用到」的模组，不会额外拉进未使用的部分。
          manualChunks(id: string) {
            if (id.includes('node_modules/echarts') || id.includes('node_modules/zrender')) {
              // 图表只有管理端看板用得到，独立成块避免拖累一般会员的首屏
              return 'echarts'
            }

            if (
              id.includes('node_modules/vue/') ||
              id.includes('node_modules/vue-router') ||
              id.includes('node_modules/pinia')
            ) {
              return 'vue'
            }

            return undefined
          },
        },
      },
    },
  }
})
