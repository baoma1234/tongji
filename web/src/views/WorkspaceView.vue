<template>
  <div class="workspace acc-page">
    <header class="topbar">
      <div class="acc-shell topbar-inner">
        <div>
          <div class="brand">极简记账</div>
          <div class="acc-muted user">{{ user?.nickname || '未命名' }} · {{ user?.access_code }}</div>
        </div>
        <div class="actions">
          <el-button text @click="$router.push('/bills')">流水</el-button>
          <el-button text type="danger" @click="onLogout">退出</el-button>
        </div>
      </div>
    </header>

    <main class="acc-shell">
      <section class="acc-card category-card">
        <div class="section-title">类目</div>
        <div v-if="categories.length" class="category-scroll">
          <button
            v-for="c in categories"
            :key="c.id"
            type="button"
            class="cat-chip"
            :class="{ active: c.id === categoryId }"
            @click="selectCategory(c.id)"
          >
            {{ c.name }}
          </button>
        </div>
        <el-empty v-else description="暂无类目，请先在后台配置" :image-size="64" />
      </section>

      <section class="acc-card list-card">
        <div class="list-head">
          <div class="section-title">参数列表</div>
          <div class="list-tools">
            <el-input
              v-model="keyword"
              clearable
              placeholder="筛选名称"
              style="width: 160px"
              @input="onFilter"
            />
            <el-button @click="toggleAllVisible">{{ allVisibleSelected ? '取消全选' : '全选当前' }}</el-button>
          </div>
        </div>

        <div v-loading="loadingParams" class="virtual-wrap" v-bind="containerProps">
          <div v-bind="wrapperProps">
            <div
              v-for="{ data: row, index } in virtualList"
              :key="row.id"
              class="param-row"
              :class="{ selected: selectedIds.has(row.id) }"
              @click="toggleRow(row.id)"
            >
              <el-checkbox
                :model-value="selectedIds.has(row.id)"
                @click.stop
                @change="() => toggleRow(row.id)"
              />
              <div class="param-main">
                <div class="param-name">{{ row.name }}</div>
                <div class="param-meta acc-muted">
                  #{{ index + 1 }} · {{ row.unit || '次' }}
                  <span v-if="row.is_custom" class="tag">专属价</span>
                </div>
              </div>
              <button type="button" class="price-btn" @click.stop="openPrice(row)">
                ¥{{ formatPrice(row.price) }}
              </button>
            </div>
          </div>
          <el-empty
            v-if="!loadingParams && filteredParams.length === 0"
            description="无匹配参数"
            :image-size="72"
          />
        </div>
      </section>
    </main>

    <footer class="dock">
      <div class="acc-shell dock-inner">
        <div class="dock-info">
          已选 <b>{{ selectedIds.size }}</b> 项
        </div>
        <div class="dock-qty">
          <span class="acc-muted">统一数量</span>
          <el-input-number
            v-model="quantity"
            :min="0.0001"
            :step="1"
            :precision="4"
            controls-position="right"
          />
        </div>
        <el-button
          type="primary"
          size="large"
          class="submit-btn"
          :loading="submitting"
          :disabled="!selectedIds.size"
          @click="debouncedSubmit"
        >
          批量入账
        </el-button>
      </div>
    </footer>

    <el-dialog v-model="priceVisible" title="设置专属单价" width="360px">
      <el-form label-position="top">
        <el-form-item :label="editingRow?.name || '参数'">
          <el-input-number
            v-model="editingPrice"
            :min="0"
            :precision="4"
            :step="1"
            style="width: 100%"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="priceVisible = false">取消</el-button>
        <el-button type="primary" :loading="savingPrice" @click="savePrice">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import {
  computed,
  onMounted,
  ref,
  shallowRef,
  watch,
} from 'vue'
import { useRouter } from 'vue-router'
import { useDebounceFn, useVirtualList } from '@vueuse/core'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  apiBatchBill,
  apiCategories,
  apiLogout,
  apiParams,
  apiSetPrice,
  getUser,
  toastError,
} from '@/api/http'

const router = useRouter()
const user = shallowRef(getUser())

/** 类目：量小，普通 ref 即可 */
const categories = shallowRef([])
const categoryId = ref(0)

