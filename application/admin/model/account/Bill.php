<?php

namespace app\admin\model\account;

use think\Model;

/** 账单逻辑模型，实际表名在控制器按 bill_ym 动态切换 */
class Bill extends Model
{
    protected $name = 'acc_bill';
    protected $autoWriteTimestamp = false;
}
