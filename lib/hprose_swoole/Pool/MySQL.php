<?php
/**
 * 基于 swoole + 协程 实现的连接池
 * Created by PhpStorm.
 * User: luojinbo
 * Date: 2018-12-17
 * Time: 14:57
 */

namespace HproseSwoole\Pool;

class MySQL
{
    /**
     * @var MySQL
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
     * @throws MySQLException
     */
    public static function init(array $connsConfig)
    {
        if (!self::$init) {
            self::$init = new MySQL($connsConfig);
        }
        return self::$init;
    }

    /**
     * MySQL constructor.
     * @param $connsConfig
     */
    public function __construct(array $connsConfig)
    {
        $this->connsConfig = $connsConfig;
        foreach ($connsConfig as $name => $config) {
            $this->spareConns[$name] = [];
            $this->busyConns[$name] = [];
            $this->pendingFetchCount[$name] = 0;
            $this->resumeFetchCount[$name] = 0;
            if ($config['maxSpareConns'] <= 0 || $config['maxConns'] <= 0) {
                throw new MySQLException("Invalid maxSpareConns or maxConns in {$name}");
            }
        }
    }

    /**
     * 回收连接，该连接必须是从连接池中获取的连接
     * @param \Swoole\Coroutine\MySQL $conn
     * @throws MySQLException
     */
    public function recycle(\Swoole\Coroutine\MySQL $conn)
    {
        $id = spl_object_hash($conn);
        $connName = $this->connsNameMap[$id];

        if (isset($this->busyConns[$connName][$id])) {
            unset($this->busyConns[$connName][$id]);
        } else {
            throw new MySQLException('Unknow MySQL connection.');
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
                    \Swoole\Coroutine::resume('MySQLPool::' . $connName);
                }
                return;
            }
        }
        unset($this->connsNameMap[$id]);
    }

    /**
     * 从连接池中获取一条连接
     * @param $connName
     * @return bool|mixed|\Swoole\Coroutine\MySQL
     * @throws MySQLException
     */
    public function fetch($connName)
    {
        if (!isset($this->connsConfig[$connName])) {
            throw new MySQLException("Unvalid connName: {$connName}.");
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
            if (\Swoole\Coroutine::suspend('MySQLPool::' . $connName) == false) {
                $this->pendingFetchCount[$connName]--;
                throw new MySQLException('Reach max connections! Cann\'t pending fetch!');
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
        $conn = new \Swoole\Coroutine\MySQL();
        $id = spl_object_hash($conn);
        $this->connsNameMap[$id] = $connName;
        $this->busyConns[$connName][$id] = $conn;
        if ($conn->connect($this->connsConfig[$connName]['serverInfo']) == false) {
            unset($this->busyConns[$connName][$id]);
            unset($this->connsNameMap[$id]);
            throw new MySQLException('Cann\'t connect to MySQL server: '
                . json_encode($this->connsConfig[$connName]['serverInfo']));
        }
        return $conn;
    }
}

class MySQLException extends \Exception {}