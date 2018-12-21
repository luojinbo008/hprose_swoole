<?php
/**
 * Created by IntelliJ IDEA.
 * User: luojinbo
 * Date: 2018-12-18
 * Time: 17:41
 */

global $php;
$config = $php->config['log'];
if (empty($config[$php->factory_key])) {
    throw new \HproseSwoole\Exception\Factory("log->{$php->factory_key} is not found.");
}
$conf = $config[$php->factory_key];
if (empty($conf['type'])) {
    $conf['type'] = 'EchoLog';
}
$class = '\\HproseSwoole\\Log\\' . $conf['type'];
$log = new $class($conf);
if (!empty($conf['level'])) {
    $log->setLevel($conf['level']);
}
return $log;