<?php
/**
 * 需要安装 mongoDb 扩展 （swoole 协成版 具体看swoole 协成 扩展安装）
 * Created by IntelliJ IDEA.
 * User: luojinbo
 * Date: 2018-12-26
 * Time: 15:20
 */

namespace HproseSwoole\Pool;


class MongoDB
{
    /**
     * @var MongoDB
     */
    protected static $init;
    protected $spareConn = [];
    protected $busyConn = [];
    protected $connConfig;
    protected $connName;
    protected $pendingFetchCount;
    protected $resumeFetchCount;

    public static function init(array $config, string $name)
    {
        if (!isset(self::$init[$name])) {
            self::$init[$name] = new self($config, $name);
        }
        return self::$init[$name];
    }

    /**
     * MongoDB constructor.
     * @param array $config
     * @param string $name
     * @throws MongoDBException
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
            throw new MongoDBException("Invalid maxSpareConns or maxConns in {$name}");
        }
    }

    /**
     * 回收连接，该连接必须是从连接池中获取的连接
     * @param \MongoDB\Database $conn
     * @throws MongoDBException
     */
    public function recycle(\MongoDB\Database $conn)
    {
         var_dump(123);
        $id = spl_object_hash($conn);

        if (isset($this->busyConn[$id])) {
            unset($this->busyConn[$id]);
        } else {
            throw new MongoDBException('Unknow MongoDB connection.');
        }
        $connPool = &$this->spareConn;
        try {
            $conn->command(['ping' => 1]);
            if (count($connPool) >= $this->connConfig['maxSpareConns']) {
                unset($conn);
            } else {
                $connPool[] = $conn;
                if ($this->pendingFetchCount > 0) {
                    $this->resumeFetchCount++;
                    $this->pendingFetchCount--;
                    \Swoole\Coroutine::resume('MongoDBPool::' . $this->connName);
                }
                return;
            }

        } catch (\MongoDB\Driver\Exception\ConnectionTimeoutException $e) {
            unset($conn);
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
            if (\Swoole\Coroutine::suspend('MongoDBPool::' . $this->connName) == false) {
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
        $server = sprintf("mongodb://%s:%s", $this->connConfig['serverInfo']['host'],
            $this->connConfig['serverInfo']['port'] ?? '27017');
        $client = new \MongoDB\Client(
            $server
        );
        $database = $this->connConfig['serverInfo']['database'];

        $conn = $client->{$database};
        $id = spl_object_hash($conn);
        $this->busyConn[$id] = $conn;
        try {
            $conn->command(['ping' => 1]);
        } catch (\MongoDB\Driver\Exception\ConnectionTimeoutException $e) {
            unset($this->busyConn[$id]);
            throw new MongoDBException('Cann\'t connect to MongoDB server: '
                . json_encode($this->connConfig['serverInfo']));
        }
        return $conn;
    }

}

class MongoDBException extends \Exception {}