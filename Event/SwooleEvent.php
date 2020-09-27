<?php

namespace Component\Event;

use Component\Event\SwooleInterface\QueueTrigger;
use Component\Event\SwooleLogger\Logger;
use Component\Event\SwooleQueue\Queue;
use Component\Event\SwooleQueue\QueueEvent;

class SwooleEvent implements QueueTrigger
{
    /**
     * @var array
     */
    private $config = [];

    /**
     * @var $instance
     */
    private static $instance;

    /**
     * @var Queue
     */
    private $queue;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct($configName)
    {
        $this->config = app()->configGet($configName);

        if (!isset($this->config["logger"])) {
            $this->config["logger"] = [];
        }
        $this->logger = new Logger($this->config["logger"]);
    }

    public static function getInstance($configName)
    {
        if (!self::$instance) {
            self::$instance = new self($configName);
        }
        return self::$instance;
    }

    /**
     * @return Queue|false|mixed
     */
    private function getQueue()
    {
        if (!$this->queue) {
            $queue = $this->config["queue"];
            if (!$queue) {
                throw new \Exception("The configuration option of the queue cannot be empty");
            }

            $this->queue = new $queue($this->config);
        }

        return $this->queue;
    }

    /**
     * 推入触发器
     * @param $class
     * @param $method
     * @param array $args
     * @return mixed
     * @throws \Exception
     */
    public function trigger($class, $method, $args = [])
    {
        //检查类是否存在
        if (!class_exists($class)) {
            $trace = debug_backtrace();
            trigger_error(
                "$class is not exist, [file:{$trace[0]["file"]}, line:{$trace[0]["line"]}]",
                E_USER_WARNING);
            return false;
        }

        //检查方法是否存在
        if (!method_exists($class, $method)) {
            $trace = debug_backtrace();
            trigger_error(
                "$class->$method is not exist, [file:{$trace[0]["file"]}, line:{$trace[0]["line"]}]",
                E_USER_WARNING);
            return false;
        }

        $methodReflection = new \ReflectionMethod($class, $method);
        $request_number = $methodReflection->getNumberOfRequiredParameters();
        if ($request_number > sizeof($args)) {
            $trace = debug_backtrace();
            trigger_error(
                "$class->$method args error, At least one parameter, [file:{$trace[0]["file"]}, line:{$trace[0]["line"]}]",
                E_USER_WARNING);
            return false;
        }
        $event = new QueueEvent();
        $event->class = $class;
        $event->method = $method;
        $event->args = $args;
        $ret = $this->getQueue()->push($event);
        return $ret;
    }

    /**
     * 写入日志
     * @param $level
     * @param $message
     */
    public function log($level, $message)
    {
        return $this->logger->log($level, $message);
    }
}