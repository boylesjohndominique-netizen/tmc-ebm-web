import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  server: {
    host: true,
    port: 5180,
    allowedHosts: true,
    proxy: {
      '/api': { target: 'http://127.0.0.1:8000', changeOrigin: true },
      '/app': { target: 'http://127.0.0.1:8080', ws: true, changeOrigin: true }
    }
  },
  build: {
    chunkSizeWarningLimit: 600,
    rollupOptions: {
      output: {
        manualChunks: {
          'vendor-react': ['react', 'react-dom', 'react-router-dom'],
          'vendor-network': ['axios', 'laravel-echo', 'pusher-js'],
          'vendor-icons': ['lucide-react'],
          'vendor-scanner': ['@zxing/browser'],
        }
      }
    }
  }
})
