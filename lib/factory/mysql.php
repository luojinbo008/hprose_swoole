<?php
/**
 * Created by PhpStorm.
 * User: luojinbo
 * Date: 2018-12-17
 * Time: 15:45
 */
global $php;

$configs = $php->config['db'];
if (empty($configs)) {
    throw new Exception("require db config");
}

if (empty($configs[$php->factory_key])) {
    throw new HproseSwoole\Exception\Factory("db->{$php->factory_key} is not found.");
}

return HproseSwoole\Pool\MySQL::init($configs[$php->factory_key], $php->factory_key);