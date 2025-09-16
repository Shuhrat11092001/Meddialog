import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  base: './', // Это важно для корректных путей в production
  build: {
    outDir: '../dist', // Будем собирать файлы на уровень выше, в папку dist
    emptyOutDir: true, // Очищаем папку назначения перед сборкой
  },
  server: {
    port: 3000, // Порт для dev-сервера
    open: true // Автоматически открывать браузер
  }
})
