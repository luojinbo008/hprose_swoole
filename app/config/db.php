<?php
/**
 * Created by PhpStorm.
 * User: luojinbo
 * Date: 2018-12-17
 * Time: 12:37
 */
return [
    'master'  => [
        'serverInfo'    => [
            "host"      => getenv("MYSQL_HOST"),
            "port"      => getenv("MYSQL_PORT"),
            "user"      => getenv("MYSQL_USER"),
            "password"  => getenv("MYSQL_PASSWORD"),
            "database"  => getenv("MYSQL_DATABASE"),
            "charset"   => getenv("MYSQL_CHARSET"),
        ],
        'maxSpareConns' => 1,   // 最大空闲连接数
        'maxConns'      => 2,   // 最大连接数
    ],
    'conn1'  => [
        'serverInfo'    => [
            "host"      => getenv("MYSQL_HOST"),
            "port"      => getenv("MYSQL_PORT"),
            "user"      => getenv("MYSQL_USER"),
            "password"  => getenv("MYSQL_PASSWORD"),
            "database"  => getenv("MYSQL_DATABASE"),
            "charset"   => getenv("MYSQL_CHARSET"),
        ],
        'maxSpareConns' => 1,   // 最大空闲连接数
        'maxConns'      => 2,   // 最大连接数
    ]
];