<?php
/**
 * Created by PhpStorm.
 * User: luojinbo
 * Date: 2018-12-17
 * Time: 12:42
 */
global $php;

$configs = $php->config['redis'];
if (empty($configs)) {
    throw new Exception("require redis config");
}

if (empty($configs[$php->factory_key])) {
    throw new HproseSwoole\Exception\Factory("redis->{$php->factory_key} is not found.");
}

return HproseSwoole\Pool\Redis::init($configs[$php->factory_key], $php->factory_key);