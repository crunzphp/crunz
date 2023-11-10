<?php

declare(strict_types=1);

namespace Crunz\Tests\Unit;

use Crunz\Event;
use Crunz\Exception\CrunzException;
use Crunz\Task\TaskException;
use Crunz\Tests\TestCase\Faker;
use Crunz\Tests\TestCase\TestClock;
use Crunz\Tests\TestCase\UnitTestCase;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\PdoStore;
use Symfony\Component\Lock\Store\SemaphoreStore;

final class EventTest extends UnitTestCase
{
    /**
     * The default configuration timezone.
     */
    protected string $defaultTimezone;

    /**
     * Unique identifier for the event.
     */
    protected string $id;

    public function setUp(): void
    {
        $this->id = \uniqid('crunz', true);

        $this->defaultTimezone = \date_default_timezone_get();
        \date_default_timezone_set('UTC');
    }

    public function tearDown(): void
    {
        \date_default_timezone_set($this->defaultTimezone);
    }

    /**
     * @group cronCompile
     */
    public function test_unit_methods(): void
    {
        $e = new Event($this->id, 'php foo');
        self::assertEquals('0 * * * *', $e->hourly()->getExpression());

        $e = new Event($this->id, 'php bar');
        self::assertEquals('0 0 * * *', $e->daily()->getExpression());

        $e = new Event($this->id, 'php foo');
        self::assertEquals('45 15 * * *', $e->dailyAt('15:45')->getExpression());

        $e = new Event($this->id, 'php bar');
        self::assertEquals('0 4,8 * * *', $e->twiceDaily(4, 8)->getExpression());

        $e = new Event($this->id, 'php foo');
        self::assertEquals('0 0 * * 0', $e->weekly()->getExpression());

        $e = new Event($this->id, 'php bar');
        self::assertEquals('0 0 1 * *', $e->monthly()->getExpression());

        $e = new Event($this->id, 'php foo');
        self::assertEquals('0 0 1 */3 *', $e->quarterly()->getExpression());

        $e = new Event($this->id, 'php bar');
        self::assertEquals('0 0 1 1 *', $e->yearly()->getExpression());
    }

    /**
     * @group cronCompile
     */
    public function test_low_level_methods(): void
    {
        $timezone = new \DateTimeZone('UTC');

        $e = new Event($this->id, 'php foo');
        self::assertEquals('30 1 11 4 *', $e->on('01:30 11-04-2016')->getExpression());

        $e = new Event($this->id, 'php bar');
        self::assertEquals('45 13 * * *', $e->on('13:45')->getExpression());

        $e = new Event($this->id, 'php foo');
        self::assertEquals('45 13 * * *', $e->at('13:45')->getExpression());

        $e = new Event($this->id, 'php bar');

        $e->minute([12, 24, 35])
          ->hour('1-5', 4, 8)
          ->dayOfMonth(1, 6, 12, 19, 25)
          ->month('1-8')
          ->dayOfWeek('mon,wed,thu');

        self::assertEquals('12,24,35 1-5,4,8 1,6,12,19,25 1-8 mon,wed,thu', $e->getExpression());

        $e = new Event($this->id, 'php foo');
        self::assertEquals('45 13 * * *', $e->cron('45 13 * * *')->getExpression());

        $e = new Event($this->id, 'php foo');
        self::assertTrue($e->isDue($timezone));
    }

    /**
     * @group cronCompile
     */
    public function test_weekday_methods(): void
    {
        $e = new Event($this->id, 'php qux');
        self::assertEquals('* * * * 2', $e->tuesdays()->getExpression());

        $e = new Event($this->id, 'php flob');
        self::assertEquals('* * * * 3', $e->wednesdays()->getExpression());

        $e = new Event($this->id, 'php foo');
        self::assertEquals('* * * * 4', $e->thursdays()->getExpression());

        $e = new Event($this->id, 'php bar');
        self::assertEquals('* * * * 5', $e->fridays()->getExpression());

        $e = new Event($this->id, 'php baz');
        self::assertEquals('* * * * 1-5', $e->weekdays()->getExpression());

        $e = new Event($this->id, 'php bla');
        self::assertEquals('30 1 * * 2', $e->weeklyOn('2', '01:30')->getExpression());
    }

