<?php

/**
 * 参考muduo 框架 每个Worker进程中都有一个主循环
 * Class QueueLoop
 */

namespace Component\Event\SwooleQueue;

use Component\Event\SwooleInterface\QueueExecutor;
use Component\Event\SwooleLogger\LogLevel;
use Component\Event\SwooleProcess\SwooleProcessManager;

class QueueLoop
{
    /**
     * 队列的实例
     * @var Queue
     */
    private $queueInstance;

    /**
     * 实例名称
     * @var
     */
    private $queueName;

    /**
     * 配置文件
     * @var array
     */
    private $config;

    /**
     * @var $worker QueueWorker
     */
    private $worker;

    /**
     * @var SwooleMonitorSentry
     */
    private $reporter;

    /**
     * 进程的最大处理数目
     * @var int
     */
    private $maxHandler = 0;

    private $currentNumber = 0;

    public function __construct()
    {

    }

    /**
     * 设置工作进程
     * @param QueueWorker $worker
     */
    public function setWorkerInstance(QueueWorker $worker)
    {
        $this->worker = $worker;
    }

    /**
     * 设置队列的实例类
     * @param $queueName
     */
    public function setQueue($queueName)
    {
        $this->queueName = $queueName;
    }

    /**
     * 设置队列的配置文件
     * @param $config
     */
    public function setConfig($config)
    {
        $this->config = $config;
    }

    /**
     * 获取队列的实例
     * @return Queue|mixed
     */
    public function getQueueInstance()
    {
        if (!$this->queueInstance) {
            $this->queueInstance = new $this->queueName($this->config);
        }
        return $this->queueInstance;
    }

    /**
     * 保护函数
     * 达到最大处理书之后就可以重启了
     */
    private function guard()
    {
        if ($this->maxHandler) {
            $this->currentNumber++;

            if ($this->currentNumber >= $this->maxHandler) {
                exit(0);
            }
        }
    }

    /**
     * 主要的事件循环
     * @param $queueName
     */
    public function loop($queueName)
    {
        if (isset($this->config["logger"])) {
            QueueLogger::setLogger($this->config["logger"]);
        }
        $this->setQueue($queueName);
        $this->maxHandler = isset($this->config["max_handler"]) ? $this->config["max_handler"] : 0;

        while (SwooleProcessManager::getSyncPrimitive()->get()) {

            //消费
            $data = $this->getQueueInstance()->pop();
            if ($data) {
                $start_time = QueueTimestamp::microTime();
                $this->execute($data);
                $end_time = QueueTimestamp::microTime();
                $interval = $end_time - $start_time;
                $this->getQueueInstance()->ack();
                //上报
                if ($this->reporter)
                    $this->reporter->report($this->config, $data, $interval);
                //$this->guard();
            }


            if (!$data)
                usleep(100000);
        }
        //释放
        QueueLogger::$logger = null;
    }

    /**
     * 设置报告哨兵
     * @var $reporter
     */
    public function setReporter($reporter)
    {
        if (class_exists($reporter)) {
            $this->reporter = new $reporter;

            if (!$this->reporter instanceof SwooleMonitorSentry) {
                $this->reporter = null;
            }
        }
    }

    /**
     * 执行事件
     * @param $event QueueEvent
     * @return bool
     */
    public function execute(QueueEvent $event)
    {
        $eventHandle = $this->worker->getEventData();
        if (!isset($eventHandle[$event->class])) {
            /**
             * @var $executor QueueExecutor
             */
            $executor = new $event->class;
            call_user_func_array([$executor, $event->method], $event->args);
        }

        return true;
    }
}