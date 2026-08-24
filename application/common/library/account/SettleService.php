<?php

namespace app\common\library\account;

use think\Db;
use think\Exception;

/**
 * 开奖结算
 *
 * 规则：
 * - 特码：开奖第7位(特码) = 投注号码
 * - 特肖：特码所属生肖 = 投注生肖
 * - 平码：投注号码出现在前6个正码中
 * - 平特：投注号码出现在7个开奖号(正码+特码)任一中
 */
class SettleService
{
    /**
     * @param int   $userId
     * @param array $payload
     *  - numbers: int[7] 正码1-6 + 特码
     *  - bill_date: Y-m-d 结算哪天的入账，默认今天
     * @return array
     * @throws Exception
     */
    public static function settle($userId, array $payload)
    {
        $userId = (int)$userId;
        $numbers = isset($payload['numbers']) ? $payload['numbers'] : [];
        $billDate = !empty($payload['bill_date']) ? $payload['bill_date'] : date('Y-m-d');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $billDate)) {
            throw new Exception('日期格式错误');
        }

        $draw = self::normalizeDraw($numbers);
        $zheng = array_slice($draw, 0, 6);
        $tema = $draw[6];
        $all = $draw;
        $temaZodiac = ZodiacHelper::numberToZodiac($tema, $billDate);
        $yearAnimal = ZodiacHelper::yearAnimal($billDate);

        $cats = Db::name('acc_category')->where('status', 1)->field('id,code,name')->select();
        $categoryMap = [];
        foreach ($cats as $c) {
            $categoryMap[(int)$c['id']] = $c;
        }

        $ym = (int)date('Ym', strtotime($billDate));
        BillService::ensureMonthTable($ym);
        $table = BillService::table($ym);

        $bills = Db::name($table)
            ->where('user_id', $userId)
            ->where('bill_date', $billDate)
            ->order('id', 'asc')
            ->select();

        $rows = [];
        $summary = [
            'stake'       => '0',
            'payout'      => '0',
            'profit'      => '0',
            'bet_count'   => 0,
            'win_count'   => 0,
            'lose_count'  => 0,
            'by_category' => [],
        ];

        foreach ($bills as $bill) {
            $catId = (int)$bill['category_id'];
            $code = isset($categoryMap[$catId]) ? (string)$categoryMap[$catId]['code'] : '';
            $catName = isset($categoryMap[$catId]) ? (string)$categoryMap[$catId]['name'] : '';

            $hit = self::isWin($code, $catName, $bill['param_name'], $zheng, $tema, $temaZodiac, $all, $billDate);
            $qty = (string)$bill['quantity'];
            $odds = (string)$bill['unit_price'];
            $stake = $qty; // 1注=1成本单位，便于统计
            $payout = $hit ? bcmul($qty, $odds, 4) : '0.0000';
            $profit = bcsub($payout, $stake, 4);

            $row = [
                'bill_id'      => (int)$bill['id'],
                'category_id'  => $catId,
                'category'     => $catName,
                'category_code'=> $code,
                'param_name'   => $bill['param_name'],
                'quantity'     => $qty,
                'odds'         => $odds,
                'stake'        => $stake,
                'hit'          => $hit ? 1 : 0,
                'payout'       => $payout,
                'profit'       => $profit,
                'batch_id'     => $bill['batch_id'],
            ];
            $rows[] = $row;

            $summary['bet_count']++;
            if ($hit) {
                $summary['win_count']++;
            } else {
                $summary['lose_count']++;
            }
            $summary['stake'] = bcadd($summary['stake'], $stake, 4);
            $summary['payout'] = bcadd($summary['payout'], $payout, 4);

            $ck = $catName ?: ('cat_' . $catId);
            if (!isset($summary['by_category'][$ck])) {
                $summary['by_category'][$ck] = [
                    'category'   => $ck,
                    'bet_count'  => 0,
                    'win_count'  => 0,
                    'stake'      => '0',
                    'payout'     => '0',
                    'profit'     => '0',
                ];
            }
            $summary['by_category'][$ck]['bet_count']++;
            if ($hit) {
                $summary['by_category'][$ck]['win_count']++;
            }
            $summary['by_category'][$ck]['stake'] = bcadd($summary['by_category'][$ck]['stake'], $stake, 4);
            $summary['by_category'][$ck]['payout'] = bcadd($summary['by_category'][$ck]['payout'], $payout, 4);
            $summary['by_category'][$ck]['profit'] = bcsub(
                $summary['by_category'][$ck]['payout'],
                $summary['by_category'][$ck]['stake'],
                4
            );
        }

        $summary['profit'] = bcsub($summary['payout'], $summary['stake'], 4);
        $summary['by_category'] = array_values($summary['by_category']);

        return [
            'bill_date'   => $billDate,
            'draw'        => [
                'zhengma'     => $zheng,
                'tema'        => $tema,
                'tema_zodiac' => $temaZodiac,
                'year_animal' => $yearAnimal,
                'numbers'     => $draw,
            ],
            'summary'     => $summary,
            'list'        => $rows,
        ];
    }

    /**
     * @param array $numbers
     * @return int[] length 7
     * @throws Exception
     */
    protected static function normalizeDraw($numbers)
    {
        if (is_string($numbers)) {
            $numbers = preg_split('/[\s,，、;；]+/', trim($numbers));
        }
        if (!is_array($numbers)) {
            throw new Exception('请输入开奖号码');
        }
        $out = [];
        foreach ($numbers as $n) {
            $n = (int)preg_replace('/\D/', '', (string)$n);
            if ($n < 1 || $n > 49) {
                continue;
            }
            $out[] = $n;
        }
        $out = array_values(array_unique($out));
        if (count($out) !== 7) {
            throw new Exception('请输入7个不重复号码（正码6个+特码1个）');
        }
        return $out;
    }

    /**
     * @param string $code
     * @param string $catName
     * @param string $paramName
     * @param int[]  $zheng
     * @param int    $tema
     * @param string $temaZodiac
     * @param int[]  $all
     * @param string $date
     * @return bool
     */
    protected static function isWin($code, $catName, $paramName, $zheng, $tema, $temaZodiac, $all, $date)
    {
        $code = strtolower(trim((string)$code));
        $name = trim((string)$catName);

        if ($code === 'tema' || $name === '特码') {
            $bet = (int)preg_replace('/\D/', '', $paramName);
            return $bet > 0 && $bet === (int)$tema;
        }

        if ($code === 'texiao' || $name === '特肖') {
            return $paramName === $temaZodiac;
        }

        if ($code === 'pingma' || $name === '平码') {
            $bet = (int)preg_replace('/\D/', '', $paramName);
            return $bet > 0 && in_array($bet, $zheng, true);
        }

        if ($code === 'pingte' || $name === '平特') {
            // 号码盘：出现在7个开奖号任一；若投注为生肖则看7码生肖是否命中
            if (in_array($paramName, ZodiacHelper::ANIMALS, true)) {
                foreach ($all as $n) {
                    if (ZodiacHelper::numberToZodiac($n, $date) === $paramName) {
                        return true;
                    }
                }
                return false;
            }
            $bet = (int)preg_replace('/\D/', '', $paramName);
            return $bet > 0 && in_array($bet, $all, true);
        }

        return false;
    }
}
