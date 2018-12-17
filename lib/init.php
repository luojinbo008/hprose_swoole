<?php
/**
 * Created by PhpStorm.
 * User: luojinbo
 * Date: 2018/11/28
 * Time: 10:20
 */
if (PHP_OS == 'WINNT') {
    die("windows system not access this server！");
}

define('BASEPATH', realpath(__DIR__ . '/../') . '/');
define("LIBPATH", __DIR__);

define("NL", "\n");
define("BL", "<br />" . NL);

require_once "../vendor/autoload.php";
(new Dotenv\Dotenv(BASEPATH))->load();

global $php;

$php = HproseSwoole\Swoole::getInstance();