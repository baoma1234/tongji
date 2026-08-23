<?php

namespace app\common\controller;

use app\common\library\account\AccountAuth;
use think\exception\HttpResponseException;
use think\Lang;
use think\Loader;
use think\Request;
use think\Response;

/**
 * 记账 API 基类（独立租户鉴权，不走 fa_user）
 */
class AccountApi
{
    protected $request;
    protected $failException = false;
    protected $batchValidate = false;
    protected $beforeActionList = [];
    /** @var array */
    protected $noNeedLogin = [];
    /** @var AccountAuth */
    protected $auth = null;
    protected $responseType = 'json';

    public function __construct(Request $request = null)
    {
        $this->request = is_null($request) ? Request::instance() : $request;
        $this->_initialize();
        if ($this->beforeActionList) {
            foreach ($this->beforeActionList as $method => $options) {
                is_numeric($method) ?
                    $this->beforeAction($options) :
                    $this->beforeAction($method, $options);
            }
        }
    }

    protected function _initialize()
    {
        check_cors_request();
        check_ip_allowed();
        $this->request->filter('trim,strip_tags,htmlspecialchars');

        $this->auth = AccountAuth::instance();
        $token = $this->request->server('HTTP_TOKEN', $this->request->request('token', \think\Cookie::get('acc_token')));

        $action = strtolower($this->request->action());
        $needLogin = true;
        foreach ($this->noNeedLogin as $name) {
            if ($name === '*' || strtolower($name) === $action) {
                $needLogin = false;
                break;
            }
        }

        if ($needLogin) {
            if (!$this->auth->init($token)) {
                $this->error($this->auth->getError() ?: '请先登录', null, 401);
            }
        } elseif ($token) {
            $this->auth->init($token);
        }

        $controllername = Loader::parseName($this->request->controller());
        $this->loadlang($controllername);
    }

    protected function loadlang($name)
    {
        $name = Loader::parseName($name);
        $lang = $this->request->langset();
        $lang = preg_match("/^([a-zA-Z\-_]{2,10})\$/i", $lang) ? $lang : 'zh-cn';
        $file = APP_PATH . $this->request->module() . '/lang/' . $lang . '/' . str_replace('.', '/', $name) . '.php';
        if (is_file($file)) {
            Lang::load($file);
        }
    }

    protected function beforeAction($method, $options = [])
    {
        if (isset($options['only'])) {
            if (is_string($options['only'])) {
                $options['only'] = explode(',', $options['only']);
            }
            if (!in_array($this->request->action(), $options['only'])) {
                return;
            }
        } elseif (isset($options['except'])) {
            if (is_string($options['except'])) {
                $options['except'] = explode(',', $options['except']);
            }
            if (in_array($this->request->action(), $options['except'])) {
                return;
            }
        }
        call_user_func([$this, $method]);
    }

    protected function success($msg = '', $data = null, $code = 1, $type = null, array $header = [])
    {
        $this->result($msg, $data, $code, $type, $header);
    }

    protected function error($msg = '', $data = null, $code = 0, $type = null, array $header = [])
    {
        $this->result($msg, $data, $code, $type, $header);
    }

    protected function result($msg, $data = null, $code = 0, $type = null, array $header = [])
    {
        $result = [
            'code' => $code,
            'msg'  => $msg,
            'time' => Request::instance()->server('REQUEST_TIME'),
            'data' => $data,
        ];
        $type = $type ?: $this->responseType;
        if (isset($header['statuscode'])) {
            $code = $header['statuscode'];
            unset($header['statuscode']);
        } else {
            $code = $result['code'] >= 1 ? 200 : 200;
        }
        $response = Response::create($result, $type, $code)->header($header);
        throw new HttpResponseException($response);
    }

}
