# 记账模块运维说明

## 分表预创建

为避免月初首笔入账触发 `CREATE TABLE ... LIKE`，建议提前创建未来月份分表。

```bash
php think account:bill-prepare --months=2
php think account:bill-prepare --start=202609 --months=6
```

- `--months=2` 表示从当前月开始，连续创建 2 个月
- `--start=YYYYMM` 可指定起始账期

建议在每月 25~28 日执行一次。

## 异步队列消费

```bash
php think account:bill-consume --max=200 --loop=1 --sleep=1
```

- `--max` 单轮最多消费多少条
- `--loop=1` 持续运行
- `--sleep=1` 空闲休眠秒数

## Windows 任务计划示例

可用 `.bat` 包装：

```bat
@echo off
cd /d C:\wwwroot\tongji.com_8866
php think account:bill-consume --max=200 --loop=1 --sleep=1
```

另建一个每月执行一次的任务：

```bat
@echo off
cd /d C:\wwwroot\tongji.com_8866
php think account:bill-prepare --months=2
```

## 死信队列

异步入账失败重试 3 次后进入：

```text
account:queue:bill:dead
```

建议定期检查并人工重放。