    public function test_cron_life_time(): void
    {
        $timezone = new \DateTimeZone('UTC');

        $event = new Event($this->id, 'php foo');
        self::assertFalse(
            $event
                ->between('2015-01-01', '2015-01-02')
                ->isDue($timezone)
        );

        $futureDate = new \DateTimeImmutable('+1 year');

        $event = new Event($this->id, 'php foo');
        self::assertFalse(
            $event
                ->from($futureDate->format('Y-m-d'))
                ->isDue($timezone)
        );

        $event = new Event($this->id, 'php foo');
        self::assertFalse(
            $event
                ->to('2015-01-01')
                ->isDue($timezone)
        );
    }

    /**
     * @param \Closure(): array{
     *     dateFrom: string,
     *     dateTo: string
     * } $paramsGenerator
     *
     * @dataProvider dateFromToProvider
     */
    public function test_get_from(\Closure $paramsGenerator): void
    {
        $params = $paramsGenerator();

        $event = new Event($this->id, 'php foo');
        $event->from($params['dateFrom']);

        self::assertSame($params['dateFrom'], $event->getFrom());
    }

    /**
     * @param \Closure(): array{
     *     dateFrom: string,
     *     dateTo: string
     * } $paramsGenerator
     *
     * @dataProvider dateFromToProvider
     */
    public function test_get_to(\Closure $paramsGenerator): void
    {
        $params = $paramsGenerator();

        $event = new Event($this->id, 'php foo');
        $event->to($params['dateTo']);

        self::assertSame($params['dateTo'], $event->getTo());
    }

    /**
     * @param \Closure(): array{
     *     dateFrom: string,
     *     dateTo: string
     * } $paramsGenerator
     *
     * @dataProvider dateFromToProvider
     */
    public function test_get_between(\Closure $paramsGenerator): void
    {
        $params = $paramsGenerator();

        $event = new Event($this->id, 'php foo');
        $event->between($params['dateFrom'], $params['dateTo']);

        self::assertSame($params['dateFrom'], $event->getFrom());

        self::assertSame($params['dateTo'], $event->getTo());
    }

    public function test_cron_conditions(): void
    {
        $timezone = new \DateTimeZone('UTC');

        $e = new Event($this->id, 'php foo');
        self::assertFalse($e->cron('* * * * *')->when(fn () => false)->isDue($timezone));

        $e = new Event($this->id, 'php foo');
        self::assertTrue($e->cron('* * * * *')->when(fn () => true)->isDue($timezone));

        $e = new Event($this->id, 'php foo');
        self::assertFalse($e->cron('* * * * *')->skip(fn () => true)->isDue($timezone));

        $e = new Event($this->id, 'php foo');
        self::assertTrue($e->cron('* * * * *')->skip(fn () => false)->isDue($timezone));
    }

    /** @test */
    public function more_than_five_parts_in_cron_expression_results_in_exception(): void
    {
        $this->expectException(TaskException::class);
        $this->expectExceptionMessage("Expression '* * * * * *' has more than five parts and this is not allowed.");

        $e = new Event(1, 'php foo -v');
        $e->cron('* * * * * *');
    }

    public function test_build_command(): void
    {
        $e = new Event($this->id, 'php -i');

        self::assertSame('php -i', $e->buildCommand());
    }

    public function test_is_due(): void
    {
        $timezone = new \DateTimeZone('UTC');
        $this->setClockNow(new \DateTimeImmutable('2015-04-12 00:00:00', $timezone));

        $e = new Event($this->id, 'php foo');
        self::assertTrue($e->sundays()->isDue($timezone));

        $e = new Event($this->id, 'php bar');
        self::assertEquals('0 19 * * 6', $e->saturdays()->at('19:00')->timezone('EST')->getExpression());
        self::assertTrue($e->isDue($timezone));

        $e = new Event($this->id, 'php bar');
        $this->setClockNow(new \DateTimeImmutable(\date('Y') . '-04-12 00:00:00'));
        self::assertTrue($e->on('00:00 ' . \date('Y') . '-04-12')->isDue($timezone));
    }

    public function test_name(): void
    {
        $e = new Event($this->id, 'php foo');
        $e->description('Testing Cron');

        self::assertEquals('Testing Cron', $e->description);
    }

