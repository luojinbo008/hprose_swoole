<?php
/**
 * Created by IntelliJ IDEA.
 * User: luojinbo
 * Date: 2018-12-27
 * Time: 16:54
 */
return [
    "master" => [
        'serverInfo'    => [
            "host" => "192.168.56.101",
            "port"  => 27017,
            "database" => 'test'
        ],
        'maxSpareConns' => 1,   // 最大空闲连接数
        'maxConns'      => 2,   // 最大连接数
    ]
];