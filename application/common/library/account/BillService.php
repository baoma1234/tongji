<?php

namespace app\common\library\account;

use think\Config;
use think\Db;
use think\Exception;

/**
 * 批量入账：事务同步 / Redis 队列异步
 */
class BillService
{
    /**
     * 物理表名（含前缀由 Db::name 处理，这里返回无前缀名）
     * @param int|null $ym YYYYMM
     * @return string
     */
    public static function table($ym = null)
    {
        $ym = $ym ? (int)$ym : (int)date('Ym');
        return 'acc_bill_' . $ym;
    }

    /**
     * 确保月分表存在（LIKE 模板表，无数据拷贝）
     * @param int $ym
     */
    public static function ensureMonthTable($ym)
    {
        $ym = (int)$ym;
        $prefix = config('database.prefix') ?: 'fa_';
        $table = $prefix . 'acc_bill_' . $ym;
        $exists = Db::query("SHOW TABLES LIKE '{$table}'");
        if (!$exists) {
            $tpl = $prefix . 'acc_bill';
            Db::execute("CREATE TABLE `{$table}` LIKE `{$tpl}`");
        }
    }

    /**
     * 批量入账入口
     *
     * @param int   $userId
     * @param array $payload
     *  - category_id int
     *  - quantity decimal 统一数量（items 内可覆盖）
     *  - items [ ['param_id'=>, 'quantity'=>optional], ... ]
     *  - bill_date Y-m-d optional
     *  - remark
     *  - client_req_id 幂等键
     *  - async bool|null 强制异步
     * @return array
     * @throws Exception
     */
    public static function batchEntry($userId, array $payload)
    {
        $userId = (int)$userId;
        $categoryId = isset($payload['category_id']) ? (int)$payload['category_id'] : 0;
        $items = isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : [];
        $defaultQty = isset($payload['quantity']) ? (string)$payload['quantity'] : '1';
        $remark = isset($payload['remark']) ? mb_substr((string)$payload['remark'], 0, 255) : '';
        $clientReqId = isset($payload['client_req_id']) ? trim((string)$payload['client_req_id']) : '';
        $billDate = !empty($payload['bill_date']) ? $payload['bill_date'] : date('Y-m-d');
        $forceAsync = !empty($payload['async']);

        if ($userId <= 0 || $categoryId <= 0 || !$items) {
            throw new Exception('参数错误');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $billDate)) {
            throw new Exception('业务日期格式错误');
        }

        // 幂等：同一用户同一 client_req_id 只处理一次
        if ($clientReqId !== '') {
            if (strlen($clientReqId) > 64) {
                throw new Exception('幂等键过长');
            }
            $redis = RedisClient::handler();
            $idemKey = RedisClient::key('idempotent:' . $userId . ':' . $clientReqId);
            $ttl = (int)(Config::get('account.idempotent_ttl') ?: 86400);
            if (!$redis->set($idemKey, '1', ['nx', 'ex' => $ttl])) {
                throw new Exception('请勿重复提交');
            }
        }

        $syncMax = (int)(Config::get('account.sync_batch_max') ?: 100);
        $needAsync = $forceAsync || count($items) > $syncMax;

        $batchId = date('YmdHis') . sprintf('%04d', mt_rand(0, 9999)) . $userId;
        $job = [
            'user_id'       => $userId,
            'category_id'   => $categoryId,
            'quantity'      => $defaultQty,
            'items'         => $items,
            'remark'        => $remark,
            'client_req_id' => $clientReqId,
            'bill_date'     => $billDate,
            'batch_id'      => $batchId,
            'created_at'    => time(),
        ];

        if ($needAsync) {
            $queueKey = RedisClient::key(Config::get('account.bill_queue_key') ?: 'queue:bill');
            RedisClient::handler()->rPush($queueKey, json_encode($job, JSON_UNESCAPED_UNICODE));
            return [
                'mode'     => 'async',
                'batch_id' => $batchId,
                'queued'   => count($items),
                'message'  => '已进入异步入账队列',
            ];
        }

