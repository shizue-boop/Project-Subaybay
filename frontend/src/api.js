const baseUrl = (import.meta.env.VITE_API_BASE_URL || (import.meta.env.DEV ? 'http://127.0.0.1:8000/api' : '')).replace(/\/$/, '')
const pendingGetRequests = new Map()

export const getToken = () => sessionStorage.getItem('subaybay_api_token')
const USER_KEY = 'subaybay_api_user'
const USER_CACHE_AT_KEY = 'subaybay_api_user_cached_at'

export const getUser = () => {
  try {
    const user = JSON.parse(sessionStorage.getItem(USER_KEY) || 'null')
    return user && typeof user === 'object' ? user : null
  } catch {
    return null
  }
}

export const isUserCacheFresh = () => {
  const cachedAt = Number(sessionStorage.getItem(USER_CACHE_AT_KEY) || 0)
  return cachedAt > 0 && Date.now() - cachedAt < 5 * 60 * 1000
}

export const saveUser = (user) => {
  sessionStorage.setItem(USER_KEY, JSON.stringify(user))
  sessionStorage.setItem(USER_CACHE_AT_KEY, String(Date.now()))
}
export const saveToken = (token) => sessionStorage.setItem('subaybay_api_token', token)
export const clearToken = () => {
  sessionStorage.removeItem('subaybay_api_token')
  sessionStorage.removeItem(USER_KEY)
  sessionStorage.removeItem(USER_CACHE_AT_KEY)
}

async function sendApiRequest(path, { method = 'GET', body, headers = {} } = {}) {
  if (!baseUrl) throw new Error('VITE_API_BASE_URL is not configured for this deployment.')
  const requestHeaders = { Accept: 'application/json', ...headers }
  const token = getToken()
  if (token) requestHeaders.Authorization = `Bearer ${token}`
  let payload = body
  if (body && !(body instanceof FormData)) {
    requestHeaders['Content-Type'] = 'application/json'
    payload = JSON.stringify(body)
  }
  const response = await fetch(`${baseUrl}${path}`, { method, headers: requestHeaders, body: payload })
  const result = await response.json().catch(() => ({}))
  if (!response.ok) {
    if (response.status === 401) {
      clearToken()
      if (!['/', '/index.php'].includes(window.location.pathname)) window.location.replace('/index.php')
    }
    const error = new Error(result.message || 'Request could not be completed.')
    error.status = response.status
    error.errors = result.errors
    throw error
  }
  return result
}

export function api(path, options = {}) {
  const method = (options.method || 'GET').toUpperCase()
  if (method !== 'GET') return sendApiRequest(path, options)

  const key = `${getToken() || ''}:${path}:${JSON.stringify(options.headers || {})}`
  const pending = pendingGetRequests.get(key)
  if (pending) return pending

  const request = sendApiRequest(path, options).finally(() => {
    if (pendingGetRequests.get(key) === request) pendingGetRequests.delete(key)
  })
  pendingGetRequests.set(key, request)
  return request
}

export async function openApiFile(path) {
  if (!baseUrl) throw new Error('VITE_API_BASE_URL is not configured for this deployment.')
  const headers = {}
  const token = getToken()
  if (token) headers.Authorization = `Bearer ${token}`
  const response = await fetch(`${baseUrl}${path}`, { headers })
  if (!response.ok) {
    const result = await response.json().catch(() => ({}))
    throw new Error(result.message || 'File could not be opened.')
  }
  const objectUrl = URL.createObjectURL(await response.blob())
  window.open(objectUrl, '_blank', 'noopener')
  setTimeout(() => URL.revokeObjectURL(objectUrl), 60000)
}

export async function apiFileObjectUrl(path) {
  if (!baseUrl) throw new Error('VITE_API_BASE_URL is not configured for this deployment.')
  const headers = {}
  const token = getToken()
  if (token) headers.Authorization = `Bearer ${token}`
  const response = await fetch(`${baseUrl}${path}`, { headers })
  if (!response.ok) throw new Error('File could not be opened.')
  return URL.createObjectURL(await response.blob())
}

export async function downloadApiFile(path, filename) {
  if (!baseUrl) throw new Error('VITE_API_BASE_URL is not configured for this deployment.')
  const headers = {}
  const token = getToken()
  if (token) headers.Authorization = `Bearer ${token}`
  const response = await fetch(`${baseUrl}${path}`, { headers })
  if (!response.ok) {
    const result = await response.json().catch(() => ({}))
    throw new Error(result.message || 'File could not be downloaded.')
  }
  const objectUrl = URL.createObjectURL(await response.blob())
  const link = document.createElement('a')
  link.href = objectUrl
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  setTimeout(() => URL.revokeObjectURL(objectUrl), 60000)
}
