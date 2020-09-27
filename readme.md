配置文件

```php
<?php

return [
    "thread" => [
        //队列配置
        "name" => "thread",
        "queue" => \Event\Queue\RocketMessageDriver::class,
        "pid_file" => "/data0/run/ugcaudit-async-event/run.pid",
        //日志配置
        "logger" => [
            "background_thread" => [
                "run" => true,
                "interval" => 5
            ],
            "max_buffer_size" => 50,
            "dir" => "/data0/log-data/synclog",
            "file_name" => "topic-thread-message"
        ],
        //执行器挂载
        "executor" => [
            //主贴
            "thread" => \Event\Controller\ThreadTopicController::class,
            //回帖
            //"reply" => \Event\Controller\ReplyController::class,
            //拷贝贴
            //"threadCopy" => \Event\Controller\ThreadTopicCopyController::class
            //直播
            "live" => \Event\Controller\LiveController::class
        ],
        //消息解码器,原因是消息body 的协议不一致
        "parser" => [
            "default" => new \Event\Protocol\DefaultParser(),
            "live" => new \Event\Protocol\JsonParser()
        ],
        "reporter" => \Event\Reporter\RigReporter::class,
        "worker_num" => 1,
        "enable_coroutine" => true,
        "php_error_log" => "/data0/log-data/php_error.log",
        //最大处理数目 进程处理程序到一定数目之后会自动重启,防止内存泄露
        "max_handler" => 100,
        "daemonize" => false
    ]
];

```

运行demo

```
        $queueManage = QueueManage::getInstance("event.thread");
        $queueManage->setEvent("trigger");
        //运行异步服务
        $queueManage->run();
```

支持命令:

start

stop

reload