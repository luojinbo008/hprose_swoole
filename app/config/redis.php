<?php
/**
 * Created by PhpStorm.
 * User: luojinbo
 * Date: 2018-12-17
 * Time: 12:40
 */

return [
    'main'  => [
        'serverInfo'    => [
            "host"      => getenv("REDIS_HOST"),
            "port"      => getenv("REDIS_PORT"),
        ],
        'maxSpareConns' => 1,   // 最大空闲连接数
        'maxConns'      => 2,   // 最大连接数
    ]
];