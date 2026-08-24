<?php

namespace app\admin\controller\account;

use app\common\controller\Backend;
use app\common\library\account\BillService;
use think\Db;

/**
 * 账单流水（按月分表只读查询）
 *
 * @icon fa fa-list-alt
 */
class Bill extends Backend
{
    protected $model = null;
    protected $searchFields = 'id,user_id,param_name,batch_id';
    /** 只读 */
    protected $noNeedRight = ['index'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\account\Bill;
    }

    public function index()
    {
        $billYm = preg_replace('/\D/', '', $this->request->request('bill_ym', date('Ym')));
        if (strlen($billYm) !== 6) {
            $billYm = date('Ym');
        }

        $this->assignconfig('billYm', $billYm);

        if (false === $this->request->isAjax()) {
            return $this->view->fetch();
        }

        BillService::ensureMonthTable((int)$billYm);
        $prefix = config('database.prefix');
        $this->model->setTable($prefix . BillService::table((int)$billYm));

        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->request('keyField')) {
            return $this->selectpage();
        }

        [$where, $sort, $order, $offset, $limit] = $this->buildparams();
        $list = $this->model
            ->where($where)
            ->order($sort ?: 'id', $order ?: 'desc')
            ->paginate($limit);

        return json(['total' => $list->total(), 'rows' => $list->items()]);
    }
}
