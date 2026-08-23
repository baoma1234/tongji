<?php

use think\Env;

/**
 * 记账业务配置（高并发）
 * Redis 密码等敏感项从 .env [redis] 读取，禁止写入仓库
 */
return [
    'redis'           => [
        'host'       => Env::get('redis.host', '127.0.0.1'),
        'port'       => (int)Env::get('redis.port', 6379),
        'password'   => Env::get('redis.password', ''),
        'select'     => (int)Env::get('redis.select', 1),
        'timeout'    => 2,
        'prefix'     => 'account:',
    ],
    'token_expire'    => 2592000,
    'param_list_ttl'  => 86400,
    'user_price_ttl'  => 86400,
    'sync_batch_max'  => 100,
    'bill_queue_key'  => 'queue:bill',
    'idempotent_ttl'  => 86400,
    'login_fail_max'  => 10,
    'auto_register'   => true,
];
