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

    /**
     * 批量设置专属赔率（当前用户 + 类目）
     * POST category_id, price, param_ids?（空则该类目全部）
     */
    public function batchSet()
    {
        if (!$this->request->isPost()) {
            $this->error('请使用 POST');
        }
        $userId = $this->auth->id();
        $categoryId = (int)$this->request->post('category_id', 0);
        $price = $this->request->post('price', '');
        $paramIds = $this->request->post('param_ids/a', []);

        if ($categoryId <= 0 || !is_numeric($price) || bccomp((string)$price, '0', 4) < 0) {
            $this->error('参数错误');
        }

        $query = ParamModel::where('category_id', $categoryId)->where('status', 1);
        if ($paramIds) {
            $query->where('id', 'in', array_map('intval', $paramIds));
        }
        $ids = $query->column('id');
        if (!$ids) {
            $this->error('无可更新参数');
        }

        $count = PriceCache::batchSetUserPrice($userId, $categoryId, $ids, (string)$price);
        $this->success('批量更新成功', [
            'category_id' => $categoryId,
            'price'       => (string)$price,
            'updated'     => $count,
        ]);
    }
}
