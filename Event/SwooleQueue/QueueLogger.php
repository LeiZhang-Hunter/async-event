<?php

namespace Component\Event\SwooleQueue;

use Component\Event\SwooleInterface\LoggerInterface;
use Component\Event\SwooleLogger\Logger;

class QueueLogger
{
    /**
     * @var LoggerInterface
     */
    public static $logger;


    private function __construct()
    {
    }

    public static function setLogger($config, $logger = "")
    {
        if ($logger && class_exists($logger)) {
            self::$logger = $logger($config);
        } else {
            self::$logger = new Logger($config);
        }
    }
}