        $count = self::commitBatch($job);
        return [
            'mode'     => 'sync',
            'batch_id' => $batchId,
            'inserted' => $count,
        ];
    }

    /**
     * 事务写入一批账单（同步或队列消费者调用）
     * @param array $job
     * @return int 写入行数
     * @throws Exception
     */
    public static function commitBatch(array $job)
    {
        $userId = (int)$job['user_id'];
        $categoryId = (int)$job['category_id'];
        $defaultQty = isset($job['quantity']) ? (string)$job['quantity'] : '1';
        $items = $job['items'];
        $remark = isset($job['remark']) ? $job['remark'] : '';
        $clientReqId = isset($job['client_req_id']) ? $job['client_req_id'] : '';
        $billDate = $job['bill_date'];
        $batchId = $job['batch_id'];
        $billYm = (int)date('Ym', strtotime($billDate));
        $now = time();

        // 拉取参数 + 用户价（走缓存，禁止 N+1）
        $merged = PriceCache::getMergedParamList($userId, $categoryId);
        $paramMap = [];
        foreach ($merged as $row) {
            $paramMap[(int)$row['id']] = $row;
        }

        $rows = [];
        foreach ($items as $item) {
            $paramId = isset($item['param_id']) ? (int)$item['param_id'] : 0;
            if ($paramId <= 0 || !isset($paramMap[$paramId])) {
                throw new Exception('存在无效或不属于该类目的参数: ' . $paramId);
            }
            // 越权：参数必须属于请求的 category_id（已由 paramMap 保证）
            if ((int)$paramMap[$paramId]['category_id'] !== $categoryId) {
                throw new Exception('参数类目不匹配');
            }
            $qty = isset($item['quantity']) && $item['quantity'] !== '' && $item['quantity'] !== null
                ? (string)$item['quantity'] : $defaultQty;
            if (!is_numeric($qty) || bccomp($qty, '0', 4) <= 0) {
                throw new Exception('数量必须大于0');
            }
            $unitPrice = (string)$paramMap[$paramId]['price'];
            $amount = bcmul($qty, $unitPrice, 4);
            $rows[] = [
                'user_id'       => $userId,
                'category_id'   => $categoryId,
                'param_id'      => $paramId,
                'param_name'    => $paramMap[$paramId]['name'],
                'quantity'      => $qty,
                'unit_price'    => $unitPrice,
                'amount'        => $amount,
                'batch_id'      => $batchId,
                'client_req_id' => $clientReqId,
                'remark'        => $remark,
                'bill_date'     => $billDate,
                'bill_ym'       => $billYm,
                'createtime'    => $now,
                'updatetime'    => $now,
            ];
        }

        if (!$rows) {
            throw new Exception('无有效入账明细');
        }

        self::ensureMonthTable($billYm);
        $table = self::table($billYm);

        Db::startTrans();
        try {
            // 分片 insert，避免单 SQL 过大；同事务保证原子性
            $chunkSize = 50;
            foreach (array_chunk($rows, $chunkSize) as $chunk) {
                Db::name($table)->insertAll($chunk);
            }
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw new Exception('入账失败: ' . $e->getMessage());
        }

        return count($rows);
    }

    /**
     * 消费异步队列（CLI 调用），返回处理条数
     * @param int $max
     * @return int
     */
    public static function consumeQueue($max = 50)
    {
        $redis = RedisClient::handler();
        $queueKey = RedisClient::key(Config::get('account.bill_queue_key') ?: 'queue:bill');
        $done = 0;
        for ($i = 0; $i < $max; $i++) {
            $raw = $redis->lPop($queueKey);
            if (!$raw) {
                break;
            }
            $job = json_decode($raw, true);
            if (!$job) {
                continue;
            }
            try {
                self::commitBatch($job);
                $done++;
            } catch (\Exception $e) {
                // 失败回队尾，避免丢单；生产可加 retry 计数进死信
                $job['retry'] = isset($job['retry']) ? ((int)$job['retry'] + 1) : 1;
                if ($job['retry'] <= 3) {
                    $redis->rPush($queueKey, json_encode($job, JSON_UNESCAPED_UNICODE));
                } else {
                    $redis->rPush(RedisClient::key('queue:bill:dead'), json_encode([
                        'job'   => $job,
                        'error' => $e->getMessage(),
                        'at'    => time(),
                    ], JSON_UNESCAPED_UNICODE));
                }
            }
        }
        return $done;
    }

    /**
     * 用户账单分页（强制 user_id 条件，防越权）
     * @param int   $userId
     * @param array $query
     * @return array
     */
    public static function listByUser($userId, array $query)
    {
        $userId = (int)$userId;
        $ym = !empty($query['bill_ym']) ? (int)$query['bill_ym'] : (int)date('Ym');
        $page = max(1, isset($query['page']) ? (int)$query['page'] : 1);
        $limit = min(100, max(1, isset($query['limit']) ? (int)$query['limit'] : 20));
        $categoryId = isset($query['category_id']) ? (int)$query['category_id'] : 0;

        self::ensureMonthTable($ym);
        $table = self::table($ym);

        $countQuery = Db::name($table)->where('user_id', $userId);
        $listQuery = Db::name($table)->where('user_id', $userId);
        if ($categoryId > 0) {
            $countQuery->where('category_id', $categoryId);
            $listQuery->where('category_id', $categoryId);
        }
        $total = $countQuery->count();
        $list = $listQuery->order('id', 'desc')->page($page, $limit)->select();
        return ['total' => $total, 'page' => $page, 'limit' => $limit, 'bill_ym' => $ym, 'list' => $list];
    }
}
