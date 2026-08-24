<template>
  <div class="workspace acc-page">
    <header class="topbar">
      <div class="acc-shell topbar-inner">
        <div>
          <div class="brand">号码统计</div>
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
        <div class="section-title">统计种类</div>
        <div v-if="categories.length" class="category-scroll">
          <button
            v-for="c in categories"
            :key="c.id"
            type="button"
            class="cat-chip"
            :class="{ active: c.id === categoryId }"
            @click="selectCategory(c.id)"
          >
            <span class="cat-name">{{ c.name }}</span>
            <span class="cat-odds">赔率 {{ categoryOdds[c.code] ?? categoryOdds[c.name] ?? '-' }}</span>
          </button>
        </div>
        <el-empty v-else description="暂无种类，请先在后台配置" :image-size="64" />
      </section>

      <section class="acc-card list-card">
        <div class="list-head">
          <div class="section-title">
            {{ isZodiac ? '十二生肖' : '号码 01-49' }}
            <span class="acc-muted head-tip">已选 {{ selectedIds.size }}</span>
          </div>
          <div class="list-tools">
            <el-button
              size="small"
              :type="oddsMode ? 'warning' : 'default'"
              @click="oddsMode = !oddsMode"
            >
              {{ oddsMode ? '退出设赔率' : '设置赔率' }}
            </el-button>
            <el-button size="small" type="primary" plain @click="openBatchPrice">
              统一赔率
            </el-button>
            <el-button v-if="!oddsMode" size="small" @click="toggleAllVisible">
              {{ allVisibleSelected ? '取消全选' : '全选' }}
            </el-button>
          </div>
        </div>

        <div v-if="oddsMode" class="odds-tip">当前为设赔率模式：点击号码/生肖即可改赔率</div>

        <div v-loading="loadingParams" class="ball-wrap">
          <!-- 特肖：十二生肖 3 列 -->
          <div v-if="isZodiac" class="zodiac-grid">
            <button
              v-for="row in filteredParams"
              :key="row.id"
              type="button"
              class="zodiac-item"
              :class="{ selected: !oddsMode && selectedIds.has(row.id), 'odds-edit': oddsMode }"
              @click="onItemClick(row)"
            >
              <span class="zodiac-name">{{ row.name }}</span>
              <span class="zodiac-odds" :class="{ custom: row.is_custom }">
                {{ formatOdds(row.price) }}
                <i v-if="oddsMode" class="odds-edit-mark">改</i>
              </span>
            </button>
          </div>
          <!-- 特码/平特/平码：号码球 -->
          <div v-else class="ball-grid">
            <button
              v-for="row in filteredParams"
              :key="row.id"
              type="button"
              class="ball-item"
              :class="[ballColor(row.num), { selected: !oddsMode && selectedIds.has(row.id), 'odds-edit': oddsMode }]"
              @click="onItemClick(row)"
            >
              <span class="ball-circle">{{ row.label }}</span>
              <span class="ball-odds" :class="{ custom: row.is_custom }">
                {{ formatOdds(row.price) }}
                <i v-if="oddsMode" class="odds-edit-mark">改</i>
              </span>
            </button>
          </div>
          <el-empty
            v-if="!loadingParams && filteredParams.length === 0"
            description="无数据"
            :image-size="72"
          />
        </div>
      </section>
    </main>

    <footer class="dock">
      <div class="acc-shell dock-inner">
        <div class="dock-info">
          已选 <b>{{ selectedIds.size }}</b> 号
        </div>
        <div class="dock-qty">
          <span class="acc-muted">统一注数</span>
          <el-input-number
            v-model="quantity"
            :min="0.0001"
            :step="1"
            :precision="2"
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

    <el-dialog
      v-model="priceVisible"
      :title="batchPriceMode ? '统一设置赔率' : '设置赔率'"
      width="360px"
    >
      <el-form label-position="top">
        <el-form-item
          :label="batchPriceMode
            ? `当前种类「${currentCategory?.name || ''}」全部项目`
            : (editingRow ? (isZodiac ? editingRow.name : `号码 ${editingRow.label}`) : '赔率')"
        >
          <el-input-number
            v-model="editingPrice"
            :min="0"
            :precision="4"
            :step="0.01"
            style="width: 100%"
          />
        </el-form-item>
        <p v-if="batchPriceMode" class="dialog-tip acc-muted">
          将覆盖本种类下全部号码/生肖的专属赔率（仅对你自己生效）
        </p>
      </el-form>
      <template #footer>
        <el-button @click="priceVisible = false">取消</el-button>
        <el-button type="primary" :loading="savingPrice" @click="savePrice">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, shallowRef, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useDebounceFn } from '@vueuse/core'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  apiBatchBill,
  apiBatchSetPrice,
  apiCategories,
  apiLogout,
  apiParams,
  apiSetPrice,
  getUser,
  toastError,
} from '@/api/http'

