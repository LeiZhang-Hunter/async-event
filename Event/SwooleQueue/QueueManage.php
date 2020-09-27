<?php

/**
 * 队列管理
 * Class QueueManage
 */

namespace Component\Event\SwooleQueue;

use Component\Event\SwooleProcess\SwooleProcessManager;
use Swoole\Process;

class QueueManage extends SwooleProcessManager
{
    /**
     * @var QueueManage
     */
    private static $manageInstance;

    /**
     * @var array
     */
    private $config;

    private $event;

    /**
     * QueueManage constructor.
     * 配置文件
     * @param array $config
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
        if (!$config) {
            trigger_error("error queue config", E_USER_ERROR);
        }
        $this->config = $config;
    }

    /**
     * 获取实例
     * @param $configKey
     * @return QueueManage
     */
    public static function getInstance($configKey)
    {
        if (!self::$manageInstance) {
            $config = app()->configGet($configKey);
            self::$manageInstance = new self($config);
        }

        return self::$manageInstance;
    }

    public function onStart()
    {
        $pid = parent::onStart(); // TODO: Change the autogenerated stub
    }

    public function setEvent($eventConfigKey)
    {
        $this->event = app()->configGet($eventConfigKey);
    }

    /**
     * 启动
     */
    public function run()
    {
        if (!extension_loaded('swoole')) {
            exit("The swoole extension must support");
        }

        $worker = new QueueWorker();

        $queue = isset($this->config["queue"]) ? $this->config["queue"] : "";
        if (!$queue) {
            trigger_error("config->queue must not be empty", E_USER_ERROR);
        }

        $error_log = isset($this->config["php_error_log"]) ? $this->config["php_error_log"] : "";
        if ($error_log) {
            ini_set("error_log", $error_log);
        }
        $worker->setQueueData($queue, $this->config);
        $worker->setEventData($this->event);
        $this->setOnWorker([$worker, "run"]);
        //加载命令行解析
        $this->manager($this->config);
    }

}