# 极简记账前端 (Vue3 + Element Plus)

## 开发

```bash
cd web
npm install
npm run dev
```

默认代理 API 到 `http://127.0.0.1:8866`（可在 `vite.config.js` 修改）。

访问：http://127.0.0.1:5173/account/

## 构建并部署到 FastAdmin public

```bash
cd web
npm run build
```

产物输出到 `public/account/`，线上访问：`https://你的域名/account/`

## 性能要点

- 参数列表：`shallowRef` + `Object.freeze`，勾选用 `Set` 整体替换
- 长列表：`useVirtualList` 虚拟滚动
- 批量提交：`useDebounceFn` 防抖 + 后端 `client_req_id` 幂等
- 流水页：`el-table-v2` 虚拟表格
