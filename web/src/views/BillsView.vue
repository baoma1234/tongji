<template>
  <div class="bills-page acc-page">
    <header class="topbar">
      <div class="acc-shell topbar-inner">
        <div>
          <div class="brand">账单流水</div>
          <div class="acc-muted">按月分表查询 · 仅看自己的数据</div>
        </div>
        <el-button text @click="$router.push('/')">返回入账</el-button>
      </div>
    </header>

    <main class="acc-shell">
      <section class="acc-card filters">
        <el-date-picker
          v-model="month"
          type="month"
          placeholder="账期"
          value-format="YYYYMM"
          :clearable="false"
          @change="reload"
        />
        <el-button :loading="loading" @click="reload">刷新</el-button>
      </section>

      <section class="acc-card table-card">
        <el-auto-resizer>
          <template #default="{ height, width }">
            <el-table-v2
              :columns="columns"
              :data="rows"
              :width="width"
              :height="Math.max(360, height)"
              :row-height="52"
              fixed
            />
          </template>
        </el-auto-resizer>
        <div class="pager">
          <span class="acc-muted">共 {{ total }} 条</span>
          <el-pagination
            v-model:current-page="page"
            :page-size="limit"
            layout="prev, pager, next"
            :total="total"
            @current-change="reload"
          />
        </div>
      </section>
    </main>
  </div>
</template>

<script setup>
import { h, onMounted, ref, shallowRef } from 'vue'
import { apiBills, toastError } from '@/api/http'

const month = ref(formatYm(new Date()))
const page = ref(1)
const limit = 50
const total = ref(0)
const rows = shallowRef([])
const loading = ref(false)

const columns = [
  { key: 'id', dataKey: 'id', title: 'ID', width: 90 },
  { key: 'param_name', dataKey: 'param_name', title: '参数', width: 140 },
  {
    key: 'quantity',
    dataKey: 'quantity',
    title: '数量',
    width: 100,
    cellRenderer: ({ cellData }) => h('span', Number(cellData).toFixed(2)),
  },
  {
    key: 'unit_price',
    dataKey: 'unit_price',
    title: '单价',
    width: 100,
    cellRenderer: ({ cellData }) => h('span', `¥${Number(cellData).toFixed(2)}`),
  },
  {
    key: 'amount',
    dataKey: 'amount',
    title: '金额',
    width: 110,
    cellRenderer: ({ cellData }) =>
      h('span', { style: 'font-weight:700;color:#0f766e' }, `¥${Number(cellData).toFixed(2)}`),
  },
  { key: 'bill_date', dataKey: 'bill_date', title: '业务日', width: 120 },
  { key: 'batch_id', dataKey: 'batch_id', title: '批次', width: 180 },
]

function formatYm(d) {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  return `${y}${m}`
}

async function reload() {
  loading.value = true
  try {
    const data = await apiBills({
      bill_ym: month.value,
      page: page.value,
      limit,
    })
    rows.value = Object.freeze(data?.list || [])
    total.value = Number(data?.total || 0)
  } catch (e) {
    toastError(e)
  } finally {
    loading.value = false
  }
}

onMounted(reload)
</script>

<style scoped>
.topbar {
  border-bottom: 1px solid var(--acc-line);
  background: rgba(243, 246, 245, 0.9);
}

.topbar-inner {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 14px;
  padding-bottom: 14px;
}

.brand {
  font-size: 22px;
  font-weight: 750;
  color: var(--acc-brand);
}

.filters {
  display: flex;
  gap: 10px;
  align-items: center;
  padding: 14px 16px;
  margin-bottom: 14px;
}

.table-card {
  padding: 12px;
  height: min(70vh, 640px);
  display: flex;
  flex-direction: column;
}

.table-card :deep(.el-auto-resizer) {
  flex: 1;
  min-height: 360px;
}

.pager {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 10px;
}
</style>
