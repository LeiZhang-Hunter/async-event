<?php

namespace Component\Event\SwooleInterface;

use Component\Event\SwooleQueue\QueueEvent;

interface QueueInterface
{

    public function __construct($config);

    //入栈

    /**
     * @param $message
     * @return mixed
     */
    public function push(QueueEvent $message);

    /**
     * @return QueueEvent
     */
    public function pop() :? QueueEvent;

    //确认
    public function ack();

}