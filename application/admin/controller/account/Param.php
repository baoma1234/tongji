<?php

namespace app\admin\controller\account;

use app\common\controller\Backend;

/**
 * 记账参数（改价/增删自动失效 Redis 参数缓存）
 *
 * @icon fa fa-sliders
 */
class Param extends Backend
{
    protected $model = null;
    protected $searchFields = 'id,name';
    protected $relationSearch = true;
    protected $noNeedRight = ['selectpage'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\account\Param;
        $this->view->assign('statusList', $this->model->getStatusList());

        $categoryList = \app\admin\model\account\Category::where('status', 1)
            ->order('weigh desc,id asc')
            ->column('name', 'id');
        $this->view->assign('categoryList', $categoryList);
        $this->assignconfig('categoryList', $categoryList);
    }
}
