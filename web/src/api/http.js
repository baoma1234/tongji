import axios from 'axios'
import { ElMessage } from 'element-plus'

const TOKEN_KEY = 'acc_token'
const USER_KEY = 'acc_user'

export function getToken() {
  return localStorage.getItem(TOKEN_KEY) || ''
}

export function setSession(userinfo) {
  if (userinfo?.token) {
    localStorage.setItem(TOKEN_KEY, userinfo.token)
  }
  if (userinfo) {
    localStorage.setItem(USER_KEY, JSON.stringify(userinfo))
  }
}

export function clearSession() {
  localStorage.removeItem(TOKEN_KEY)
  localStorage.removeItem(USER_KEY)
}

export function getUser() {
  try {
    return JSON.parse(localStorage.getItem(USER_KEY) || 'null')
  } catch {
    return null
  }
}

const http = axios.create({
  baseURL: '/api',
  timeout: 20000,
})

http.interceptors.request.use((config) => {
  const token = getToken()
  if (token) {
    config.headers.token = token
  }
  return config
})

http.interceptors.response.use(
  (res) => {
    const body = res.data
    if (body && typeof body === 'object' && 'code' in body) {
      if (body.code === 1) {
        return body.data
      }
      if (body.code === 401) {
        clearSession()
        if (!location.pathname.includes('/login')) {
          location.href = '/account/login'
        }
      }
      return Promise.reject(new Error(body.msg || '请求失败'))
    }
    return body
  },
  (err) => {
    const msg = err.response?.data?.msg || err.message || '网络异常'
    return Promise.reject(new Error(msg))
  },
)

export async function apiLogin(accessCode) {
  const data = await http.post('/account.auth/login', { access_code: accessCode })
  setSession(data.userinfo)
  return data.userinfo
}

export async function apiLogout() {
  try {
    await http.post('/account.auth/logout')
  } finally {
    clearSession()
  }
}

export function apiCategories() {
  return http.get('/account.category/index')
}

export function apiParams(categoryId) {
  return http.get('/account.param/index', { params: { category_id: categoryId } })
}

export function apiSetPrice(payload) {
  return http.post('/account.price/set', payload)
}

export function apiBatchSetPrice(payload) {
  return http.post('/account.price/batchSet', payload)
}

export function apiBatchBill(payload) {
  return http.post('/account.bill/batch', payload)
}

export function apiBills(params) {
  return http.get('/account.bill/index', { params })
}

export function apiSettle(payload) {
  return http.post('/account.settle/calc', payload)
}

export function apiZodiac(num, date) {
  return http.get('/account.settle/zodiac', { params: { num, date } })
}

export function toastError(e) {
  ElMessage.error(e?.message || String(e))
}

export default http
