<?php
/**
 * Created by PhpStorm.
 * User: luojinbo
 * Date: 2018-12-17
 * Time: 15:37
 */

namespace Swoole\Component;


use HproseSwoole\Pool\MySQLException;

class MySQL
{
    /**
     * @var $_redis \Swoole\Coroutine\Mysql
     */
    protected $_mysql;
    protected $conn = "main";

    public function __construct($conn = "main")
    {
        $this->_mysql = \HproseSwoole\Pool\Mysql::fetch($conn);
    }

    /**
     * @param $method
     * @param array $args
     * @return bool|mixed
     * @throws MySQLExceptions
     */
    public function __call($method, $args = [])
    {
        $reConnect = false;
        while (1) {
            try {
                $result = call_user_func_array([$this->_mysql, $method], $args);
            } catch (MySQLException $e) {

                // 失败 重连
                if ($reConnect) {
                    throw $e;
                }
                if ($this->_mysql->query("select 1")) {
                    $this->_mysql->close();
                }
                $this->_mysql = \HproseSwoole\Pool\MySQL::fetch($this->conn);
                $reConnect = true;
                continue;
            }
            return $result;
        }

        // 不可能到这里
        return false;
    }

    /**
     * 回收
     * @throws \HproseSwoole\Pool\RedisException
     */
    public function __destruct()
    {
        // TODO: Implement __destruct() method.
        \HproseSwoole\Pool\Mysql::recycle($this->_mysql);
    }
}