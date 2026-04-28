import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
  ],
  server: {
    host: true,        // Exposes to Network (0.0.0.0) so phone can connect
    port: 5173,        // Keeps port consistent
    strictPort: true,  // Prevents port switching if 5173 is busy

    // Optional: Proxy API requests to backend to avoid CORS issues locally
    proxy: {
      '/api': {
        target: 'http://192.168.60.199',
        secure: false,
        changeOrigin: true
      }
    }
  },

  // ======================================================================
  // BUILD OPTIMIZATIONS — Code Splitting
  // ======================================================================
  build: {
    rollupOptions: {
      output: {
        manualChunks: {
          // Split heavy vendor libraries into their own cache-friendly chunks
          'vendor': ['vue', 'vue-router'],
          'http': ['axios'],
        }
      }
    }
  }
})