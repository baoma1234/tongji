/*
 ============================================================================
 * 多租户记账系统 - 核心 5 表 DDL（百万级读写优化）
 * MySQL 5.7+ / 8.0 兼容 | 表前缀 fa_ | 禁止物理外键（关联由代码层保证）
 * 生成日期: 2026-08-24
 * ============================================================================
 */

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- 1. 用户表 fa_acc_user
-- 极简入口：专属密码(access_code)即身份，无需注册；数据隔离主键 user_id
-- ============================================================================
DROP TABLE IF EXISTS `fa_acc_user`;
CREATE TABLE `fa_acc_user` (
  `id`            bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '用户ID',
  `access_code`   varchar(64)  NOT NULL COMMENT '专属登录密码/口令(如 qwer1234、001)，全局唯一',
  `nickname`      varchar(64)  NOT NULL DEFAULT '' COMMENT '显示昵称',
  `salt`          varchar(16)  NOT NULL DEFAULT '' COMMENT '密码盐(预留二次校验/升级哈希)',
  `password`      varchar(64)  NOT NULL DEFAULT '' COMMENT '口令哈希(应用层写入，禁止明文落库生产环境)',
  `status`        tinyint(1)   NOT NULL DEFAULT 1 COMMENT '状态:1=正常,0=禁用',
  `logintime`     bigint(16)   DEFAULT NULL COMMENT '最近登录时间(Unix)',
  `loginip`       varchar(50)  NOT NULL DEFAULT '' COMMENT '最近登录IP',
  `loginfailure`  tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '连续登录失败次数',
  `createtime`    bigint(16)   DEFAULT NULL COMMENT '创建时间',
  `updatetime`    bigint(16)   DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_access_code` (`access_code`),
  KEY `idx_status_createtime` (`status`, `createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='记账-租户用户表';

-- ============================================================================
-- 2. 分类表 fa_acc_category
-- 类目主数据量小，靠 Redis 全量/按状态缓存；weigh 控制前端展示顺序
-- ============================================================================
DROP TABLE IF EXISTS `fa_acc_category`;
CREATE TABLE `fa_acc_category` (
  `id`          int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '分类ID',
  `name`        varchar(64)  NOT NULL DEFAULT '' COMMENT '分类名称',
  `code`        varchar(32)  DEFAULT NULL COMMENT '业务编码(可选，空则NULL，保证uk不冲突)',
  `icon`        varchar(128) NOT NULL DEFAULT '' COMMENT '图标',
  `weigh`       int(10)      NOT NULL DEFAULT 0 COMMENT '权重(越大越靠前)',
  `status`      tinyint(1)   NOT NULL DEFAULT 1 COMMENT '状态:1=启用,0=禁用',
  `createtime`  bigint(16)   DEFAULT NULL COMMENT '创建时间',
  `updatetime`  bigint(16)   DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_status_weigh` (`status`, `weigh`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='记账-类目表';

-- ============================================================================
-- 3. 参数表 fa_acc_param
-- 每类目约几十个参数；查询模式: WHERE category_id=? AND status=1 ORDER BY weigh DESC
-- 默认单价存在本表，用户覆盖价在 user_price；列表必须走 Redis，禁止每次进类目打 DB
-- ============================================================================
DROP TABLE IF EXISTS `fa_acc_param`;
CREATE TABLE `fa_acc_param` (
  `id`             int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '参数ID',
  `category_id`    int(10) unsigned NOT NULL DEFAULT 0 COMMENT '所属分类ID(代码层关联)',
  `name`           varchar(128) NOT NULL DEFAULT '' COMMENT '参数名称',
  `default_price`  decimal(12,4) NOT NULL DEFAULT 0.0000 COMMENT '系统默认单价',
  `unit`           varchar(16)  NOT NULL DEFAULT '' COMMENT '计量单位(个/次/小时等)',
  `weigh`          int(10)      NOT NULL DEFAULT 0 COMMENT '权重(越大越靠前)',
  `status`         tinyint(1)   NOT NULL DEFAULT 1 COMMENT '状态:1=启用,0=禁用',
  `createtime`     bigint(16)   DEFAULT NULL COMMENT '创建时间',
  `updatetime`     bigint(16)   DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_category_status_weigh` (`category_id`, `status`, `weigh`),
  KEY `idx_category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='记账-类目参数表';

-- ============================================================================
-- 4. 用户专属价格表 fa_acc_user_price
-- 仅存储“用户覆盖了默认价”的行(稀疏表)；未覆盖则读 param.default_price
-- 热点查询: user_id + category_id 一次拉出该类目全部自定义价，再与参数列表 merge
-- Redis Key 建议: account:user:price:{user_id}:{param_id} 及 Hash account:user:prices:{user_id}:{category_id}
-- ============================================================================
DROP TABLE IF EXISTS `fa_acc_user_price`;
CREATE TABLE `fa_acc_user_price` (
  `id`           bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `user_id`      bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT '用户ID',
  `category_id`  int(10) unsigned NOT NULL DEFAULT 0 COMMENT '分类ID(冗余，避免回表联查)',
  `param_id`     int(10) unsigned NOT NULL DEFAULT 0 COMMENT '参数ID',
  `price`        decimal(12,4) NOT NULL DEFAULT 0.0000 COMMENT '用户专属单价',
  `createtime`   bigint(16)   DEFAULT NULL COMMENT '创建时间',
  `updatetime`   bigint(16)   DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_param` (`user_id`, `param_id`),
  KEY `idx_user_category` (`user_id`, `category_id`),
  KEY `idx_param_id` (`param_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='记账-用户专属单价表';

-- ============================================================================
-- 5. 账单流水表 fa_acc_bill（逻辑表 / 当月热表模板）
-- 分表策略见文件末尾说明；应用层按 bill_ym 路由到 fa_acc_bill_YYYYMM
-- 金额字段落库固化，禁止报表时再算一遍造成 CPU 抖动
-- ============================================================================
DROP TABLE IF EXISTS `fa_acc_bill`;
CREATE TABLE `fa_acc_bill` (
  `id`            bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '流水ID',
  `user_id`       bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT '用户ID(租户隔离)',
  `category_id`   int(10) unsigned NOT NULL DEFAULT 0 COMMENT '分类ID',
  `param_id`      int(10) unsigned NOT NULL DEFAULT 0 COMMENT '参数ID',
  `param_name`    varchar(128) NOT NULL DEFAULT '' COMMENT '参数名称快照(防改名影响历史)',
  `quantity`      decimal(12,4) NOT NULL DEFAULT 0.0000 COMMENT '数量',
  `unit_price`    decimal(12,4) NOT NULL DEFAULT 0.0000 COMMENT '成交单价快照',
  `amount`        decimal(14,4) NOT NULL DEFAULT 0.0000 COMMENT '金额=quantity*unit_price(写入时固化)',
  `batch_id`      varchar(64)  NOT NULL DEFAULT '' COMMENT '批量入账批次号(同一次勾选共用)',
  `client_req_id` varchar(64)  NOT NULL DEFAULT '' COMMENT '客户端幂等键(防连点/重试)',
  `remark`        varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `bill_date`     date         NOT NULL COMMENT '业务日期(报表按日聚合)',
  `bill_ym`       mediumint(6) unsigned NOT NULL DEFAULT 0 COMMENT '账期YYYYMM(分表路由/归档)',
  `createtime`    bigint(16)   DEFAULT NULL COMMENT '入账时间',
  `updatetime`    bigint(16)   DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_createtime` (`user_id`, `createtime`),
  KEY `idx_user_category_createtime` (`user_id`, `category_id`, `createtime`),
  KEY `idx_user_bill_date` (`user_id`, `bill_date`),
  KEY `idx_user_bill_ym` (`user_id`, `bill_ym`),
  KEY `idx_batch_id` (`batch_id`),
  KEY `idx_user_client_req` (`user_id`, `client_req_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='记账-账单流水表(逻辑/热表)';

-- 当月物理分表示例（与逻辑表结构完全一致，应用层写入路由到此表）
DROP TABLE IF EXISTS `fa_acc_bill_202608`;
CREATE TABLE `fa_acc_bill_202608` LIKE `fa_acc_bill`;

SET FOREIGN_KEY_CHECKS = 1;

/*
 * ============================================================================
 * 【账单分表策略】推荐：按月分表（优先） + 超大规模再按 user_id hash 二次分片
 * ----------------------------------------------------------------------------
 * 1) 表名约定: fa_acc_bill_YYYYMM ，如 fa_acc_bill_202608
 * 2) 路由: $table = 'fa_acc_bill_' . date('Ym', $billTime);
 * 3) 建表: CREATE TABLE fa_acc_bill_202609 LIKE fa_acc_bill;
 *    建议用定时任务每月提前创建下月空表，避免写入时 DDL 锁。
 * 4) 为何优先按月而不是纯 user_id hash:
 *    - 业务查询多为「某用户最近账单 / 某月汇总」，时间局部性强，按月归档/冷热分离简单；
 *    - 单月表可控，历史月可迁冷存或只读从库。
 * 5) 当单月仍超千万行时，二次分片:
 *    fa_acc_bill_YYYYMM_{user_id % N} ，N=8/16/32；
 *    查询必须带 user_id，保证命中单一分片。
 * 6) 跨月报表: 应用层按月份并集查询，禁止 UNION 无边界扫全部分表。
 * 7) 禁止物理外键: 高并发批量写入时 FK 检查会放大锁竞争与死锁概率。
 * ============================================================================
 */
