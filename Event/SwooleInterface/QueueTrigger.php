<?php
/**
 * 异步触发器
 */

namespace Component\Event\SwooleInterface;

interface QueueTrigger
{
    /**
     * QueueTrigger constructor.
     * 队列名称
     * @param $queue
     */
    public function __construct($queue);

    /**
     * 事件
     * @param $class
     * @param $method
     * @param array $args
     * @return mixed
     */
    public function trigger($class, $method, $args = []);
}
