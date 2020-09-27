<?php

namespace Component\Event\SwooleQueue;

use Component\Event\SwooleInterface\QueueExecutor;

abstract class QueueHandle implements QueueExecutor
{
    /**
     * 执行
     * @param $event
     * @param $data
     * @return mixed
     */
    abstract function execute($event, $data);
}