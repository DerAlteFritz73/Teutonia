<?php

namespace App\Tests\Extension;

use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Test\Errored;
use PHPUnit\Event\Test\ErroredSubscriber;
use PHPUnit\Event\Test\Failed;
use PHPUnit\Event\Test\FailedSubscriber;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;
use PHPUnit\Event\Test\Passed;
use PHPUnit\Event\Test\PassedSubscriber;
use PHPUnit\Event\Test\Skipped;
use PHPUnit\Event\Test\SkippedSubscriber;
use PHPUnit\Event\TestRunner\Finished as RunnerFinished;
use PHPUnit\Event\TestRunner\FinishedSubscriber as RunnerFinishedSubscriber;
use PHPUnit\Event\TestRunner\Started as RunnerStarted;
use PHPUnit\Event\TestRunner\StartedSubscriber as RunnerStartedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

final class TestResultsExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        try {
            $storage = new TestResultsStorage();
        } catch (\Throwable $e) {
            // Don't let DB issues break the test run
            fwrite(STDERR, "\n[TestResultsExtension] Could not connect: {$e->getMessage()}\n");
            return;
        }

        $facade->registerSubscribers(
            new class($storage) implements RunnerStartedSubscriber {
                public function __construct(private TestResultsStorage $storage) {}
                public function notify(RunnerStarted $event): void { $this->storage->startRun(); }
            },

            new class($storage) implements PassedSubscriber {
                public function __construct(private TestResultsStorage $storage) {}
                public function notify(Passed $event): void {
                    $this->storage->setTestStatus($event->test()->id(), 'passed');
                }
            },

            new class($storage) implements FailedSubscriber {
                public function __construct(private TestResultsStorage $storage) {}
                public function notify(Failed $event): void {
                    $this->storage->setTestStatus(
                        $event->test()->id(),
                        'failed',
                        $event->throwable()->message(),
                    );
                }
            },

            new class($storage) implements ErroredSubscriber {
                public function __construct(private TestResultsStorage $storage) {}
                public function notify(Errored $event): void {
                    $this->storage->setTestStatus(
                        $event->test()->id(),
                        'error',
                        $event->throwable()->message(),
                    );
                }
            },

            new class($storage) implements SkippedSubscriber {
                public function __construct(private TestResultsStorage $storage) {}
                public function notify(Skipped $event): void {
                    $this->storage->setTestStatus(
                        $event->test()->id(),
                        'skipped',
                        $event->message(),
                    );
                }
            },

            new class($storage) implements FinishedSubscriber {
                public function __construct(private TestResultsStorage $storage) {}
                public function notify(Finished $event): void {
                    $test = $event->test();
                    [$className, $testName] = $test instanceof TestMethod
                        ? [$test->className(), $test->methodName()]
                        : ['', $test->name()];

                    $durationMs = (int) round(
                        $event->telemetryInfo()->durationSincePrevious()->asFloat() * 1000
                    );

                    $this->storage->recordFinished($test->id(), $className, $testName, $durationMs);
                }
            },

            new class($storage) implements RunnerFinishedSubscriber {
                public function __construct(private TestResultsStorage $storage) {}
                public function notify(RunnerFinished $event): void { $this->storage->finishRun(); }
            },
        );
    }
}
