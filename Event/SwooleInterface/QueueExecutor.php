<?php

namespace Component\Event\SwooleInterface;

interface QueueExecutor
{

    /**
     * 执行
     * @param $event
     * @param $data
     * @return mixed
     */
    public function execute($event, $data);
}

