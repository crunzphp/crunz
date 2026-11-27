<?php

declare(strict_types=1);

use Crunz\Schedule;

$scheduler = new Schedule();
$scheduler
    ->run(
        static function (): void {
            // Emit the first marker and flush it, then sleep before emitting the
            // second. With realtime output the chunks are written to the log file
            // as they are produced, so the first marker reaches the file before
            // the task finishes.
            echo 'FILE_FIRST' . PHP_EOL;
            \flush();

            \sleep(3);

            echo 'FILE_SECOND' . PHP_EOL;
        }
    )
    ->description('Realtime file streaming task')
    ->everyMinute()
    ->appendOutputTo('realtime.log')
;

return $scheduler;