/** 香港六合彩球色 */
const RED_SET = new Set([1, 2, 7, 8, 12, 13, 18, 19, 23, 24, 29, 30, 34, 35, 40, 45, 46])
const BLUE_SET = new Set([3, 4, 9, 10, 14, 15, 20, 25, 26, 31, 36, 37, 41, 42, 47, 48])

const ZODIAC_ORDER = ['鼠', '牛', '虎', '兔', '龙', '蛇', '马', '羊', '猴', '鸡', '狗', '猪']

const categoryOdds = {
  tema: '47',
  texiao: '11',
  pingte: '7.8',
  pingma: '8.07',
  特码: '47',
  特肖: '11',
  平特: '7.8',
  平码: '8.07',
}

const router = useRouter()
const user = shallowRef(getUser())
const categories = shallowRef([])
const categoryId = ref(0)
const params = shallowRef([])
const selectedIds = shallowRef(new Set())
const quantity = ref(1)
const loadingParams = ref(false)
const submitting = ref(false)
const priceVisible = ref(false)
const editingRow = shallowRef(null)
const editingPrice = ref(0)
const savingPrice = ref(false)
const oddsMode = ref(false)
const batchPriceMode = ref(false)

const currentCategory = computed(() =>
  categories.value.find((c) => Number(c.id) === Number(categoryId.value)) || null,
)

const isZodiac = computed(() => {
  const c = currentCategory.value
  if (!c) return false
  return c.code === 'texiao' || c.name === '特肖'
})

const filteredParams = computed(() => {
  const list = [...params.value]
  if (isZodiac.value) {
    return list.sort((a, b) => {
      const ia = ZODIAC_ORDER.indexOf(a.name)
      const ib = ZODIAC_ORDER.indexOf(b.name)
      return (ia < 0 ? 99 : ia) - (ib < 0 ? 99 : ib)
    })
  }
  return list.sort((a, b) => a.num - b.num)
})

const allVisibleSelected = computed(() => {
  const list = filteredParams.value
  if (!list.length) return false
  return list.every((p) => selectedIds.value.has(p.id))
})

function ballColor(num) {
  if (RED_SET.has(num)) return 'ball-red'
  if (BLUE_SET.has(num)) return 'ball-blue'
  return 'ball-green'
}

function formatOdds(v) {
  const n = Number(v)
  if (!Number.isFinite(n)) return '0'
  return Number.isInteger(n) ? String(n) : n.toFixed(2).replace(/\.?0+$/, '') || String(n)
}

