<?php

namespace app\common\model\account;

use think\Model;

/**
 * 账单逻辑模型；实际写入请用 BillService::table($ym)
 */
class Bill extends Model
{
    protected $name = 'acc_bill';
    protected $autoWriteTimestamp = false;
}
