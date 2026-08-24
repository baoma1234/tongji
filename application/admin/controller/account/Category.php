<?php

namespace app\admin\controller\account;

use app\common\controller\Backend;

/**
 * 记账类目
 *
 * @icon fa fa-folder-open
 */
class Category extends Backend
{
    protected $model = null;
    protected $searchFields = 'id,name,code';
    protected $noNeedRight = ['selectpage'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\account\Category;
        $this->view->assign('statusList', $this->model->getStatusList());
    }
}
