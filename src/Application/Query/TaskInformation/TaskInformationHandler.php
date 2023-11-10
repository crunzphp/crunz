<?php

declare(strict_types=1);

namespace Crunz\Application\Query\TaskInformation;

use Crunz\Application\Cron\CronExpressionFactoryInterface;
use Crunz\Application\Service\ConfigurationInterface;
use Crunz\Event;
use Crunz\Schedule\ScheduleFactory;
use Crunz\Task\CollectionInterface;
use Crunz\Task\LoaderInterface;
use Crunz\Task\Timezone;

final class TaskInformationHandler
{
    public function __construct(
        private readonly Timezone $timezone,
        private readonly ConfigurationInterface $configuration,
        private readonly CollectionInterface $taskCollection,
        private readonly LoaderInterface $taskLoader,
        private readonly ScheduleFactory $scheduleFactory,
        private readonly CronExpressionFactoryInterface $cronExpressionFactory,
    ) {
    }

    public function handle(TaskInformation $taskInformation): TaskInformationView
    {
        $source = $this->configuration
            ->getSourcePath()
        ;
        /** @var \SplFileInfo[] $files */
        $files = $this->taskCollection
            ->all($source)
        ;

        // List of schedules
        $schedules = $this->taskLoader
            ->load(...\array_values($files))
        ;

        $timezoneForComparisons = $this->timezone
            ->timezoneForComparisons()
        ;
        $event = $this->scheduleFactory
            ->singleTask($taskInformation->taskNumber(), ...$schedules)
        ;

        $cronExpression = $this->cronExpressionFactory
            ->createFromString($event->getExpression())
        ;
        $nextRunTimezone = $timezoneForComparisons;
        $eventProperties = $this->getEventProperties($event, ['timezone', 'preventOverlapping']);
        $eventTimezone = $eventProperties['timezone'];
        if (\is_string($eventTimezone)) {
            $eventTimezone = new \DateTimeZone($eventTimezone);
            $nextRunTimezone = $eventTimezone;
        }

        $nextRuns = $cronExpression->multipleRunDates(
            5,
            new \DateTimeImmutable(),
            $nextRunTimezone
        );

        return new TaskInformationView(
            $event->getCommand(),
            $event->description ?? '',
            $event->getExpression(),
            \filter_var($eventProperties['preventOverlapping'] ?? false, FILTER_VALIDATE_BOOLEAN),
            $eventTimezone,
            $timezoneForComparisons,
            ...$nextRuns
        );
    }

    /**
     * @param string[] $properties
     *
     * @return array<string,mixed>
     */
    private function getEventProperties(Event $event, array $properties): array
    {
        $propertiesExtractor = function () use ($properties, $event): array {
            $values = [];
            foreach ($properties as $property) {
                if (!\property_exists($event, $property)) {
                    $class = $event::class;

                    throw new \RuntimeException("Property '{$property}' doesn't exists in '{$class}' class.");
                }

                $values[$property] = $this->{$property};
            }

            return $values;
        };

        return $propertiesExtractor->bindTo($event, Event::class)();
    }
}
