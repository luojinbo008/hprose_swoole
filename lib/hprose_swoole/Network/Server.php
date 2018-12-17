<?php

/**
 * Created by PhpStorm.
 * User: luojinbo
 * Date: 2018/10/8
 * Time: 14:22
 */
namespace HproseSwoole\Network;

class Server
{
    protected static $options = [];

    protected static $beforeStopCallback;
    protected static $beforeReloadCallback;

    public static $optionKit;
    public static $pidFile;

    public $host = '0.0.0.0';
    public $port;
    public $runtimeSetting;

    public static $defaultOptions = [
        'd|daemon'  => '启用守护进程模式',
        'h|host?'   => '指定监听地址',
        'p|port?'   => '指定监听端口',
        'help'      => '显示帮助界面',
        'b|base'    => '使用BASE模式启动',
        'w|worker?' => '设置Worker进程的数量',
        'r|thread?' => '设置Reactor线程的数量',
        't|tasker?' => '设置Task进程的数量',
    ];

    /**
     * @var \Hprose\Swoole\Server
     */
    protected $hSvr;

    protected $pid_file;

    public static $server;

    protected $processName;

    /**
     * 设置PID文件
     * @param $pidFile
     */
    public static function setPidFile($pidFile)
    {
        self::$pidFile = $pidFile;
    }

    /**
     * 杀死所有进程
     * @param $name
     * @param int $signo
     * @return string
     */
    public static function killProcessByName($name, $signo = 9)
    {
        $cmd = 'ps -eaf |grep "' . $name . '" | grep -v "grep"| awk "{print $2}"|xargs kill -' . $signo;
        return exec($cmd);
    }

    /**
     *
     * $opt->add( 'f|foo:' , 'option requires a value.' );
     * $opt->add( 'b|bar+' , 'option with multiple value.' );
     * $opt->add( 'z|zoo?' , 'option with optional value.' );
     * $opt->add( 'v|verbose' , 'verbose message.' );
     * $opt->add( 'd|debug'   , 'debug message.' );
     * $opt->add( 'long'   , 'long option name only.' );
     * $opt->add( 's'   , 'short option name only.' );
     *
     * @param $specString
     * @param $description
     * @throws ServerOptionException
     */
    public static function addOption($specString, $description)
    {
        if (!self::$optionKit) {
            self::$optionKit = new \GetOptionKit\GetOptionKit;
        }
        foreach (self::$defaultOptions as $k => $v) {
            if ($k[0] == $specString[0]) {
                throw new ServerOptionException("不能添加系统保留的选项名称");
            }
        }
        self::$optionKit->add($specString, $description);
    }

    /**
     * @param callable $function
     */
    public static function beforeStop(callable $function)
    {
        self::$beforeStopCallback = $function;
    }

    /**
     * @param callable $function
     */
    public static function beforeReload(callable $function)
    {
        self::$beforeReloadCallback = $function;
    }

    /**
     * 显示命令行指令
     */
    public static function start($startFunction)
    {
        if (empty(self::$pidFile)) {
            throw new \Exception("require pidFile.");
        }
        $pid_file = self::$pidFile;
        if (is_file($pid_file)) {
            $server_pid = file_get_contents($pid_file);
        } else {
            $server_pid = 0;
        }

        if (!self::$optionKit) {
            self::$optionKit = new \GetOptionKit\GetOptionKit;
        }

        $kit = self::$optionKit;
        foreach(self::$defaultOptions as $k => $v) {
            $kit->add($k, $v);
        }

        global $argv;
        $opt = $kit->parse($argv);
        if (empty($argv[1]) or isset($opt['help'])) {
            goto usage;
        } elseif ($argv[1] == 'reload') {
            if (empty($server_pid)) {
                exit("Server is not running");
            }
            if (self::$beforeReloadCallback) {
                call_user_func(self::$beforeReloadCallback, $opt);
            }

            \HproseSwoole\Swoole::$php->os->kill($server_pid, SIGUSR1);
            exit;
        } elseif ($argv[1] == 'stop') {
            if (empty($server_pid)) {
                exit("Server is not running\n");
            }
            if (self::$beforeStopCallback) {
                call_user_func(self::$beforeStopCallback, $opt);
            }
            \HproseSwoole\Swoole::$php->os->kill($server_pid, SIGTERM);
            exit;
        } elseif ($argv[1] == 'start') {

            // 已存在ServerPID，并且进程存在
            if (!empty($server_pid) and \HproseSwoole\Swoole::$php->os->kill($server_pid, 0)) {
                exit("Server is already running.\n");
            }
        } else {
            usage:
            echo "================================================================================\n";
            echo "Usage: php {$argv[0]} start|stop|reload\n";
            echo "================================================================================\n";
            $kit->specs->printOptions();
            exit("\n");
        }
        self::$options = $opt;
        $startFunction($opt);
    }

    /**
     * @param array $setting
     * @throws \Exception
     */
    public function run($setting = [])
    {
        $this->runtimeSetting = array_merge($this->runtimeSetting, $setting);
        if (self::$pidFile) {
            $this->runtimeSetting['pid_file'] = self::$pidFile;
        }
        if (!empty(self::$options['daemon'])) {
            $this->runtimeSetting['daemonize'] = true;
        }
        if (!empty(self::$options['worker'])) {
            $this->runtimeSetting['worker_num'] = intval(self::$options['worker']);
        }
        if (!empty(self::$options['thread'])) {
            $this->runtimeSetting['reator_num'] = intval(self::$options['thread']);
        }
        if (!empty(self::$options['tasker'])) {
            $this->runtimeSetting['task_worker_num'] = intval(self::$options['tasker']);
        }

        $this->hSvr->set($this->runtimeSetting);
        $this->hSvr->on('WorkerStart', [$this, 'onWorkerStart']);
        $this->hSvr->start();
    }



    /**
     * @param $serv
     * @param $worker_id
     */
    public function onWorkerStart($serv, $worker_id)
    {
        /**
         * 清理Opcache缓存
         */
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        /**
         * 清理APC缓存
         */
        if (function_exists('apc_clear_cache')) {
            apc_clear_cache();
        }

        if ($worker_id >= $serv->setting['worker_num']) {
            \HproseSwoole\Console::setProcessName($this->getProcessName() . ': task');
        } else {
            \HproseSwoole\Console::setProcessName($this->getProcessName() . ': worker');
        }

    }

    /**
     * @param $func
     * @param string $alias
     * @param array $options
     */
    public function addFunction($func, $alias = '', array $options = array())
    {
        $this->hSvr->addFunction($func, $alias, $options);
    }

    /**
     * 设置进程名称
     * @param $name
     */
    public function setProcessName($name)
    {
        $this->processName = $name;
    }

    /**
     * 获取进程名称
     * @return string
     */
    public function getProcessName()
    {
        if (empty($this->processName)) {
            global $argv;
            return "php {$argv[0]}";
        } else {
            return $this->processName;
        }
    }

    /**
     * @param $host
     * @param $port
     * @return Server
     */
    public static function autoCreate($host, $port)
    {
        return new self($host, $port);
    }

    /**
     * Server constructor.
     * @param $host
     * @param $port
     * @throws \Exception
     */
    public function __construct($host, $port)
    {
        $this->host = $host;
        $this->port = $port;
        $this->runtimeSetting = [
            'backlog' => 128,        // listen backlog
        ];
        $this->hSvr = new \Hprose\Swoole\Server(sprintf('tcp://%s:%s/', $this->host, $this->port));
    }

}

class ServerOptionException extends \Exception
{

}