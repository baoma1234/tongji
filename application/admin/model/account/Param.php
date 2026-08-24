<?php

namespace app\admin\model\account;

use app\common\library\account\PriceCache;
use think\Model;

class Param extends Model
{
    protected $name = 'acc_param';
    protected $autoWriteTimestamp = 'integer';
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    protected $append = ['status_text'];

    protected static function init()
    {
        self::afterInsert(function ($row) {
            PriceCache::forgetParamList($row['category_id']);
        });
        self::afterUpdate(function ($row) {
            PriceCache::forgetParamList($row['category_id']);
            $origin = $row->getOrigin();
            if ($origin && (int)$origin['category_id'] !== (int)$row['category_id']) {
                PriceCache::forgetParamList($origin['category_id']);
            }
        });
        self::afterDelete(function ($row) {
            PriceCache::forgetParamList($row['category_id']);
        });
    }

    public function getStatusList()
    {
        return ['1' => __('Status 1'), '0' => __('Status 0')];
    }

    public function getStatusTextAttr($value, $data)
    {
        $value = $value ?: ($data['status'] ?? '');
        $list = $this->getStatusList();
        return $list[(string)$value] ?? '';
    }

    public function category()
    {
        return $this->belongsTo('Category', 'category_id', 'id', [], 'LEFT')->setEagerlyType(0);
    }
}
