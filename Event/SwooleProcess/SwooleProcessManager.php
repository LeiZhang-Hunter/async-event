<?php

/**
 * 进程管理
 */

namespace Component\Event\SwooleProcess;

use Component\Event\SwooleQueue\Queue;
use Hamcrest\Type\IsCallable;
use Swoole\Process;
use Swoole\Atomic;
use Swoole\Event;
use Swoole\Timer;

class SwooleProcessManager
{
    /**
     * @var SwooleCommand
     */
    public $command;

    /**
     * 文件目录
     * @var string
     */
    private $pidDir;

    /**
     * @var Queue
     */
    private $queue;

    /**
     * 进程id
     * @var int
     */
    private $pid;

    /**
     * 同步原语
     * @var Swoole\Atomic
     */
    private static $syncPrimitive;

    /**
     * worker的回调
     * @var IsCallable
     */
    private $onWorker;

    /**
     * 工作进程的数目
     * @var int
     */
    private $workerNumber;

    /**
     * 进程池管理区域
     * @var array
     */
    private static $processPool;

    /**
     * 守护进程标志位
     * @var int
     */
    private $daemonize;

    /**
     * 是否支持热重载
     * @var int
     */
    private $isReload;

    /**
     * 打开热重载
     */
    const OPEN_OVERLOAD = 1;

    /**
     * 关闭热重载
     */
    const CLOSE_OVERLOAD = 0;

    /**
     * 启动进程池
     */
    const OPEN_PROCESS_POOL = 1;

    /**
     * 关闭进程池
     */
    const STOP_PROCESS_POOL = 0;

    private $config;

    public function __construct($config)
    {
        $this->command = new SwooleCommand();
        $this->config = $config;
    }

    /**
     * 生成pid文件，用来做互斥
     * @return int
     */
    protected function mutexPid()
    {
        //使用文件锁来判断进程池是否启动
        static $fp;
        $fp = fopen($this->pidDir, "r+");
        if (!$fp) {
            exit(-1);
        }
        $mutex = flock($fp, LOCK_EX  | LOCK_NB);
        if (!$mutex) {
            exit("process manager has been running\n");
        }

        if (isset($this->config["daemonize"]) && $this->config["daemonize"]) {
            Process::daemon(true, false);
        }

        $this->pid = posix_getpid();

        $result = fwrite($fp, $this->pid, strlen($this->pid));

        if (!$result) {
            trigger_error("fwrite pid file error($this->pidDir)", E_USER_ERROR);
        }
        return $this->pid;
    }

    protected function getPid()
    {
        $pid = (int)file_get_contents($this->pidDir);
        return $pid;
    }

    public function onStart()
    {
        $pid = $this->mutexPid();
        return $pid;
    }

    public function signal($pid, $signo)
    {
        /**
         * -1 是一个非常危险的行为，在这里一定要做判断
         */
        if ($pid <= 0) {
            return false;
        }
        return posix_kill($pid, $signo);
    }

    /**
     * 发出停止信号
     */
    public function onStop()
    {
        $pid = $this->getPid();

        $this->signal($pid, SIGTERM);
        exit(0);
    }

    public function onReload()
    {
        $pid = $this->getPid();

        $this->signal($pid, SIGUSR1);
        exit(0);
    }

    /**
     * 启动进程池
     */
    private function startProcessPool()
    {
        for ($count = 0; $count < $this->workerNumber; $count++) {
            $worker = new SwooleProcessWorker($this->onWorker);
            $worker_process_id = $worker->run();
            self::$processPool[$worker_process_id] = $worker;
        }
        return true;
    }


