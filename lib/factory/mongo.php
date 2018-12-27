<?php
/**
 * Created by IntelliJ IDEA.
 * User: luojinbo
 * Date: 2018-12-27
 * Time: 16:53
 */
global $php;
$configs = $php->config['mongo'];

if (empty($configs)) {
    throw new Exception("require mongo config");
}

if (empty($configs[$php->factory_key])) {
    throw new HproseSwoole\Exception\Factory("mongo->{$php->factory_key} is not found.");
}

return HproseSwoole\Pool\MongoDB::init($configs[$php->factory_key], $php->factory_key);