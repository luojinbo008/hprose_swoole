<?php
/**
 * Created by IntelliJ IDEA.
 * User: luojinbo
 * Date: 2018-12-21
 * Time: 11:48
 */

global $php;

$config = $php->config['db'];
if (empty($config)) {
    throw new Exception("require db config");
}

return HproseSwoole\Pool\MySQL::init($config);