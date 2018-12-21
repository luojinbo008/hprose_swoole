<?php
/**
 * Created by PhpStorm.
 * User: luojinbo
 * Date: 2018-12-17
 * Time: 12:42
 */
global $php;
$config = $php->config['redis'];
if (empty($config)) {
    throw new Exception("require redis config");
}
return HproseSwoole\Pool\Redis::init($config);