/**
 * 参数列表用 shallowRef，避免几十上百项深度代理导致输入/勾选卡顿。
 * 勾选状态单独用 Set + shallowRef，变更时整体替换触发一次渲染。
 */
const params = shallowRef([])
const selectedIds = shallowRef(new Set())
const keyword = ref('')
const quantity = ref(1)
const loadingParams = ref(false)
const submitting = ref(false)

const priceVisible = ref(false)
const editingRow = shallowRef(null)
const editingPrice = ref(0)
const savingPrice = ref(false)

const filteredParams = computed(() => {
  const kw = keyword.value.trim().toLowerCase()
  const list = params.value
  if (!kw) return list
  return list.filter((p) => String(p.name).toLowerCase().includes(kw))
})

const { list: virtualList, containerProps, wrapperProps } = useVirtualList(filteredParams, {
  itemHeight: 64,
  overscan: 8,
})

const allVisibleSelected = computed(() => {
  const list = filteredParams.value
  if (!list.length) return false
  return list.every((p) => selectedIds.value.has(p.id))
})

function formatPrice(v) {
  const n = Number(v)
  return Number.isFinite(n) ? n.toFixed(2) : '0.00'
}

function toggleRow(id) {
  const next = new Set(selectedIds.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  selectedIds.value = next
}

function toggleAllVisible() {
  const next = new Set(selectedIds.value)
  const list = filteredParams.value
  if (allVisibleSelected.value) {
    list.forEach((p) => next.delete(p.id))
  } else {
    list.forEach((p) => next.add(p.id))
  }
  selectedIds.value = next
}

function onFilter() {
  // keyword 已驱动 computed；此处无需深拷贝 params
}

async function loadCategories() {
  const data = await apiCategories()
  categories.value = data?.list || []
  if (!categoryId.value && categories.value.length) {
    categoryId.value = Number(categories.value[0].id)
  }
}

async function loadParams() {
  if (!categoryId.value) {
    params.value = []
    return
  }
  loadingParams.value = true
  try {
    const data = await apiParams(categoryId.value)
    // 冻结每行对象，进一步降低意外深度响应开销
    params.value = Object.freeze(
      (data?.list || []).map((row) =>
        Object.freeze({
          id: Number(row.id),
          category_id: Number(row.category_id),
          name: row.name,
          unit: row.unit,
          default_price: row.default_price,
          price: row.price,
          is_custom: Number(row.is_custom) || 0,
        }),
      ),
    )
    selectedIds.value = new Set()
  } finally {
    loadingParams.value = false
  }
}

function selectCategory(id) {
  if (categoryId.value === id) return
  categoryId.value = id
}

watch(categoryId, () => {
  loadParams().catch(toastError)
})

function openPrice(row) {
  editingRow.value = row
  editingPrice.value = Number(row.price) || 0
  priceVisible.value = true
}

async function savePrice() {
  if (!editingRow.value) return
  savingPrice.value = true
  try {
    const price = String(editingPrice.value)
    await apiSetPrice({
      param_id: editingRow.value.id,
      category_id: categoryId.value,
      price,
    })
    // 局部更新：复制数组浅替换一行，避免整表深响应
    const next = params.value.map((p) =>
      p.id === editingRow.value.id
        ? Object.freeze({ ...p, price, is_custom: 1 })
        : p,
    )
    params.value = Object.freeze(next)
    priceVisible.value = false
    ElMessage.success('单价已更新')
  } catch (e) {
    toastError(e)
  } finally {
    savingPrice.value = false
  }
}

async function submitBatch() {
  if (submitting.value) return
  if (!selectedIds.value.size) {
    ElMessage.warning('请先勾选参数')
    return
  }
  if (!quantity.value || Number(quantity.value) <= 0) {
    ElMessage.warning('数量必须大于 0')
    return
  }

  const items = [...selectedIds.value].map((param_id) => ({ param_id }))
  submitting.value = true
  try {
    const clientReqId =
      (crypto.randomUUID && crypto.randomUUID()) ||
      `web_${Date.now()}_${Math.random().toString(16).slice(2)}`

    const res = await apiBatchBill({
      category_id: categoryId.value,
      quantity: String(quantity.value),
      items,
      client_req_id: clientReqId,
      remark: '',
    })

    if (res.mode === 'async') {
      ElMessage.success(`已排队异步入账 ${res.queued} 条，批次 ${res.batch_id}`)
    } else {
      ElMessage.success(`入账成功 ${res.inserted} 条`)
    }
    selectedIds.value = new Set()
  } catch (e) {
    toastError(e)
  } finally {
    submitting.value = false
  }
}

/** 防抖：300ms 内连点只提交一次，配合后端 client_req_id 双保险 */
const debouncedSubmit = useDebounceFn(submitBatch, 300, { maxWait: 800 })

async function onLogout() {
  try {
    await ElMessageBox.confirm('确定退出当前账本？', '提示', { type: 'warning' })
  } catch {
    return
  }
  await apiLogout()
  router.replace('/login')
}

onMounted(async () => {
  try {
    await loadCategories()
    await loadParams()
  } catch (e) {
    toastError(e)
  }
})
</script>

<style scoped>
.topbar {
  position: sticky;
  top: 0;
  z-index: 20;
  backdrop-filter: blur(10px);
  background: rgba(243, 246, 245, 0.88);
  border-bottom: 1px solid var(--acc-line);
}

.topbar-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 14px;
  padding-bottom: 14px;
}

