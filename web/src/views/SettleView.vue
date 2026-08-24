<template>
  <div class="settle-page acc-page">
    <header class="topbar">
      <div class="acc-shell topbar-inner">
        <div>
          <div class="brand">开奖结算</div>
          <div class="acc-muted">输入正码6个+特码1个，自动核对当日入账</div>
        </div>
        <div>
          <el-button text @click="$router.push('/')">返回入账</el-button>
          <el-button text @click="$router.push('/bills')">流水</el-button>
        </div>
      </div>
    </header>

    <main class="acc-shell">
      <section class="acc-card form-card">
        <div class="section-title">开奖号码</div>
        <div class="form-row">
          <el-date-picker
            v-model="billDate"
            type="date"
            value-format="YYYY-MM-DD"
            placeholder="入账日期"
            :clearable="false"
          />
        </div>
        <div class="num-grid">
          <div v-for="(n, idx) in drawInput" :key="idx" class="num-field">
            <label>{{ idx < 6 ? `正码${idx + 1}` : '特码' }}</label>
            <el-input
              v-model="drawInput[idx]"
              maxlength="2"
              inputmode="numeric"
              :placeholder="idx < 6 ? '01-49' : '特码'"
              @input="(v) => onNumInput(idx, v)"
            />
          </div>
        </div>
        <div class="paste-row">
          <el-input
            v-model="pasteText"
            placeholder="也可粘贴：01 12 23 34 45 08 49"
            clearable
            @keyup.enter="parsePaste"
          />
          <el-button @click="parsePaste">识别</el-button>
        </div>
        <div class="actions-row">
          <el-button type="primary" size="large" :loading="loading" @click="onSettle">
            自动结算
          </el-button>
        </div>
        <p class="rule-tip acc-muted">
          特码=第7位命中；特肖=特码生肖命中；平码=前6个正码命中；平特=7个号码任一命中。
          成本按「1注=1」计，中奖金额=注数×赔率。
        </p>
      </section>

      <section v-if="result" class="acc-card result-card">
        <div class="draw-board">
          <div class="section-title">本期开奖</div>
          <div class="balls">
            <span
              v-for="(n, i) in result.draw.zhengma"
              :key="'z' + i"
              class="ball"
              :class="ballColor(n)"
            >{{ pad(n) }}</span>
            <span class="plus">+</span>
            <span class="ball tema" :class="ballColor(result.draw.tema)">{{ pad(result.draw.tema) }}</span>
          </div>
          <div class="acc-muted meta">
            特码生肖：<b>{{ result.draw.tema_zodiac }}</b>
            · 年生肖：{{ result.draw.year_animal }}
            · 入账日：{{ result.bill_date }}
          </div>
        </div>

        <div class="summary-grid">
          <div class="sum-item">
            <div class="label">投注注数</div>
            <div class="val">{{ fmt(result.summary.stake) }}</div>
          </div>
          <div class="sum-item">
            <div class="label">中奖派彩</div>
            <div class="val win">{{ fmt(result.summary.payout) }}</div>
          </div>
          <div class="sum-item">
            <div class="label">盈亏</div>
            <div class="val" :class="Number(result.summary.profit) >= 0 ? 'win' : 'lose'">
              {{ fmt(result.summary.profit) }}
            </div>
          </div>
          <div class="sum-item">
            <div class="label">中/总</div>
            <div class="val">{{ result.summary.win_count }}/{{ result.summary.bet_count }}</div>
          </div>
        </div>

        <div v-if="result.summary.by_category?.length" class="cat-summary">
          <div class="section-title">分类汇总</div>
          <div
            v-for="c in result.summary.by_category"
            :key="c.category"
            class="cat-line"
          >
            <span>{{ c.category }}</span>
            <span>中{{ c.win_count }}/{{ c.bet_count }}</span>
            <span :class="Number(c.profit) >= 0 ? 'win' : 'lose'">{{ fmt(c.profit) }}</span>
          </div>
        </div>

        <div class="section-title">明细</div>
        <el-table :data="result.list" size="small" stripe max-height="420" empty-text="该日暂无入账记录">
          <el-table-column prop="category" label="种类" width="80" />
          <el-table-column prop="param_name" label="投注" width="70" />
          <el-table-column prop="quantity" label="注数" width="70" />
          <el-table-column prop="odds" label="赔率" width="70" />
          <el-table-column label="结果" width="70">
            <template #default="{ row }">
              <span :class="row.hit ? 'win' : 'lose'">{{ row.hit ? '中' : '未中' }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="payout" label="派彩" min-width="90">
            <template #default="{ row }">{{ fmt(row.payout) }}</template>
          </el-table-column>
          <el-table-column prop="profit" label="盈亏" min-width="90">
            <template #default="{ row }">
              <span :class="Number(row.profit) >= 0 ? 'win' : 'lose'">{{ fmt(row.profit) }}</span>
            </template>
          </el-table-column>
        </el-table>
      </section>
    </main>
  </div>
</template>

<script setup>
import { reactive, ref, shallowRef } from 'vue'
import { ElMessage } from 'element-plus'
import { apiSettle, toastError } from '@/api/http'

const RED_SET = new Set([1, 2, 7, 8, 12, 13, 18, 19, 23, 24, 29, 30, 34, 35, 40, 45, 46])
const BLUE_SET = new Set([3, 4, 9, 10, 14, 15, 20, 25, 26, 31, 36, 37, 41, 42, 47, 48])

const billDate = ref(formatToday())
const drawInput = reactive(['', '', '', '', '', '', ''])
const pasteText = ref('')
const loading = ref(false)
const result = shallowRef(null)

function formatToday() {
  const d = new Date()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${d.getFullYear()}-${m}-${day}`
}

function pad(n) {
  return String(n).padStart(2, '0')
}

function fmt(v) {
  const n = Number(v)
  if (!Number.isFinite(n)) return '0'
  return n.toFixed(2)
}

function ballColor(num) {
  if (RED_SET.has(num)) return 'ball-red'
  if (BLUE_SET.has(num)) return 'ball-blue'
  return 'ball-green'
}

function onNumInput(idx, v) {
  drawInput[idx] = String(v).replace(/\D/g, '').slice(0, 2)
}

function parsePaste() {
  const parts = pasteText.value.split(/[\s,，、;；]+/).map((s) => s.replace(/\D/g, '')).filter(Boolean)
  if (parts.length < 7) {
    ElMessage.warning('请粘贴7个号码')
    return
  }
  for (let i = 0; i < 7; i++) {
    drawInput[i] = parts[i].padStart(2, '0')
  }
  ElMessage.success('已识别7个号码')
}

async function onSettle() {
  const numbers = drawInput.map((s) => parseInt(s, 10)).filter((n) => n >= 1 && n <= 49)
  if (numbers.length !== 7) {
    ElMessage.warning('请填齐7个有效号码(01-49)')
    return
  }
  if (new Set(numbers).size !== 7) {
    ElMessage.warning('号码不能重复')
    return
  }
  loading.value = true
  try {
    const data = await apiSettle({
      numbers,
      bill_date: billDate.value,
    })
    result.value = data
    if (!data.summary.bet_count) {
      ElMessage.info('该日暂无入账，请先在入账页选号提交')
    } else {
      ElMessage.success(`结算完成：中${data.summary.win_count}/${data.summary.bet_count}`)
    }
  } catch (e) {
    toastError(e)
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.topbar {
  border-bottom: 1px solid var(--acc-line);
  background: rgba(243, 246, 245, 0.92);
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

.form-card,
.result-card {
  padding: 16px;
  margin-bottom: 14px;
}

.section-title {
  font-weight: 650;
  margin-bottom: 12px;
}

.form-row {
  margin-bottom: 12px;
}

.num-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
  margin-bottom: 12px;
}

.num-field label {
  display: block;
  font-size: 12px;
  color: var(--acc-muted);
  margin-bottom: 4px;
}

.paste-row {
  display: flex;
  gap: 8px;
  margin-bottom: 14px;
}

.actions-row {
  margin-bottom: 10px;
}

.rule-tip {
  font-size: 12px;
  line-height: 1.5;
  margin: 0;
}

.balls {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}

.ball {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  border: 2px solid #94a3b8;
  display: grid;
  place-items: center;
  font-weight: 700;
  background: #fff;
}

.ball.tema {
  box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.2);
}

.ball-red { border-color: #e11d48; color: #be123c; }
.ball-blue { border-color: #2563eb; color: #1d4ed8; }
.ball-green { border-color: #16a34a; color: #15803d; }

.plus {
  font-weight: 700;
  color: var(--acc-muted);
}

.meta {
  margin-bottom: 14px;
  font-size: 13px;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
  margin-bottom: 16px;
}

.sum-item {
  background: #f8faf9;
  border: 1px solid var(--acc-line);
  border-radius: 10px;
  padding: 10px;
}

.sum-item .label {
  font-size: 12px;
  color: var(--acc-muted);
}

.sum-item .val {
  font-size: 18px;
  font-weight: 750;
  margin-top: 4px;
}

.cat-summary {
  margin-bottom: 16px;
}

.cat-line {
  display: grid;
  grid-template-columns: 1fr auto auto;
  gap: 12px;
  padding: 8px 0;
  border-bottom: 1px solid #eef2f1;
  font-size: 14px;
}

.win { color: #0f766e; font-weight: 700; }
.lose { color: #dc2626; }

@media (max-width: 640px) {
  .num-grid,
  .summary-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
