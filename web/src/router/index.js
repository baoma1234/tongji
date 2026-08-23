import { createRouter, createWebHashHistory } from 'vue-router'
import { getToken } from '@/api/http'

const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/LoginView.vue'),
    meta: { public: true },
  },
  {
    path: '/',
    name: 'workspace',
    component: () => import('@/views/WorkspaceView.vue'),
  },
  {
    path: '/bills',
    name: 'bills',
    component: () => import('@/views/BillsView.vue'),
  },
]

const router = createRouter({
  // Hash 模式：宝塔/Apache 无需额外 rewrite，刷新不 404
  history: createWebHashHistory(),
  routes,
})

router.beforeEach((to) => {
  if (!to.meta.public && !getToken()) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  if (to.name === 'login' && getToken()) {
    return { name: 'workspace' }
  }
})

export default router