    /**
     * 监控进程池的运行状况
     */
    public function monitorProcessPool()
    {
        while (1) {
            //出现问题的进程
            $killProcess = Process::wait(false);

            if ($killProcess) {

                $killPid = $killProcess["pid"];

                //检查在进程池里面是否存在
                if (!isset(self::$processPool[$killPid])) {
                    break;
                }

                $worker = self::$processPool[$killPid];

                //检查是否要停止
                if (self::getSyncPrimitive()->get() == self::OPEN_PROCESS_POOL) {
                    //意外停止
                    $pid = $worker->run();
                    self::$processPool[$pid] = $worker;
                    unset(self::$processPool[$killPid]);
                } else {
                    //平滑退出
                    unset(self::$processPool[$killPid]);
                    $poolSize = sizeof(self::$processPool);
                    if (!$poolSize) {
                        //首先检查是否要进行热重载
                        if ($this->isReload == self::OPEN_OVERLOAD) {
                            //关闭重载
                            $this->closeReloadProcessPool();;
                            //重新启动进程池
                            $this->startProcessPool();
                        } else {
                            Event::exit();
                        }
                    }
                }

            } else {
                break;
            }
        }
    }

    /**
     * 停止进程池
     */
    public function stopProcessPool()
    {
        $this->isReload = self::CLOSE_OVERLOAD;
        self::$syncPrimitive->set(self::STOP_PROCESS_POOL);
    }

    /**
     * 重载进程池
     */
    public function reloadProcessPool()
    {
        //设置标志位为热重载
        $this->isReload = self::OPEN_OVERLOAD;
        //关闭子进程
        self::$syncPrimitive->set(self::STOP_PROCESS_POOL);
    }

    /**
     * 关闭进程池重载
     */
    public function closeReloadProcessPool()
    {
        self::getSyncPrimitive()->set(self::OPEN_PROCESS_POOL);
        $this->isReload = self::CLOSE_OVERLOAD;
    }

    /**
     * 安装信号处理器
     */
    private function installSignalHandle()
    {
        //保护工作进程的平稳运行
        Process::signal(SIGCHLD, [$this, "monitorProcessPool"]);

        //执行热重载
        Process::signal(SIGUSR1, [$this, "reloadProcessPool"]);

        //监控进程的停止行为
        Process::signal(SIGTERM, [$this, "stopProcessPool"]);
    }

    /**
     * 获取进程启动状态
     */
    public static function getProcessPoolWorkerStatus()
    {
        return self::$syncPrimitive->get();
    }

    /**
     * 同步原语工具，类似java的CountDownLatch
     * @return Swoole\Atomic
     */
    public static function getSyncPrimitive()
    {
        return self::$syncPrimitive;
    }

    /**
     * 初始化进程池
     * @param array $config
     */
    private function initManager(array $config)
    {
        if (!isset($config["pid_file"]) || !$config["pid_file"]) {
            trigger_error("pid_file must not be null", E_USER_ERROR);
        }
        $this->pidDir = $config["pid_file"];

        //初始化进程之间同步器
        self::$syncPrimitive = new Atomic();

        //打开子进程运行标志位
        self::$syncPrimitive->add();

        //运行命令行进程
        //设置启动的钩子
        $this->command->setStartCallable([$this, "onStart"]);
        //设置停止的钩子
        $this->command->setStopCallable([$this, "onStop"]);
        //设置重启的钩子
        $this->command->setReloadCallable([$this, "onReload"]);
        //解析工具
        $this->command->parse();

        //确认工作进程的数目
        $workerNumber = isset($config["worker_num"]) ? (int)$config["worker_num"] : 1;
        if ($workerNumber < 1)
            $workerNumber = 1;
        $this->workerNumber = $workerNumber;
    }

    /**
     * 设置Worker进程的回调
     */
    public function setOnWorker($hook)
    {
        if (!is_callable($hook)) {
            trigger_error("setOnWorker must be callable", E_USER_ERROR);
        }

        $this->onWorker = $hook;
    }


    /**
     * 管理进程池
     */
    public function manager(array $config)
    {
        //初始化进程池的配置
        $this->initManager($config);

        //启动进程池
        $this->startProcessPool();

        //安装信号处理器
        $this->installSignalHandle();

        //区分Swoole的版本，高版本不在隐式监听事件循环
        if (version_compare(swoole_version(), "4.4.0", ">")) {
            Timer::tick(2000, function () {
            });
        }

        Event::wait();
    }

}