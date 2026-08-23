<?php

namespace app\api\controller\account;

use app\common\controller\AccountApi;
use app\common\library\account\PriceCache;

/**
 * 类目参数（Redis 缓存，禁止每次打库拉 40+ 参数）
 */
class Param extends AccountApi
{
    protected $noNeedLogin = [];

    /**
     * 类目参数 + 用户单价合并列表
     * GET/POST category_id
     */
    public function index()
    {
        $categoryId = (int)$this->request->param('category_id', 0);
        if ($categoryId <= 0) {
            $this->error('请指定 category_id');
        }
        $list = PriceCache::getMergedParamList($this->auth->id(), $categoryId);
        $this->success('', ['list' => $list, 'category_id' => $categoryId]);
    }
}
