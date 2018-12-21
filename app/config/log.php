<?php
/**
 * Created by IntelliJ IDEA.
 * User: luojinbo
 * Date: 2018-12-18
 * Time: 17:46
 */

return [
    "master" => [
        "enable_cache" => false,
        "type"  => getenv('APP_LOG_TYPE'),
        "dir"   => getenv('APP_LOG_PATH'),
        "date"  => true,
        "leave" => \HproseSwoole\Log::INFO
    ]
];