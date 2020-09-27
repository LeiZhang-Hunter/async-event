<?php

/**
 * 队列监控的哨兵
 */

namespace Component\Event\SwooleQueue;

use Component\Event\SwooleInterface\QueueMonitor;
use Component\Event\SwooleLogger\Logger;

abstract class SwooleMonitorSentry implements QueueMonitor
{
    /**
     * 上报监控指标
     * @param $config
     * @param $content
     * @param $interval
     * @return mixed
     */
    abstract function report($config, $content, $interval);
}