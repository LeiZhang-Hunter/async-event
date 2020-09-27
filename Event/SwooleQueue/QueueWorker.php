<?php

namespace Component\Event\SwooleQueue;

use Swoole\Coroutine;
use Swoole\Runtime;
use Component\Event\SwooleLogger\Logger;

class QueueWorker
{
    /**
     * 队列的实例
     * @var Queue
     */
    private $queueName;
    /**
     * @var \Component\Event\SwooleQueue\QueueLoop
     */
    private $loop;

    /**
     * 事件
     * @var $event
     */
    private $event;

    /**
     * 是否开启协程
     * @var bool
     */
    private $enableCo;

    private $config = [];

    public function __construct()
    {
        $this->loop = new QueueLoop();
    }

    public function setQueueData($queueName, $config)
    {
        $this->queueName = $queueName;
        $this->config = $config;
    }

    public function setEventData(array $event)
    {
        $this->event = $event;
    }

    public function getEventData()
    {
        return $this->event;
    }

    //运行worker
    public function run()
    {
        //控制反转
        $this->loop->setWorkerInstance($this);
        if (isset($this->config["reporter"])) {
            $this->loop->setReporter($this->config["reporter"]);
        }
        $this->enableCo = isset($this->config["enable_coroutine"]) ? $this->config["enable_coroutine"] : 0;
        $this->loop->setQueue($this->queueName);
        $this->loop->setConfig($this->config);
        if ($this->enableCo) {
            Runtime::enableCoroutine(SWOOLE_HOOK_ALL);
            Coroutine::create([$this->loop, "loop"], $this->queueName);
        } else {
            $this->loop->loop($this->queueName);
        }

    }

}