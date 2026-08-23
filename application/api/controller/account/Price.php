<?php

namespace app\api\controller\account;

use app\common\controller\AccountApi;
use app\common\library\account\PriceCache;
use app\common\model\account\Param as ParamModel;

/**
 * 用户专属单价
 */
class Price extends AccountApi
{
    protected $noNeedLogin = [];

    /**
     * 设置专属单价（仅能改自己的）
     * POST param_id, price, category_id?
     */
    public function set()
    {
        if (!$this->request->isPost()) {
            $this->error('请使用 POST');
        }
        $userId = $this->auth->id();
        $paramId = (int)$this->request->post('param_id', 0);
        $price = $this->request->post('price', '');
        $categoryId = (int)$this->request->post('category_id', 0);

        if ($paramId <= 0 || !is_numeric($price) || bccomp((string)$price, '0', 4) < 0) {
            $this->error('参数错误');
        }

        $param = ParamModel::where('id', $paramId)->where('status', 1)->find();
        if (!$param) {
            $this->error('参数不存在');
        }
        if ($categoryId > 0 && (int)$param['category_id'] !== $categoryId) {
            $this->error('类目与参数不匹配');
        }
        $categoryId = (int)$param['category_id'];

        PriceCache::setUserPrice($userId, $categoryId, $paramId, (string)$price);
        $this->success('已更新', [
            'param_id'    => $paramId,
            'category_id' => $categoryId,
            'price'       => (string)$price,
        ]);
    }
}
