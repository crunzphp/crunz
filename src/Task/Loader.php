<?php

declare(strict_types=1);

namespace Crunz\Task;

use Crunz\Schedule;

final class Loader implements LoaderInterface
{
    /** @return Schedule[] */
    public function load(string $source, \SplFileInfo ...$files): array
    {
        $schedules = [];
        foreach ($files as $file) {
            /**
             * Actual "require" is in separated method to make sure
             * local variables are not overwritten by required file
             * See: https://github.com/lavary/crunz/issues/242 for more information.
             */
            $schedule = $this->loadSchedule($file);
            if (!$schedule instanceof Schedule) {
                throw WrongTaskInstanceException::fromFilePath($file, $schedule);
            }

            $events = $schedule->events();
            foreach ($events as $event_key => $event) {
                $events[$event_key]->sourceFile(\str_replace($source, '', $file->getRealPath()));
            }

            $schedule->events($events);

            $schedules[] = $schedule;
        }

        return $schedules;
    }

    /** @return Schedule|mixed */
    private function loadSchedule(\SplFileInfo $file)
    {
        return require $file->getRealPath();
    }
}
