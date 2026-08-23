<?php

namespace app\api\controller\account;

use app\common\controller\AccountApi;

/**
 * 记账登录
 */
class Auth extends AccountApi
{
    protected $noNeedLogin = ['login'];

    /**
     * 专属口令登录（无需注册）
     * POST access_code
     */
    public function login()
    {
        if (!$this->request->isPost()) {
            $this->error('请使用 POST');
        }
        $accessCode = $this->request->post('access_code', '');
        if ($accessCode === '') {
            $this->error('请输入专属口令');
        }
        if ($this->auth->login($accessCode)) {
            $this->success('登录成功', ['userinfo' => $this->auth->getUserinfo()]);
        }
        $this->error($this->auth->getError() ?: '登录失败');
    }

    /**
     * 退出
     */
    public function logout()
    {
        if (!$this->request->isPost()) {
            $this->error('请使用 POST');
        }
        $this->auth->logout();
        $this->success('已退出');
    }

    /**
     * 当前用户
     */
    public function profile()
    {
        $this->success('', ['userinfo' => $this->auth->getUserinfo()]);
    }
}
