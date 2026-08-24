<?php

namespace app\admin\controller\account;

use app\common\controller\Backend;
use fast\Random;
use think\Db;
use think\Exception;

/**
 * 记账租户用户（专属口令）
 *
 * @icon fa fa-key
 */
class User extends Backend
{
    protected $model = null;
    protected $searchFields = 'id,access_code,nickname';
    protected $noNeedRight = ['selectpage'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\account\User;
        $this->view->assign('statusList', $this->model->getStatusList());
    }

    /**
     * 添加：写入口令哈希
     */
    public function add()
    {
        if (false === $this->request->isPost()) {
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (empty($params['access_code'])) {
            $this->error('请填写专属口令');
        }
        $exists = $this->model->where('access_code', $params['access_code'])->find();
        if ($exists) {
            $this->error('口令已存在');
        }
        $salt = Random::alnum(6);
        $code = trim($params['access_code']);
        $params['salt'] = $salt;
        $params['password'] = md5(md5($code) . $salt);
        if (!isset($params['nickname']) || $params['nickname'] === '') {
            $params['nickname'] = '用户' . substr(md5($code), 0, 6);
        }
        Db::startTrans();
        try {
            $this->model->allowField(true)->save($params);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success();
    }

    /**
     * 编辑：可选重置口令
     */
    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        if (false === $this->request->isPost()) {
            $this->view->assign('row', $row);
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (!empty($params['access_code']) && $params['access_code'] !== $row['access_code']) {
            $dup = $this->model->where('access_code', $params['access_code'])->where('id', '<>', $row['id'])->find();
            if ($dup) {
                $this->error('口令已存在');
            }
            $salt = Random::alnum(6);
            $params['salt'] = $salt;
            $params['password'] = md5(md5(trim($params['access_code'])) . $salt);
        }
        unset($params['password_display']);
        Db::startTrans();
        try {
            $row->allowField(true)->save($params);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success();
    }
}