    /** @test */
    public function in_change_working_directory_in_build_command_on_windows(): void
    {
        if (!$this->isWindows()) {
            self::markTestSkipped('Required Windows OS.');
        }

        $workingDir = 'C:\\windows\\temp';
        $event = new Event($this->id, 'php -v');

        $event->in($workingDir);

        self::assertSame("cd /d {$workingDir} & php -v", $event->buildCommand());
    }

    /** @test */
    public function in_change_working_directory_in_build_command_on_unix(): void
    {
        if ($this->isWindows()) {
            self::markTestSkipped('Required Unix-based OS.');
        }

        $event = new Event($this->id, 'php -v');

        $event->in('/tmp');

        self::assertSame('cd /tmp; php -v', $event->buildCommand());
    }

    /** @test */
    public function on_do_not_run_task_every_minute(): void
    {
        $event = new Event($this->id, 'php -i');

        $event->on('Thursday 8:00');

        self::assertSame('0 8 * * *', $event->getExpression());
    }

    /** @test */
    public function setting_user_prepend_sudo_to_command(): void
    {
        if ($this->isWindows()) {
            self::markTestSkipped('Required Unix-based OS.');
        }

        $event = new Event($this->id, 'php -v');

        $event->user('john');

        self::assertSame('sudo -u john php -v', $event->buildCommand());
    }

    /** @test */
    public function custom_user_and_cwd(): void
    {
        if ($this->isWindows()) {
            self::markTestSkipped('Required Unix-based OS.');
        }

        $event = new Event($this->id, 'php -i');

        $event->user('john');
        $event->in('/var/test');

        self::assertSame('sudo -u john cd /var/test; sudo -u john php -i', $event->buildCommand());
    }

    /** @test */
    public function not_implemented_user_change_on_windows(): void
    {
        if (!$this->isWindows()) {
            self::markTestSkipped('Required Windows OS.');
        }

        $this->expectException(\Crunz\Exception\NotImplementedException::class);
        $this->expectExceptionMessage('Changing user on Windows is not implemented.');

        $event = new Event($this->id, 'php -i');

        $event->user('john');
    }

    /**
     * @test
     *
     * @runInSeparateProcess
     */
    public function closure_command_have_full_binary_paths(): void
    {
        if (!\defined('CRUNZ_BIN')) {
            \define('CRUNZ_BIN', __FILE__);
        }

        $closure = fn () => 0;
        $closureSerializer = $this->createClosureSerializer();
        $serializedClosure = $closureSerializer->serialize($closure);
        $queryClosure = \http_build_query([$serializedClosure]);
        $crunzBin = CRUNZ_BIN;

        $event = new Event($this->id, $closure);

        $command = $event->buildCommand();

        self::assertSame(PHP_BINARY . " {$crunzBin} closure:run {$queryClosure}", $command);
    }

    /** @test */
    public function whole_output_catches_stdout_and_stderr(): void
    {
        $command = "php -r \"echo 'Test output'; throw new \Exception('Exception output');\"";
        $event = new Event(\uniqid('c', true), $command);
        $event->start();
        $process = $event->getProcess();

        while ($process->isRunning()) {
            \usleep(20000); // wait 20 ms
        }

        $wholeOutput = $event->wholeOutput();

        self::assertStringContainsString(
            'Test output',
            $wholeOutput,
            'Missing standard output'
        );
        self::assertStringContainsString(
            'Exception output',
            $wholeOutput,
            'Missing error output'
        );
    }

    /** @test */
    public function task_will_prevent_overlapping_with_default_store(): void
    {
        $this->assertPreventOverlapping();
    }

    /** @test */
    public function task_will_prevent_overlapping_with_semaphore_store(): void
    {
        if (!\extension_loaded('sysvsem')) {
            self::markTestSkipped('Semaphore extension not installed.');
        }

        $this->assertPreventOverlapping(new SemaphoreStore());
    }

    /** @dataProvider everyMethodProvider */
    public function test_every_methods(string $method, string $expectedCronExpression): void
    {
        // Arrange
        $event = new Event($this->id, 'php -i');
        /** @var callable $methodCall */
        $methodCall = [$event, $method];
        $methodCallClosure = \Closure::fromCallable($methodCall);

        // Act
        $methodCallClosure();

        // Assert
        self::assertSame($expectedCronExpression, $event->getExpression());
    }

