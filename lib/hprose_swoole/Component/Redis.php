<?php
/**
 * Created by PhpStorm.
 * User: luojinbo
 * Date: 2018-12-17
 * Time: 14:19
 */
namespace Swoole\Component;

class Redis
{
    /**
     * @var $_redis \Swoole\Coroutine\Redis
     */
    protected $_redis;
    protected $conn = "main";


    public function __construct($conn = "main")
    {
        $this->_redis = \HproseSwoole\Pool\Redis::fetch($conn);
    }

    /**
     * @param $method
     * @param array $args
     * @return bool|mixed
     * @throws \RedisException
     */
    public function __call($method, $args = [])
    {
        $reConnect = false;
        while (1) {
            try {
                $result = call_user_func_array([$this->_redis, $method], $args);
            } catch (\RedisException $e) {

                // 失败 重连
                if ($reConnect) {
                    throw $e;
                }
                if ($this->_redis->isConnected()) {
                    $this->_redis->close();
                }
                $this->_redis = \HproseSwoole\Pool\Redis::fetch($this->conn);
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
        \HproseSwoole\Pool\Redis::recycle($this->_redis);
    }
}