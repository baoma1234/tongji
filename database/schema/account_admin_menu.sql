/*
 * 记账后台菜单（若 php think menu 已执行可跳过）
 * 修正父级标题并删除账单只读模块的写权限节点
 */
SET NAMES utf8mb4;

UPDATE `fa_auth_rule` SET `title` = '记账管理', `icon` = 'fa fa-calculator', `weigh` = 80 WHERE `name` = 'account';
UPDATE `fa_auth_rule` SET `title` = '记账类目' WHERE `name` = 'account/category';
UPDATE `fa_auth_rule` SET `title` = '记账参数' WHERE `name` = 'account/param';
UPDATE `fa_auth_rule` SET `title` = '租户用户' WHERE `name` = 'account/user';
UPDATE `fa_auth_rule` SET `title` = '账单流水' WHERE `name` = 'account/bill';
UPDATE `fa_auth_rule` SET `title` = '查看' WHERE `name` = 'account/bill/index';

DELETE FROM `fa_auth_rule` WHERE `name` IN (
  'account/bill/add',
  'account/bill/edit',
  'account/bill/del',
  'account/bill/multi'
);
