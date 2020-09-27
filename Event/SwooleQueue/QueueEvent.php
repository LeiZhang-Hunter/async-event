<?php

namespace Component\Event\SwooleQueue;
class QueueEvent
{
    /**
     * 类
     * @var
     */
    public $class;
    /**
     * 事件名字
     * @var string
     */
    public $method;

    /**
     * @var array
     */
    public $args = [];
}