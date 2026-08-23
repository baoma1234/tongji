<?php

namespace app\common\library\account;

use think\Config;

/**
 * 记账业务 Redis 单例（与默认 Cache 隔离，固定 DB）
 */
class RedisClient
{
    /** @var \Redis|null */
    protected static $handler = null;

    /** @var string */
    protected static $prefix = 'account:';

    /**
     * @return \Redis
     */
    public static function handler()
    {
        if (self::$handler instanceof \Redis) {
            return self::$handler;
        }
        if (!extension_loaded('redis')) {
            throw new \RuntimeException('redis extension required');
        }
        $cfg = Config::get('account.redis') ?: [];
        $redis = new \Redis();
        $redis->connect(
            isset($cfg['host']) ? $cfg['host'] : '127.0.0.1',
            isset($cfg['port']) ? (int)$cfg['port'] : 6379,
            isset($cfg['timeout']) ? (float)$cfg['timeout'] : 2.0
        );
        if (!empty($cfg['password'])) {
            $redis->auth($cfg['password']);
        }
        $redis->select(isset($cfg['select']) ? (int)$cfg['select'] : 1);
        self::$prefix = isset($cfg['prefix']) ? $cfg['prefix'] : 'account:';
        self::$handler = $redis;
        return self::$handler;
    }

    /**
     * @param string $key
     * @return string
     */
    public static function key($key)
    {
        return self::$prefix . ltrim($key, ':');
    }
}
