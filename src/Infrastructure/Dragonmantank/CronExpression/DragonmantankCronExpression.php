<?php

declare(strict_types=1);

namespace Crunz\Infrastructure\Dragonmantank\CronExpression;

use Cron\CronExpression;
use Crunz\Application\Cron\CronExpressionInterface;

final class DragonmantankCronExpression implements CronExpressionInterface
{
    public function __construct(private readonly CronExpression $innerCronExpression)
    {
    }

    public function multipleRunDates(int $total, \DateTimeImmutable $now, ?\DateTimeZone $timeZone = null): array
    {
        $timeZoneNow = null !== $timeZone
            ? $now->setTimezone($timeZone)
            : $now
        ;

        $dates = $this->innerCronExpression
            ->getMultipleRunDates($total, $timeZoneNow)
        ;

        return \array_map(
            static fn (\DateTime $runDate): \DateTimeImmutable => \DateTimeImmutable::createFromMutable($runDate),
            $dates
        );
    }
}
