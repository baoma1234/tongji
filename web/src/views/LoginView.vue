<template>
  <div class="login-page acc-page">
    <div class="login-panel acc-card">
      <div class="brand">极简记账</div>
      <p class="acc-muted tip">输入专属口令即可进入，无需注册。数据按口令完全隔离。</p>
      <el-form @submit.prevent="onSubmit">
        <el-form-item>
          <el-input
            v-model="accessCode"
            size="large"
            placeholder="专属口令，如 qwer1234"
            clearable
            show-password
            autocomplete="current-password"
            @keyup.enter="onSubmit"
          />
        </el-form-item>
        <el-button
          type="primary"
          size="large"
          class="submit"
          :loading="loading"
          @click="onSubmit"
        >
          进入账本
        </el-button>
      </el-form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import { apiLogin, toastError } from '@/api/http'

const router = useRouter()
const route = useRoute()
const accessCode = ref('')
const loading = ref(false)

async function onSubmit() {
  const code = accessCode.value.trim()
  if (!code) {
    ElMessage.warning('请输入专属口令')
    return
  }
  loading.value = true
  try {
    await apiLogin(code)
    ElMessage.success('登录成功')
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/'
    router.replace(redirect || '/')
  } catch (e) {
    toastError(e)
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-page {
  display: grid;
  place-items: center;
  padding: 24px;
  min-height: 100vh;
}

.login-panel {
  width: min(420px, 100%);
  padding: 36px 28px 28px;
}

.brand {
  font-size: 32px;
  font-weight: 750;
  letter-spacing: 0.04em;
  color: var(--acc-brand);
  margin-bottom: 8px;
}

.tip {
  margin: 0 0 24px;
  font-size: 14px;
}

.submit {
  width: 100%;
  --el-button-bg-color: var(--acc-brand);
  --el-button-border-color: var(--acc-brand);
  --el-button-hover-bg-color: #0d9488;
  --el-button-hover-border-color: #0d9488;
}
</style>
