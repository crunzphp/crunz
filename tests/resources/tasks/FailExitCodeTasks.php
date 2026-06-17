<?php

declare(strict_types=1);

use Crunz\Schedule;

$scheduler = new Schedule();
$scheduler
    ->run('php -r "exit(1);"')
    ->description('Task that will fail with exit code 1')
    ->everyMinute()
;

return $scheduler;
