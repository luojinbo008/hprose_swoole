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
HproseSwoole\Pool\Redis::init($config);
return HproseSwoole\Pool\Redis::fetch("main");