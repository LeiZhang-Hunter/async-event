<?php

namespace Component\Event\SwooleProcess;

use  Swoole\Process;
use Swoole\Coroutine;

class SwooleProcessWorker
{

    /**
     * 工作进程
     * @var Process
     */
    private $worker;

    /**
     * 进程id
     * @var int
     */
    private $pid;

    public function __construct($hook)
    {
        $this->worker = new Process($hook);
    }

    public function getProcessId()
    {
        return $this->pid;
    }

    public function output($pid)
    {
        $socket = $this->worker->exportSocket();
        while (SwooleProcessManager::getSyncPrimitive()->get()) {
            $data = $socket->recv();
        }
    }

    public function run()
    {
        $this->pid = $this->worker->start();
        //创建一个协程容器
        return $this->pid;
    }
}