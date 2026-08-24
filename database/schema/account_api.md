# 记账 API 速查

前端入口: `/account/`（Vue3 + Element Plus）

Base: `/api/account.xxx/action`  
鉴权头: `token: <登录返回的 token>` 或参数 `token=`

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | `/api/account.auth/login` | body: `access_code` 专属口令（可自动建户） |
| POST | `/api/account.auth/logout` | 退出 |
| GET  | `/api/account.auth/profile` | 当前用户 |
| GET  | `/api/account.category/index` | 类目列表 |
| GET  | `/api/account.param/index?category_id=1` | 参数+用户单价（Redis） |
| POST | `/api/account.price/set` | `param_id, price` 设专属单价 |
| POST | `/api/account.bill/batch` | 批量入账（事务/异步） |
| GET  | `/api/account.bill/index` | 我的账单 `bill_ym,page,limit` |

## 批量入账 body 示例

```json
{
  "category_id": 1,
  "quantity": "2",
  "items": [{"param_id": 1}, {"param_id": 2, "quantity": "1"}],
  "client_req_id": "uuid-or-frontend-key",
  "remark": "",
  "bill_date": "2026-08-24"
}
```

- `items` ≤ 100：同步事务写入当月分表
- `items` > 100 或 `async=1`：入 Redis List，CLI 消费：
  `php think account:bill-consume --max=100 --loop=1`

## 运维命令

- 预创建当前月和下月分表：
  `php think account:bill-prepare --months=2`
- 从指定账期开始预建 6 个月：
  `php think account:bill-prepare --start=202609 --months=6`
- 持续消费异步入账队列：
  `php think account:bill-consume --max=200 --loop=1 --sleep=1`

## 生产建议

- 定时任务：每月 25~28 日执行一次 `account:bill-prepare --months=2`
- 常驻进程：Supervisor / Windows 任务计划常驻 `account:bill-consume`
- 队列失败 3 次后进入死信 Key：`account:queue:bill:dead`
- 大批量导入优先走异步，避免 Web 请求占满 PHP-FPM/Apache Worker

## Redis Key

- `account:param:list:{category_id}`
- `account:user:prices:{user_id}:{category_id}`
- `account:token:{token}`
- `account:idempotent:{user_id}:{client_req_id}`
- `account:queue:bill`
