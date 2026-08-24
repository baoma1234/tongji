<?php

namespace app\api\controller\account;

use app\common\controller\AccountApi;
use app\common\library\account\SettleService;
use app\common\library\account\ZodiacHelper;
use think\Exception;

/**
 * 开奖结算
 */
class Settle extends AccountApi
{
    protected $noNeedLogin = [];

    /**
     * 输入开奖号码，自动核对当日入账并算出中奖
     *
     * POST numbers[] / numbers=01,12,...  bill_date?
     */
    public function calc()
    {
        if (!$this->request->isPost()) {
            $this->error('请使用 POST');
        }

        $numbers = $this->request->post('numbers/a', []);
        if (!$numbers) {
            $raw = $this->request->post('numbers', '');
            if ($raw === '') {
                $content = $this->request->getContent();
                $json = $content ? json_decode($content, true) : null;
                if (is_array($json)) {
                    $numbers = isset($json['numbers']) ? $json['numbers'] : [];
                    $billDate = isset($json['bill_date']) ? $json['bill_date'] : '';
                }
            } else {
                $numbers = $raw;
            }
        }
        $billDate = isset($billDate) ? $billDate : $this->request->post('bill_date', '');

        try {
            $result = SettleService::settle($this->auth->id(), [
                'numbers'   => $numbers,
                'bill_date' => $billDate ?: date('Y-m-d'),
            ]);
            $this->success('结算完成', $result);
        } catch (Exception $e) {
            $this->error($e->getMessage());
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    /**
     * 查询某号码生肖（调试/展示）
     */
    public function zodiac()
    {
        $num = (int)$this->request->param('num', 0);
        $date = $this->request->param('date', date('Y-m-d'));
        if ($num < 1 || $num > 49) {
            $this->error('号码无效');
        }
        $this->success('', [
            'num'         => $num,
            'zodiac'      => ZodiacHelper::numberToZodiac($num, $date),
            'year_animal' => ZodiacHelper::yearAnimal($date),
            'numbers'     => ZodiacHelper::zodiacNumbers(ZodiacHelper::numberToZodiac($num, $date), $date),
        ]);
    }
}
