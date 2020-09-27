<?php

/**
 * 时间处理器
 * Class QueueTimestamp
 */

namespace Component\Event\SwooleQueue;

class QueueTimestamp
{
    /**
     * 获取微秒时间戳 浮点型
     * @return float
     */
    public static function microTimeFloat()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }

    /**
     * 获取微秒时间戳 整数
     * @return int
     */
    public static function microTime()
    {
        $time = microtime(true);
        $result = $time * 1000 * 1000;
        return (int)$result;
    }
}