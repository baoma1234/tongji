/*
 * 演示种子数据：1 个类目 + 若干参数（可重复执行前先清理 demo 类目）
 */
SET NAMES utf8mb4;

INSERT INTO `fa_acc_category` (`id`, `name`, `code`, `icon`, `weigh`, `status`, `createtime`, `updatetime`)
VALUES (1, '日常记账', 'daily', '', 100, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `status`=1, `updatetime`=UNIX_TIMESTAMP();

DELETE FROM `fa_acc_param` WHERE `category_id` = 1;

INSERT INTO `fa_acc_param` (`category_id`, `name`, `default_price`, `unit`, `weigh`, `status`, `createtime`, `updatetime`) VALUES
(1, '早餐', 15.0000, '次', 100, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '午餐', 25.0000, '次', 99, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '晚餐', 30.0000, '次', 98, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '地铁', 6.0000, '次', 97, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '公交', 2.0000, '次', 96, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '打车', 20.0000, '次', 95, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '咖啡', 28.0000, '杯', 94, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '零食', 10.0000, '份', 93, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '水费', 50.0000, '月', 92, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '电费', 120.0000, '月', 91, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
