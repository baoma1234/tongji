<?php

namespace app\common\library\account;

use think\Config;

/**
 * 六合彩生肖映射（按农历年生肖轮转）
 */
class ZodiacHelper
{
    const ANIMALS = ['鼠', '牛', '虎', '兔', '龙', '蛇', '马', '羊', '猴', '鸡', '狗', '猪'];

    /**
     * 近年农历新年公历日期（用于判定当年生肖）
     * 可在 account.zodiac_new_year 覆盖扩展
     */
    protected static $newYearDates = [
        2023 => '2023-01-22', // 兔
        2024 => '2024-02-10', // 龙
        2025 => '2025-01-29', // 蛇
        2026 => '2026-02-17', // 马
        2027 => '2027-02-06', // 羊
        2028 => '2028-01-26', // 猴
        2029 => '2029-02-13', // 鸡
        2030 => '2030-02-03', // 狗
    ];

    /**
     * 基准：2020-01-25 为鼠年元旦
     */
    protected static $baseYear = 2020;
    protected static $baseAnimalIndex = 0; // 鼠

    /**
     * @param string|null $date Y-m-d
     * @return string
     */
    public static function yearAnimal($date = null)
    {
        $forced = Config::get('account.zodiac_year');
        if ($forced && in_array($forced, self::ANIMALS, true)) {
            return $forced;
        }

        $date = $date ?: date('Y-m-d');
        $ts = strtotime($date);
        $year = (int)date('Y', $ts);

        $map = Config::get('account.zodiac_new_year');
        if (is_array($map) && $map) {
            $newYearDates = $map;
        } else {
            $newYearDates = self::$newYearDates;
        }

        // 找不晚于 $date 的最近一个农历新年
        $animalYear = $year;
        if (isset($newYearDates[$year]) && $date < $newYearDates[$year]) {
            $animalYear = $year - 1;
        } elseif (!isset($newYearDates[$year])) {
            // 回退：按公历粗略（2月前算上一年）
            if ((int)date('n', $ts) < 2 || ((int)date('n', $ts) === 2 && (int)date('j', $ts) < 4)) {
                $animalYear = $year - 1;
            }
        }

        $idx = (self::$baseAnimalIndex + ($animalYear - self::$baseYear)) % 12;
        if ($idx < 0) {
            $idx += 12;
        }
        return self::ANIMALS[$idx];
    }

    /**
     * 号码 -> 生肖
     * 当年肖对应号码序列：1=当年肖，2=上一个生肖……
     * @param int         $num  1-49
     * @param string|null $date
     * @return string
     */
    public static function numberToZodiac($num, $date = null)
    {
        $num = (int)$num;
        if ($num < 1 || $num > 49) {
            return '';
        }
        $yearAnimal = self::yearAnimal($date);
        $yearIdx = array_search($yearAnimal, self::ANIMALS, true);
        if ($yearIdx === false) {
            $yearIdx = 0;
        }
        $idx = ($yearIdx - ($num - 1) % 12 + 12) % 12;
        return self::ANIMALS[$idx];
    }

    /**
     * 生肖下挂号码列表
     * @param string      $zodiac
     * @param string|null $date
     * @return int[]
     */
    public static function zodiacNumbers($zodiac, $date = null)
    {
        $out = [];
        for ($n = 1; $n <= 49; $n++) {
            if (self::numberToZodiac($n, $date) === $zodiac) {
                $out[] = $n;
            }
        }
        return $out;
    }
}
