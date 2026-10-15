import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

export default defineConfig(() => {
  if (process.env.VERCEL) {
    const apiBaseUrl = process.env.VITE_API_BASE_URL
    if (!apiBaseUrl) throw new Error('Set VITE_API_BASE_URL in Vercel before building the production frontend.')
    const apiUrl = new URL(apiBaseUrl)
    if (apiUrl.protocol !== 'https:' || !apiUrl.pathname.replace(/\/$/, '').endsWith('/api') || /YOUR-|example\.com|localhost|127\.0\.0\.1/i.test(apiUrl.hostname)) {
      throw new Error('VITE_API_BASE_URL must be an HTTPS Render API URL ending in /api.')
    }
  }

  return { plugins: [react()] }
})