.brand {
  font-size: 22px;
  font-weight: 750;
  color: var(--acc-brand);
}

.user {
  font-size: 13px;
  margin-top: 2px;
}

.category-card,
.list-card {
  padding: 16px;
  margin-bottom: 14px;
}

.section-title {
  font-weight: 650;
  margin-bottom: 12px;
}

.category-scroll {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  padding-bottom: 4px;
}

.cat-chip {
  border: 1px solid var(--acc-line);
  background: #fff;
  color: var(--acc-ink);
  border-radius: 999px;
  padding: 8px 14px;
  white-space: nowrap;
  cursor: pointer;
}

.cat-chip.active {
  background: var(--acc-brand);
  border-color: var(--acc-brand);
  color: #fff;
}

.list-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 8px;
}

.list-tools {
  display: flex;
  gap: 8px;
  align-items: center;
}

.virtual-wrap {
  height: min(58vh, 560px);
  overflow: auto;
  border: 1px solid var(--acc-line);
  border-radius: 12px;
  background: #fbfcfc;
}

.param-row {
  height: 64px;
  display: grid;
  grid-template-columns: 28px 1fr auto;
  gap: 10px;
  align-items: center;
  padding: 0 14px;
  border-bottom: 1px solid #e8efec;
  cursor: pointer;
}

.param-row.selected {
  background: rgba(20, 184, 166, 0.1);
}

.param-name {
  font-weight: 600;
}

.param-meta {
  font-size: 12px;
  margin-top: 2px;
}

.tag {
  display: inline-block;
  margin-left: 6px;
  padding: 0 6px;
  border-radius: 999px;
  background: rgba(15, 118, 110, 0.12);
  color: var(--acc-brand);
}

.price-btn {
  border: 0;
  background: transparent;
  color: var(--acc-brand);
  font-weight: 700;
  cursor: pointer;
  padding: 6px 8px;
  border-radius: 8px;
}

.price-btn:hover {
  background: rgba(15, 118, 110, 0.08);
}

.dock {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 30;
  background: rgba(255, 255, 255, 0.96);
  border-top: 1px solid var(--acc-line);
  box-shadow: 0 -8px 24px rgba(18, 53, 47, 0.06);
}

.dock-inner {
  display: flex;
  align-items: center;
  gap: 12px;
  padding-top: 12px;
  padding-bottom: calc(12px + env(safe-area-inset-bottom));
}

.dock-info {
  min-width: 88px;
}

.dock-qty {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
}

.submit-btn {
  min-width: 120px;
  --el-button-bg-color: var(--acc-brand);
  --el-button-border-color: var(--acc-brand);
  --el-button-hover-bg-color: #0d9488;
  --el-button-hover-border-color: #0d9488;
}

@media (max-width: 640px) {
  .dock-inner {
    flex-wrap: wrap;
  }

  .dock-qty {
    order: 3;
    width: 100%;
  }

  .submit-btn {
    margin-left: auto;
  }

  .list-head {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>
