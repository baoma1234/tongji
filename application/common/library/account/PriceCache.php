<?php

namespace app\common\library\account;

use app\common\model\account\Param as ParamModel;
use app\common\model\account\UserPrice as UserPriceModel;
use think\Config;

/**
 * 参数列表 + 用户专属单价缓存
 *
 * Key 规范:
 *   account:param:list:{category_id}              STRING JSON
 *   account:user:prices:{user_id}:{category_id}   HASH field=param_id value=price
 *   account:user:price:{user_id}:{param_id}       STRING（可选单点，改价时同步删）
 */
class PriceCache
{
    /**
     * 获取类目下启用参数（优先 Redis）
     * @param int $categoryId
     * @return array
     */
    public static function getParamList($categoryId)
    {
        $categoryId = (int)$categoryId;
        $redis = RedisClient::handler();
        $key = RedisClient::key('param:list:' . $categoryId);
        $cached = $redis->get($key);
        if ($cached !== false && $cached !== null && $cached !== '') {
            $list = json_decode($cached, true);
            return is_array($list) ? $list : [];
        }

        $rows = ParamModel::where('category_id', $categoryId)
            ->where('status', 1)
            ->order('weigh', 'desc')
            ->order('id', 'asc')
            ->field('id,category_id,name,default_price,unit,weigh')
            ->select();
        $list = [];
        foreach ($rows as $row) {
            $list[] = $row->toArray();
        }
        $ttl = (int)(Config::get('account.param_list_ttl') ?: 86400);
        $redis->setex($key, $ttl, json_encode($list, JSON_UNESCAPED_UNICODE));
        return $list;
    }

    /**
     * 失效类目参数列表
     * @param int $categoryId
     */
    public static function forgetParamList($categoryId)
    {
        RedisClient::handler()->del(RedisClient::key('param:list:' . (int)$categoryId));
    }

    /**
     * 获取用户在某类目下的全部自定义单价 [param_id => price]
     * @param int $userId
     * @param int $categoryId
     * @return array
     */
    public static function getUserPrices($userId, $categoryId)
    {
        $userId = (int)$userId;
        $categoryId = (int)$categoryId;
        $redis = RedisClient::handler();
        $key = RedisClient::key('user:prices:' . $userId . ':' . $categoryId);

        if ($redis->exists($key)) {
            $map = $redis->hGetAll($key);
            $out = [];
            foreach ($map as $pid => $price) {
                // 占位字段表示「已加载但无覆盖」
                if ($pid === '_loaded') {
                    continue;
                }
                $out[(int)$pid] = (string)$price;
            }
            return $out;
        }

        $rows = UserPriceModel::where('user_id', $userId)
            ->where('category_id', $categoryId)
            ->field('param_id,price')
            ->select();
        $out = [];
        $pipeData = ['_loaded' => '1'];
        foreach ($rows as $row) {
            $pid = (int)$row['param_id'];
            $price = (string)$row['price'];
            $out[$pid] = $price;
            $pipeData[(string)$pid] = $price;
        }
        $ttl = (int)(Config::get('account.user_price_ttl') ?: 86400);
        $redis->hMSet($key, $pipeData);
        $redis->expire($key, $ttl);
        return $out;
    }

    /**
     * 合并参数列表与用户单价（前端一次渲染）
     * @param int $userId
     * @param int $categoryId
     * @return array
     */
    public static function getMergedParamList($userId, $categoryId)
    {
        $params = self::getParamList($categoryId);
        $prices = self::getUserPrices($userId, $categoryId);
        foreach ($params as &$item) {
            $pid = (int)$item['id'];
            $item['price'] = isset($prices[$pid]) ? $prices[$pid] : $item['default_price'];
            $item['is_custom'] = isset($prices[$pid]) ? 1 : 0;
        }
        unset($item);
        return $params;
    }

    /**
     * 写入/更新用户单价并失效缓存
     * @param int   $userId
     * @param int   $categoryId
     * @param int   $paramId
     * @param string $price
     */
    public static function setUserPrice($userId, $categoryId, $paramId, $price)
    {
        $userId = (int)$userId;
        $categoryId = (int)$categoryId;
        $paramId = (int)$paramId;
        $now = time();

        $exist = UserPriceModel::where('user_id', $userId)->where('param_id', $paramId)->find();
        if ($exist) {
            // 越权防护：禁止改别人的行（按 user_id 条件更新）
            UserPriceModel::where('id', $exist['id'])->where('user_id', $userId)->update([
                'category_id' => $categoryId,
                'price'       => $price,
                'updatetime'  => $now,
            ]);
        } else {
            UserPriceModel::create([
                'user_id'     => $userId,
                'category_id' => $categoryId,
                'param_id'    => $paramId,
                'price'       => $price,
                'createtime'  => $now,
                'updatetime'  => $now,
            ]);
        }

        self::forgetUserPrices($userId, $categoryId, $paramId);
    }

    /**
     * 批量设置用户专属价（同一类目），最后只失效一次缓存
     * @param int   $userId
     * @param int   $categoryId
     * @param array $paramIds
     * @param string $price
     * @return int
     */
    public static function batchSetUserPrice($userId, $categoryId, array $paramIds, $price)
    {
        $userId = (int)$userId;
        $categoryId = (int)$categoryId;
        $price = (string)$price;
        $now = time();
        $paramIds = array_values(array_unique(array_map('intval', $paramIds)));
        $paramIds = array_filter($paramIds, function ($id) {
            return $id > 0;
        });
        if (!$paramIds) {
            return 0;
        }

        $existMap = [];
        $exists = UserPriceModel::where('user_id', $userId)
            ->where('param_id', 'in', $paramIds)
            ->field('id,param_id')
            ->select();
        foreach ($exists as $row) {
            $existMap[(int)$row['param_id']] = (int)$row['id'];
        }

        $inserts = [];
        foreach ($paramIds as $paramId) {
            if (isset($existMap[$paramId])) {
                UserPriceModel::where('id', $existMap[$paramId])->where('user_id', $userId)->update([
                    'category_id' => $categoryId,
                    'price'       => $price,
                    'updatetime'  => $now,
                ]);
            } else {
                $inserts[] = [
                    'user_id'     => $userId,
                    'category_id' => $categoryId,
                    'param_id'    => $paramId,
                    'price'       => $price,
                    'createtime'  => $now,
                    'updatetime'  => $now,
                ];
            }
        }
        if ($inserts) {
            (new UserPriceModel())->saveAll($inserts);
        }

        self::forgetUserPrices($userId, $categoryId);
        return count($paramIds);
    }

    /**
     * @param int $userId
     * @param int $categoryId
     * @param int $paramId
     */
    public static function forgetUserPrices($userId, $categoryId, $paramId = 0)
    {
        $redis = RedisClient::handler();
        $redis->del(RedisClient::key('user:prices:' . (int)$userId . ':' . (int)$categoryId));
        if ($paramId > 0) {
            $redis->del(RedisClient::key('user:price:' . (int)$userId . ':' . (int)$paramId));
        }
    }
}
