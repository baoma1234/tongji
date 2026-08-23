<?php

namespace app\api\controller\account;

use app\common\controller\AccountApi;
use app\common\model\account\Category as CategoryModel;

/**
 * 类目
 */
class Category extends AccountApi
{
    protected $noNeedLogin = [];

    /**
     * 启用类目列表
     */
    public function index()
    {
        $list = CategoryModel::where('status', 1)
            ->order('weigh', 'desc')
            ->order('id', 'asc')
            ->field('id,name,code,icon,weigh')
            ->select();
        $this->success('', ['list' => $list]);
    }
}
