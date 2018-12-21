<?php
/**
 * 基于 swoole + 协程 实现的连接池
 * Created by PhpStorm.
 * User: luojinbo
 * Date: 2018-12-12
 * Time: 11:10
 */

namespace HproseSwoole\Pool;

class Redis
{
    /**
     * @var Redis
     */
    protected static $init;
    protected $spareConn = [];
    protected $busyConn = [];
    protected $connConfig;
    protected $connName;
    protected $pendingFetchCount;
    protected $resumeFetchCount;

    /**
     * @param array $config
     * @param string $name
     * @return mixed
     * @throws RedisException
     */
    public static function init(array $config, string $name)
    {
        if (!isset(self::$init[$name])) {
            self::$init[$name] = new Redis($config, $name);
        }
        return self::$init[$name];
    }

    /**
     * Redis constructor.
     * @param array $config
     * @param string $name
     * @throws MySQLException
     */
    public function __construct(array $config, string $name)
    {
        $this->connName = $name;
        $this->connConfig = $config;
        $this->spareConn = [];
        $this->busyConn = [];
        $this->pendingFetchCount = 0;
        $this->resumeFetchCount = 0;
        if ($config['maxSpareConns'] <= 0 || $config['maxConns'] <= 0) {
            throw new RedisException("Invalid maxSpareConns or maxConns in {$name}");
        }
    }

    /**
     * 回收连接，该连接必须是从连接池中获取的连接
     * @param \Swoole\Coroutine\Redis $conn
     * @throws RedisException
     */
    public function recycle(\Swoole\Coroutine\Redis $conn)
    {
        $id = spl_object_hash($conn);

        if (isset($this->busyConn[$id])) {
            unset($this->busyConn[$id]);
        } else {
            throw new RedisException('Unknow Redis connection.');
        }
        $connPool = &$this->spareConn;
        if ($conn->connected) {
            if (count($connPool) >= $this->connConfig['maxSpareConns']) {
                $conn->close();
            } else {
                $connPool[] = $conn;
                if ($this->pendingFetchCount > 0) {
                    $this->resumeFetchCount++;
                    $this->pendingFetchCount--;
                    \Swoole\Coroutine::resume('RedisPool::' . $this->connName);
                }
                return;
            }
        }
    }

    /**
     * 从连接池中获取一条连接
     * @param $connName
     * @return bool|mixed|\Swoole\Coroutine\Redis
     * @throws RedisException
     */
    public function fetch()
    {
        $connPool = &$this->spareConn;
        if (!empty($connPool) && count($connPool) > $this->resumeFetchCount) {
            $conn = array_pop($connPool);
            if ($conn->connected) {
                $this->busyConn[spl_object_hash($conn)] = $conn;
                return $conn;
            }
        }
        if (count($this->busyConn) + count($connPool) == $this->connConfig['maxConns']) {
            $this->pendingFetchCount++;
            if (\Swoole\Coroutine::suspend('RedisPool::' . $this->connName) == false) {
                $this->pendingFetchCount--;
                throw new RedisException('Reach max connections! Cann\'t pending fetch!');
            }
            $this->resumeFetchCount--;
            if (!empty($connPool)) {
                $conn = array_pop($connPool);
                if ($conn->connected) {
                    $this->busyConn[spl_object_hash($conn)] = $conn;
                    return $conn;
                }
            } else {
                return false;   // should not happen
            }
        }
        $conn = new \Swoole\Coroutine\Redis();
        $id = spl_object_hash($conn);
        $this->busyConn[$id] = $conn;
        if ($conn->connect($this->connConfig['serverInfo']['host'],
                $this->connConfig['serverInfo']['port']) == false
            ||
            (
                isset($this->connConfig['serverInfo']['pwd'])
                &&
                $conn->auth($this->connConfig['serverInfo']['pwd']) == false
            )

        ) {
            unset($this->busyConn[$id]);
            throw new RedisException('Cann\'t connect to Redis server: '
                . json_encode($this->connConfig['serverInfo']));
        }
        return $conn;
    }

}

class RedisException extends \Exception {}