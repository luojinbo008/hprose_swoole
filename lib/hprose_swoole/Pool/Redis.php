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
    protected static $init = false;
    protected static $spareConns = [];
    protected static $busyConns = [];
    protected static $connsConfig;
    protected static $connsNameMap = [];
    protected static $pendingFetchCount = [];
    protected static $resumeFetchCount = [];

    /**
     * @param array $connsConfig
     * @throws RedisException
     */
    public static function init(array $connsConfig)
    {
        if (self::$init) {
            return;
        }
        self::$connsConfig = $connsConfig;
        foreach ($connsConfig as $name => $config) {
            self::$spareConns[$name] = [];
            self::$busyConns[$name] = [];
            self::$pendingFetchCount[$name] = 0;
            self::$resumeFetchCount[$name] = 0;
            if ($config['maxSpareConns'] <= 0 || $config['maxConns'] <= 0) {
                throw new RedisException("Invalid maxSpareConns or maxConns in {$name}");
            }
        }
        self::$init = true;
    }

    /**
     * 回收连接，该连接必须是从连接池中获取的连接
     * @param \Swoole\Coroutine\Redis $conn
     * @throws RedisException
     */
    public static function recycle(\Swoole\Coroutine\Redis $conn)
    {
        if (!self::$init) {
            throw new RedisException('Should call RedisPool::init.');
        }
        $id = spl_object_hash($conn);
        $connName = self::$connsNameMap[$id];

        if (isset(self::$busyConns[$connName][$id])) {
            unset(self::$busyConns[$connName][$id]);
        } else {
            throw new RedisException('Unknow Redis connection.');
        }
        $connsPool = &self::$spareConns[$connName];
        if ($conn->connected) {
            if (count($connsPool) >= self::$connsConfig[$connName]['maxSpareConns']) {
                $conn->close();
            } else {
                $connsPool[] = $conn;
                if (self::$pendingFetchCount[$connName] > 0) {
                    self::$resumeFetchCount[$connName]++;
                    self::$pendingFetchCount[$connName]--;
                    \Swoole\Coroutine::resume('RedisPool::' . $connName);
                }
                return;
            }
        }
        unset(self::$connsNameMap[$id]);
    }

    /**
     * 从连接池中获取一条连接
     * @param $connName
     * @return bool|mixed|\Swoole\Coroutine\Redis
     * @throws MySQLException
     */
    public static function fetch($connName)
    {
        if (!self::$init) {
            throw new RedisException('Should call RedisPool::init!');
        }
        if (!isset(self::$connsConfig[$connName])) {
            throw new RedisException("Unvalid connName: {$connName}.");
        }
        $connsPool = &self::$spareConns[$connName];
        if (!empty($connsPool) && count($connsPool) > self::$resumeFetchCount[$connName]) {
            $conn = array_pop($connsPool);
            if ($conn->connected) {
                self::$busyConns[$connName][spl_object_hash($conn)] = $conn;
                return $conn;
            }
        }
        if (count(self::$busyConns[$connName]) + count($connsPool) == self::$connsConfig[$connName]['maxConns']) {
            self::$pendingFetchCount[$connName]++;
            if (\Swoole\Coroutine::suspend('RedisPool::' . $connName) == false) {
                self::$pendingFetchCount[$connName]--;
                throw new RedisException('Reach max connections! Cann\'t pending fetch!');
            }
            self::$resumeFetchCount[$connName]--;
            if (!empty($connsPool)) {
                $conn = array_pop($connsPool);
                if ($conn->connected) {
                    self::$busyConns[$connName][spl_object_hash($conn)] = $conn;
                    return $conn;
                }
            } else {
                return false;   // should not happen
            }
        }
        $conn = new \Swoole\Coroutine\Redis();
        $id = spl_object_hash($conn);
        self::$connsNameMap[$id] = $connName;
        self::$busyConns[$connName][$id] = $conn;
        if ($conn->connect(self::$connsConfig[$connName]['serverInfo']['host'],
                self::$connsConfig[$connName]['serverInfo']['port']) == false
            ||
            (
                isset(self::$connsConfig[$connName]['serverInfo']['pwd'])
                &&
                $conn->auth(self::$connsConfig[$connName]['serverInfo']['pwd']) == false
            )

        ) {
            unset(self::$busyConns[$connName][$id]);
            unset(self::$connsNameMap[$id]);
            throw new RedisException('Cann\'t connect to Redis server: '
                . json_encode(self::$connsConfig[$connName]['serverInfo']));
        }

        if (isset(self::$connsConfig[$connName]['serverInfo']['pwd'])
            && $conn->auth(self::$connsConfig[$connName]['serverInfo']['pwd']) == false) {
            unset(self::$busyConns[$connName][$id]);
            unset(self::$connsNameMap[$id]);
            throw new RedisException('Cann\'t connect to Redis server: '
                . json_encode(self::$connsConfig[$connName]['serverInfo']));
        }
        return $conn;
    }

}

class RedisException extends \Exception {}