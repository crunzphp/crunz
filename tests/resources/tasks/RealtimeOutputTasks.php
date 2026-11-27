<?php

declare(strict_types=1);

use Crunz\Schedule;

$scheduler = new Schedule();
$scheduler
    ->run(
        static function (): void {
            // Emit the first marker and flush it immediately, then sleep before
            // emitting the second marker. This creates a window during which the
            // first marker has been produced but the task has not yet finished,
            // letting the test observe whether output is streamed in realtime.
            echo 'REALTIME_FIRST' . PHP_EOL;
            \flush();

            \sleep(3);

            echo 'REALTIME_SECOND' . PHP_EOL;
        }
    )
    ->description('Realtime streaming task')
    ->everyMinute()
;

return $scheduler;
