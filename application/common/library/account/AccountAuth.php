<?php

namespace app\common\library\account;

use app\common\model\account\User as AccUser;
use fast\Random;
use think\Config;
use think\Request;

/**
 * 记账租户鉴权（与 FastAdmin fa_user 隔离）
 * Token 存 Redis: account:token:{token} -> user_id JSON
 */
class AccountAuth
{
    /** @var AccUser|null */
    protected $user = null;

    /** @var string */
    protected $token = '';

    /** @var string */
    protected $error = '';

    /** @var self|null */
    protected static $instance = null;

    public static function instance()
    {
        if (!self::$instance) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    /**
     * @param string $token
     * @return bool
     */
    public function init($token)
    {
        $token = trim((string)$token);
        if ($token === '') {
            $this->error = 'Token required';
            return false;
        }
        $redis = RedisClient::handler();
        $raw = $redis->get(RedisClient::key('token:' . $token));
        if (!$raw) {
            $this->error = 'Token expired';
            return false;
        }
        $payload = json_decode($raw, true);
        $userId = isset($payload['uid']) ? (int)$payload['uid'] : 0;
        if ($userId <= 0) {
            $this->error = 'Invalid token';
            return false;
        }
        $user = AccUser::where('id', $userId)->where('status', 1)->find();
        if (!$user) {
            $redis->del(RedisClient::key('token:' . $token));
            $this->error = 'User disabled';
            return false;
        }
        $this->token = $token;
        $this->user = $user;
        return true;
    }

    /**
     * 专属口令登录（可自动建户）
     * @param string $accessCode
     * @return bool
     */
    public function login($accessCode)
    {
        $accessCode = trim((string)$accessCode);
        if ($accessCode === '' || strlen($accessCode) > 64) {
            $this->error = '口令无效';
            return false;
        }

        $user = AccUser::where('access_code', $accessCode)->find();
        $failMax = (int)(Config::get('account.login_fail_max') ?: 10);

        if ($user) {
            if ((int)$user['status'] !== 1) {
                $this->error = '账号已禁用';
                return false;
            }
            if ((int)$user['loginfailure'] >= $failMax) {
                $this->error = '登录失败次数过多，请联系管理员';
                return false;
            }
            // 若设置了 password 哈希则校验；否则 access_code 命中即通过
            if ($user['password'] !== '' && $user['salt'] !== '') {
                $hash = md5(md5($accessCode) . $user['salt']);
                if ($hash !== $user['password']) {
                    AccUser::where('id', $user['id'])->setInc('loginfailure');
                    $this->error = '口令错误';
                    return false;
                }
            }
        } else {
            if (!Config::get('account.auto_register')) {
                $this->error = '口令不存在';
                return false;
            }
            $now = time();
            $salt = Random::alnum(6);
            $user = AccUser::create([
                'access_code'  => $accessCode,
                'nickname'     => '用户' . substr(md5($accessCode), 0, 6),
                'salt'         => $salt,
                'password'     => md5(md5($accessCode) . $salt),
                'status'       => 1,
                'loginfailure' => 0,
                'createtime'   => $now,
                'updatetime'   => $now,
            ]);
        }

        $request = Request::instance();
        $ip = '0.0.0.0';
        try {
            $tmpIp = $request->ip();
            if (is_string($tmpIp) && $tmpIp !== '') {
                $ip = $tmpIp;
            }
        } catch (\Throwable $e) {
            $ip = '127.0.0.1';
        }
        AccUser::where('id', $user['id'])->update([
            'logintime'    => time(),
            'loginip'      => $ip,
            'loginfailure' => 0,
            'updatetime'   => time(),
        ]);

        $this->user = AccUser::get($user['id']);
        $this->token = $this->buildToken($this->user['id']);
        return true;
    }

    /**
     * @param int $userId
     * @return string
     */
    protected function buildToken($userId)
    {
        $token = md5(uniqid((string)$userId, true) . Random::alnum(16));
        $expire = (int)(Config::get('account.token_expire') ?: 2592000);
        $redis = RedisClient::handler();
        $payload = json_encode(['uid' => (int)$userId, 't' => time()], JSON_UNESCAPED_UNICODE);
        $redis->setex(RedisClient::key('token:' . $token), $expire, $payload);
        // 单端会话：记录最新 token，便于踢旧会话（可选）
        $redis->setex(RedisClient::key('user:sess:' . (int)$userId), $expire, $token);
        return $token;
    }

    public function logout()
    {
        if ($this->token) {
            RedisClient::handler()->del(RedisClient::key('token:' . $this->token));
        }
        $this->token = '';
        $this->user = null;
        return true;
    }

    public function isLogin()
    {
        return $this->user && $this->user['id'] > 0;
    }

    public function id()
    {
        return $this->user ? (int)$this->user['id'] : 0;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function getError()
    {
        return $this->error;
    }

    public function getUserinfo()
    {
        if (!$this->user) {
            return [];
        }
        return [
            'id'          => (int)$this->user['id'],
            'nickname'    => $this->user['nickname'],
            'access_code' => $this->user['access_code'],
            'token'       => $this->token,
            'logintime'   => $this->user['logintime'],
        ];
    }
}
