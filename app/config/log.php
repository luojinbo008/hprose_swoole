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
        "type"  => "FileLog",
        "date"  => true,
        "dir"   => APPPATH,
        "file"  => "demo_log.txt",
        "leave" => \HproseSwoole\Log::INFO
    ]
];