    public function test_hourly_at_with_valid_minute(): void
    {
        // Arrange
        $event = $this->createEvent();
        $minute = Faker::int(0, 59);

        // Act
        $event->hourlyAt($minute);

        // Assert
        self::assertSame("{$minute} * * * *", $event->getExpression());
    }

    /** @dataProvider hourlyAtInvalidProvider */
    public function test_hourly_at_with_invalid_minute(
        int $minute,
        string $expectedExceptionMessage
    ): void {
        // Arrange
        $event = $this->createEvent();

        // Expect
        $this->expectException(CrunzException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        // Act
        $event->hourlyAt($minute);
    }

    public function test_non_blocking_store_can_be_passed_to_prevent_overlapping(): void
    {
        // Arrange
        $store = new PdoStore('');
        $event = $this->createEvent();

        // Expect
        $this->expectNotToPerformAssertions();

        // Act
        $event->preventOverlapping($store);
    }

    /**
     * @param \Closure(): array{
     *     now: \DateTimeImmutable,
     *     fromDateTime: string,
     *     timeZone: \DateTimeZone,
     *     expectedIsDue: bool,
     * } $paramsGenerator
     *
     * @dataProvider fromTimeZoneProvider
     */
    public function test_from_respects_time_zone(\Closure $paramsGenerator): void
    {
        // Arrange
        [
            'now' => $now,
            'fromDateTime' => $fromDateTime,
            'timeZone' => $timeZone,
            'expectedIsDue' => $expectedIsDue,
        ] = $paramsGenerator();
        $this->setClockNow($now);
        $event = $this->createEvent();
        $event->from($fromDateTime);

        // Act
        $isDue = $event->isDue($timeZone);

        // Assert
        self::assertSame($expectedIsDue, $isDue);
    }

    /**
     * @param \Closure(): array{
     *     now: \DateTimeImmutable,
     *     toDateTime: string,
     *     timeZone: \DateTimeZone,
     *     expectedIsDue: bool,
     * } $paramsGenerator
     *
     * @dataProvider toTimeZoneProvider
     */
    public function test_to_respects_timezone(\Closure $paramsGenerator): void
    {
        // Arrange
        [
            'now' => $now,
            'toDateTime' => $toDateTime,
            'timeZone' => $timeZone,
            'expectedIsDue' => $expectedIsDue,
        ] = $paramsGenerator();
        $this->setClockNow($now);
        $event = $this->createEvent();
        $event->to($toDateTime);

        // Act
        $isDue = $event->isDue($timeZone);

        // Assert
        self::assertSame($expectedIsDue, $isDue);
    }

    /** @return iterable<string,array> */
    public function deprecatedEveryProvider(): iterable
    {
        yield 'every seven minutes' => ['everySevenMinutes'];
        yield 'every five hours' => ['everyFiveHours'];
        yield 'every two days' => ['everyTwoDays'];
        yield 'every five months' => ['everyFiveMonths'];
    }

    /** @return iterable<string,array> */
    public function everyMethodProvider(): iterable
    {
        yield 'every minute' => ['everyMinute', '* * * * *'];
        yield 'every two minutes' => ['everyTwoMinutes', '*/2 * * * *'];
        yield 'every three minutes' => ['everyThreeMinutes', '*/3 * * * *'];
        yield 'every four minutes' => ['everyFourMinutes', '*/4 * * * *'];
        yield 'every five minutes' => ['everyFiveMinutes', '*/5 * * * *'];
        yield 'every ten minutes' => ['everyTenMinutes', '*/10 * * * *'];
        yield 'every fifteen minutes' => ['everyFifteenMinutes', '*/15 * * * *'];
        yield 'every thirty minutes' => ['everyThirtyMinutes', '*/30 * * * *'];
        yield 'every two hours' => ['everyTwoHours', '0 */2 * * *'];
        yield 'every three hours' => ['everyThreeHours', '0 */3 * * *'];
        yield 'every four hours' => ['everyFourHours', '0 */4 * * *'];
        yield 'every six hours' => ['everySixHours', '0 */6 * * *'];
    }

    /** @return iterable<string, array{\Closure}> */
    public static function fromTimeZoneProvider(): iterable
    {
        yield 'same timezone' => [
            static function (): array {
                $timeZone = new \DateTimeZone('Europe/Warsaw');

                return [
                    'now' => new \DateTimeImmutable(
                        '12:01',
                        $timeZone,
                    ),
                    'fromDateTime' => '12:00',
                    'timeZone' => $timeZone,
                    'expectedIsDue' => true,
                ];
            },
        ];

        yield 'different timezones' => [
            static fn (): array => [
                'now' => new \DateTimeImmutable(
                    '12:00',
                    new \DateTimeZone('Europe/Warsaw'),
                ),
                'fromDateTime' => '11:01',
                'timeZone' => new \DateTimeZone('Europe/Lisbon'),
                'expectedIsDue' => false,
            ],
        ];
    }

    /** @return iterable<string, array{\Closure}> */
    public static function toTimeZoneProvider(): iterable
    {
        yield 'same timezone' => [
            static function (): array {
                $timeZone = new \DateTimeZone('Europe/Warsaw');

                return [
                    'now' => new \DateTimeImmutable(
                        '13:59',
                        $timeZone,
                    ),
                    'toDateTime' => '14:00',
                    'timeZone' => $timeZone,
                    'expectedIsDue' => true,
                ];
            },
        ];

        yield 'different timezones' => [
            static fn (): array => [
                'now' => new \DateTimeImmutable(
                    '17:01',
                    new \DateTimeZone('Europe/Lisbon'),
                ),
                'toDateTime' => '18:00',
                'timeZone' => new \DateTimeZone('Europe/Warsaw'),
                'expectedIsDue' => false,
            ],
        ];
    }

    /** @return iterable<string,array> */
    public function hourlyAtInvalidProvider(): iterable
    {
        yield 'minute below zero' => [
            Faker::int(-100, -1),
            "Minute cannot be lower than '0'.",
        ];

        yield 'minute above fifty nine' => [
            Faker::int(60, 120),
            "Minute cannot be greater than '59'.",
        ];
    }

    /** @return iterable<string, array{\Closure}> */
    public function dateFromToProvider(): iterable
    {
        yield 'dateFrom, dateTo with format yyyy-mm-dd' => [
            static fn (): array => [
                'dateFrom' => (new \DateTime('+' . \random_int(1, 59) . ' days'))->format('Y-m-d'),
                'dateTo' => (new \DateTime('+' . \random_int(60, 120) . ' days'))->format('Y-m-d'),
            ],
        ];

        yield 'dateFrom, dateTo with format H:i' => [
            static fn (): array => [
                'dateFrom' => (new \DateTime('+' . \random_int(1, 29) . ' minutes'))->format('H:i'),
                'dateTo' => (new \DateTime('+' . \random_int(30, 60) . ' minutes'))->format('H:i'),
            ],
        ];

        yield 'dateFrom, dateTo with format yyyy-mm-dd hh:mm' => [
            static fn (): array => [
                'dateFrom' => (new \DateTime('+' . \random_int(1, 59) . ' days +' . \random_int(1, 29) . ' minutes'))->format('Y-m-d H:i'),
                'dateTo' => (new \DateTime('+' . \random_int(60, 120) . ' days +' . \random_int(30, 60) . ' minutes'))->format('Y-m-d H:i'),
            ],
        ];
    }

    private function assertPreventOverlapping(?PersistingStoreInterface $store = null): void
    {
        $event = $this->createPreventOverlappingEvent($store);
        $event2 = $this->createPreventOverlappingEvent($store);

        $event->start();

        self::assertFalse($event2->isDue(new \DateTimeZone('UTC')));
    }

    private function createPreventOverlappingEvent(?PersistingStoreInterface $store = null): Event
    {
        $command = "php -r 'sleep(2);'";

        $event = new Event(\uniqid('c', true), $command);
        $event->preventOverlapping($store);
        $event->everyMinute();

        return $event;
    }

    private function setClockNow(\DateTimeImmutable $dateTime): void
    {
        $testClock = new TestClock($dateTime);
        $reflection = new \ReflectionClass(Event::class);
        $property = $reflection->getProperty('clock');
        $property->setAccessible(true);
        $property->setValue(null, $testClock);
    }

    private function isWindows(): bool
    {
        return DIRECTORY_SEPARATOR === '\\';
    }

    private function createEvent(): Event
    {
        return new Event(
            \uniqid(
                'c',
                true,
            ),
            'php -i',
        );
    }
}
