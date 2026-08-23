<?php

use think\Env;

return [
    'connector'  => 'Redis',
    'expire'     => 0,
    'default'    => 'default',
    'host'       => Env::get('redis.host', '127.0.0.1'),
    'port'       => (int)Env::get('redis.port', 6379),
    'password'   => Env::get('redis.password', ''),
    'select'     => (int)Env::get('redis.select', 0),
    'timeout'    => 0,
    'persistent' => false,
];