function padNum(n) {
  return String(n).padStart(2, '0')
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
    params.value = Object.freeze(
      (data?.list || []).map((row) => {
        const num = parseInt(String(row.name).replace(/\D/g, ''), 10) || Number(row.id)
        return Object.freeze({
          id: Number(row.id),
          category_id: Number(row.category_id),
          name: row.name,
          num,
          label: padNum(num),
          unit: row.unit,
          default_price: row.default_price,
          price: row.price,
          is_custom: Number(row.is_custom) || 0,
        })
      }),
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

function onItemClick(row) {
  if (oddsMode.value) {
    openPrice(row)
    return
  }
  toggleRow(row.id)
}

function openPrice(row) {
  batchPriceMode.value = false
  editingRow.value = row
  editingPrice.value = Number(row.price) || 0
  priceVisible.value = true
}

function openBatchPrice() {
  if (!categoryId.value || !params.value.length) {
    ElMessage.warning('请先选择种类')
    return
  }
  batchPriceMode.value = true
  editingRow.value = null
  const first = filteredParams.value[0]
  editingPrice.value =
    Number(first?.price) || Number(categoryOdds[currentCategory.value?.code] || 0) || 0
  priceVisible.value = true
}

async function savePrice() {
  if (!Number.isFinite(Number(editingPrice.value)) || Number(editingPrice.value) < 0) {
    ElMessage.warning('请输入有效赔率')
    return
  }
  savingPrice.value = true
  try {
    const price = String(editingPrice.value)
    if (batchPriceMode.value) {
      const res = await apiBatchSetPrice({
        category_id: categoryId.value,
        price,
      })
      const next = params.value.map((p) => Object.freeze({ ...p, price, is_custom: 1 }))
      params.value = Object.freeze(next)
      ElMessage.success(`已统一设置 ${res.updated || next.length} 项赔率`)
    } else {
      if (!editingRow.value) return
      await apiSetPrice({
        param_id: editingRow.value.id,
        category_id: categoryId.value,
        price,
      })
      const next = params.value.map((p) =>
        p.id === editingRow.value.id ? Object.freeze({ ...p, price, is_custom: 1 }) : p,
      )
      params.value = Object.freeze(next)
      ElMessage.success('赔率已更新')
    }
    priceVisible.value = false
  } catch (e) {
    toastError(e)
  } finally {
    savingPrice.value = false
  }
}

async function submitBatch() {
  if (submitting.value) return
  if (!selectedIds.value.size) {
    ElMessage.warning('请先选号')
    return
  }
  if (!quantity.value || Number(quantity.value) <= 0) {
    ElMessage.warning('注数必须大于 0')
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
      ElMessage.success(`已排队异步入账 ${res.queued} 条`)
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

const debouncedSubmit = useDebounceFn(submitBatch, 300, { maxWait: 800 })

async function onLogout() {
  try {
    await ElMessageBox.confirm('确定退出？', '提示', { type: 'warning' })
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
  background: rgba(243, 246, 245, 0.92);
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
  display: flex;
  align-items: baseline;
  gap: 10px;
}

.head-tip {
  font-size: 13px;
  font-weight: 400;
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
  border-radius: 12px;
  padding: 10px 14px;
  white-space: nowrap;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  min-width: 88px;
}

.cat-name {
  font-weight: 700;
  font-size: 15px;
}

.cat-odds {
  font-size: 12px;
  color: var(--acc-muted);
}

.cat-chip.active {
  background: var(--acc-brand);
  border-color: var(--acc-brand);
  color: #fff;
}

.cat-chip.active .cat-odds {
  color: rgba(255, 255, 255, 0.85);
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
  flex-wrap: wrap;
}

.odds-tip {
  margin: -4px 0 10px;
  padding: 8px 10px;
  border-radius: 8px;
  background: #fff7ed;
  color: #c2410c;
  font-size: 13px;
}

.dialog-tip {
  margin: 0;
  font-size: 13px;
  line-height: 1.4;
}

.zodiac-odds.custom,
.ball-odds.custom {
  color: #0f766e;
  font-weight: 700;
}

.odds-edit-mark {
  font-style: normal;
  margin-left: 4px;
  font-size: 11px;
  color: #ea580c;
}

.zodiac-item.odds-edit,
.ball-item.odds-edit .ball-circle {
  outline: 1px dashed rgba(234, 88, 12, 0.45);
}

.ball-wrap {
  min-height: 240px;
  border: 1px solid var(--acc-line);
  border-radius: 12px;
  background: #f0f1f3;
  padding: 14px 10px 18px;
}

.zodiac-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px 10px;
}

.zodiac-item {
  border: 1px solid #7eb6e8;
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 2px 0 #c5d8ea;
  padding: 14px 8px 12px;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  min-height: 72px;
}

.zodiac-item.selected {
  border-color: #0f766e;
  box-shadow: 0 0 0 2px rgba(15, 118, 110, 0.25);
  background: #f0fdfa;
}

.zodiac-name {
  font-size: 20px;
  font-weight: 700;
  color: #334155;
  line-height: 1.1;
}

.zodiac-odds {
  font-size: 15px;
  color: #a67c52;
  line-height: 1;
}

.ball-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px 8px;
  justify-items: center;
}

.ball-item {
  border: 0;
  background: transparent;
  padding: 0;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  width: 100%;
  max-width: 72px;
}

.ball-circle {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  border: 2px solid currentColor;
  display: grid;
  place-items: center;
  font-size: 18px;
  font-weight: 700;
  color: #334155;
  background: #fff;
  transition: box-shadow 0.15s, transform 0.15s;
}

.ball-red .ball-circle {
  border-color: #e11d48;
  color: #be123c;
}

.ball-blue .ball-circle {
  border-color: #2563eb;
  color: #1d4ed8;
}

.ball-green .ball-circle {
  border-color: #16a34a;
  color: #15803d;
}

.ball-item.selected .ball-circle {
  box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.28);
  transform: scale(1.06);
  background: #ecfdf5;
}

.ball-odds {
  font-size: 13px;
  color: #64748b;
  line-height: 1;
}

.ball-item.selected .ball-odds {
  color: var(--acc-brand);
  font-weight: 650;
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

  .ball-circle {
    width: 44px;
    height: 44px;
    font-size: 16px;
  }
}

@media (min-width: 900px) {
  .ball-grid {
    grid-template-columns: repeat(5, minmax(0, 1fr));
  }
}
</style>
