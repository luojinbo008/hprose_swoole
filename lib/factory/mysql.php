<?php
/**
 * Created by PhpStorm.
 * User: luojinbo
 * Date: 2018-12-17
 * Time: 15:45
 */
global $php;
$config = $php->config['db'];
if (empty($config)) {
    throw new Exception("require db config");
}
HproseSwoole\Pool\MySQL::init($config);

return HproseSwoole\Pool\MySQL::fetch($php->factory_key);