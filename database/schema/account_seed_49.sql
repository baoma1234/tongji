/*
 * 演示数据：1 类目 49 参数 + 1 业务类目（10 参数）
 * 可重复执行（按 category_id 重建参数）
 */
SET NAMES utf8mb4;

INSERT INTO `fa_acc_category` (`id`, `name`, `code`, `icon`, `weigh`, `status`, `createtime`, `updatetime`) VALUES
(1, '日常记账', 'daily', 'fa fa-coffee', 100, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(2, '业务结算', 'biz', 'fa fa-briefcase', 90, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `status`=1, `updatetime`=UNIX_TIMESTAMP();

DELETE FROM `fa_acc_param` WHERE `category_id` IN (1, 2);

INSERT INTO `fa_acc_param` (`category_id`, `name`, `default_price`, `unit`, `weigh`, `status`, `createtime`, `updatetime`) VALUES
(1, '早餐', 15.0000, '次', 149, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '午餐', 25.0000, '次', 148, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '晚餐', 30.0000, '次', 147, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '夜宵', 18.0000, '次', 146, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '咖啡', 28.0000, '杯', 145, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '奶茶', 16.0000, '杯', 144, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '地铁', 6.0000, '次', 143, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '公交', 2.0000, '次', 142, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '打车', 20.0000, '次', 141, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '共享单车', 3.0000, '次', 140, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '停车费', 10.0000, '次', 139, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '加油', 300.0000, '次', 138, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '水电费', 120.0000, '月', 137, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '燃气费', 80.0000, '月', 136, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '物业费', 200.0000, '月', 135, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '房租', 2500.0000, '月', 134, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '宽带', 99.0000, '月', 133, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '手机话费', 59.0000, '月', 132, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '超市购物', 88.0000, '次', 131, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '水果', 35.0000, '次', 130, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '蔬菜', 20.0000, '次', 129, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '牛奶', 12.0000, '盒', 128, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '零食', 10.0000, '份', 127, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '外卖', 32.0000, '次', 126, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '理发', 45.0000, '次', 125, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '洗衣', 25.0000, '次', 124, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '健身', 80.0000, '次', 123, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '游泳', 60.0000, '次', 122, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '电影', 45.0000, '次', 121, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, 'KTV', 120.0000, '次', 120, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '游戏充值', 68.0000, '次', 119, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '书籍', 39.0000, '本', 118, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '文具', 15.0000, '次', 117, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '快递', 12.0000, '次', 116, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '药品', 55.0000, '次', 115, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '体检', 380.0000, '次', 114, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '牙科', 200.0000, '次', 113, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '眼镜', 350.0000, '次', 112, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '宠物粮', 89.0000, '袋', 111, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '宠物医疗', 150.0000, '次', 110, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '人情礼金', 200.0000, '次', 109, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '捐款', 50.0000, '次', 108, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '旅行', 800.0000, '次', 107, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '酒店', 320.0000, '晚', 106, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '机票', 680.0000, '次', 105, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '高铁', 220.0000, '次', 104, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '保险', 1200.0000, '年', 103, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '学习课程', 199.0000, '门', 102, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(1, '会员订阅', 25.0000, '月', 101, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

INSERT INTO `fa_acc_param` (`category_id`, `name`, `default_price`, `unit`, `weigh`, `status`, `createtime`, `updatetime`) VALUES
(2, '设计稿', 500.0000, '份', 100, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(2, '前端页面', 800.0000, '页', 99, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(2, '接口开发', 300.0000, '个', 98, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(2, '测试用例', 150.0000, '条', 97, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(2, '部署上线', 200.0000, '次', 96, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(2, '运维监控', 120.0000, '月', 95, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(2, 'SEO优化', 260.0000, '次', 94, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(2, '广告投放', 1000.0000, '次', 93, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(2, '文案撰写', 80.0000, '篇', 92, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(2, '视频剪辑', 350.0000, '条', 91, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
