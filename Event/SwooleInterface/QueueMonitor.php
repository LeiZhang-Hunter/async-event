<?php

namespace Component\Event\SwooleInterface;

interface QueueMonitor
{
    /**
     * 上报监控指标
     * 上报监控指标的依据
     * @param $config
     * @param $message
     * @param $interval
     * @return mixed
     */
    public function report($config, $message, $interval);
}