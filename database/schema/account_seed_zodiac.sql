/*
 * 特肖：十二生肖（替换原 01-49）
 * 默认赔率 11
 */
SET NAMES utf8mb4;

UPDATE `fa_acc_category` SET `name`='特肖', `code`='texiao', `weigh`=90, `status`=1, `updatetime`=UNIX_TIMESTAMP() WHERE `id`=2;

DELETE FROM `fa_acc_user_price` WHERE `category_id` = 2;
DELETE FROM `fa_acc_param` WHERE `category_id` = 2;

INSERT INTO `fa_acc_param` (`category_id`, `name`, `default_price`, `unit`, `weigh`, `status`, `createtime`, `updatetime`) VALUES
(2, '鼠', 11.0000, '注', 12, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(2, '牛', 11.0000, '注', 11, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(2, '虎', 11.0000, '注', 10, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(2, '兔', 11.0000, '注', 9, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(2, '龙', 11.0000, '注', 8, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(2, '蛇', 11.0000, '注', 7, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(2, '马', 11.0000, '注', 6, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(2, '羊', 11.0000, '注', 5, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(2, '猴', 11.0000, '注', 4, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(2, '鸡', 11.0000, '注', 3, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(2, '狗', 11.0000, '注', 2, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(2, '猪', 11.0000, '注', 1, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
