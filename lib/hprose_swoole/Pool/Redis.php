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
    protected $spareConns = [];
    protected $busyConns = [];
    protected $connsConfig;
    protected $connsNameMap = [];
    protected $pendingFetchCount = [];
    protected $resumeFetchCount = [];

    /**
     * @param array $connsConfig
     * @return Redis
     * @throws RedisException
     */
    public static function init(array $connsConfig)
    {
        if (!self::$init) {
            self::$init = new Redis($connsConfig);
        }
        return self::$init;
    }

    public function __construct(array $connsConfig)
    {
        $this->connsConfig = $connsConfig;
        foreach ($connsConfig as $name => $config) {
            $this->spareConns[$name] = [];
            $this->busyConns[$name] = [];
            $this->pendingFetchCount[$name] = 0;
            $this->resumeFetchCount[$name] = 0;
            if ($config['maxSpareConns'] <= 0 || $config['maxConns'] <= 0) {
                throw new RedisException("Invalid maxSpareConns or maxConns in {$name}");
            }
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
        $connName = $this->connsNameMap[$id];

        if (isset($this->busyConns[$connName][$id])) {
            unset($this->busyConns[$connName][$id]);
        } else {
            throw new RedisException('Unknow Redis connection.');
        }
        $connsPool = &$this->spareConns[$connName];
        if ($conn->connected) {
            if (count($connsPool) >= $this->connsConfig[$connName]['maxSpareConns']) {
                $conn->close();
            } else {
                $connsPool[] = $conn;
                if ($this->pendingFetchCount[$connName] > 0) {
                    $this->resumeFetchCount[$connName]++;
                    $this->pendingFetchCount[$connName]--;
                    \Swoole\Coroutine::resume('RedisPool::' . $connName);
                }
                return;
            }
        }
        unset($this->connsNameMap[$id]);
    }

    /**
     * 从连接池中获取一条连接
     * @param $connName
     * @return bool|mixed|\Swoole\Coroutine\Redis
     * @throws MySQLException
     */
    public function fetch($connName)
    {
        if (!isset($this->connsConfig[$connName])) {
            throw new RedisException("Unvalid connName: {$connName}.");
        }
        $connsPool = &$this->spareConns[$connName];
        if (!empty($connsPool) && count($connsPool) > $this->resumeFetchCount[$connName]) {
            $conn = array_pop($connsPool);
            if ($conn->connected) {
                $this->busyConns[$connName][spl_object_hash($conn)] = $conn;
                return $conn;
            }
        }
        if (count($this->busyConns[$connName]) + count($connsPool) == $this->connsConfig[$connName]['maxConns']) {
            $this->pendingFetchCount[$connName]++;
            if (\Swoole\Coroutine::suspend('RedisPool::' . $connName) == false) {
                $this->pendingFetchCount[$connName]--;
                throw new RedisException('Reach max connections! Cann\'t pending fetch!');
            }
            $this->resumeFetchCount[$connName]--;
            if (!empty($connsPool)) {
                $conn = array_pop($connsPool);
                if ($conn->connected) {
                    $this->busyConns[$connName][spl_object_hash($conn)] = $conn;
                    return $conn;
                }
            } else {
                return false;   // should not happen
            }
        }
        $conn = new \Swoole\Coroutine\Redis();
        $id = spl_object_hash($conn);
        $this->connsNameMap[$id] = $connName;
        $this->busyConns[$connName][$id] = $conn;
        if ($conn->connect($this->connsConfig[$connName]['serverInfo']['host'],
                $this->connsConfig[$connName]['serverInfo']['port']) == false
            ||
            (
                isset($this->connsConfig[$connName]['serverInfo']['pwd'])
                &&
                $conn->auth($this->connsConfig[$connName]['serverInfo']['pwd']) == false
            )

        ) {
            unset($this->busyConns[$connName][$id]);
            unset($this->connsNameMap[$id]);
            throw new RedisException('Cann\'t connect to Redis server: '
                . json_encode($this->connsConfig[$connName]['serverInfo']));
        }

        if (isset($this->connsConfig[$connName]['serverInfo']['pwd'])
            && $conn->auth($this->connsConfig[$connName]['serverInfo']['pwd']) == false) {
            unset($this->busyConns[$connName][$id]);
            unset($this->connsNameMap[$id]);
            throw new RedisException('Cann\'t connect to Redis server: '
                . json_encode($this->connsConfig[$connName]['serverInfo']));
        }
        return $conn;
    }

}

class RedisException extends \Exception {}