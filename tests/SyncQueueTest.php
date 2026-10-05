<?php

declare(strict_types=1);

namespace Marko\Queue\Sync\Tests;

use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Queue\ContainerAwareJobInterface;
use Marko\Queue\Exceptions\JobFailedException;
use Marko\Queue\Job;
use Marko\Queue\JobEnvelope;
use Marko\Queue\QueueInterface;
use Marko\Queue\Sync\SyncQueue;
use Marko\Testing\Fake\FakeConfigRepository;
use RuntimeException;

function createSyncQueueJobEnvelope(): JobEnvelope
{
    return new JobEnvelope(
        new EncryptionConfig(new FakeConfigRepository(['encryption.key' => 'sync-queue-test-key'])),
    );
}

function createSyncQueue(
    ?ContainerInterface $container = null,
    ?JobEnvelope $jobEnvelope = null,
): SyncQueue {
    return new SyncQueue(
        $container ?? new Container(),
        $jobEnvelope ?? createSyncQueueJobEnvelope(),
    );
}

class ContainerAwareCaptureJob extends Job implements ContainerAwareJobInterface
{
    public ?ContainerInterface $receivedContainer = null;

    public ?JobEnvelope $receivedJobEnvelope = null;

    public bool $hadBothWhenHandled = false;

    public function setContainer(ContainerInterface $container): void
    {
        $this->receivedContainer = $container;
    }

    public function setJobEnvelope(JobEnvelope $jobEnvelope): void
    {
        $this->receivedJobEnvelope = $jobEnvelope;
    }

    public function handle(): void
    {
        $this->hadBothWhenHandled = $this->receivedContainer !== null && $this->receivedJobEnvelope !== null;
    }
}

it('gives container-aware jobs the container and job envelope before handling them', function (): void {
    $container = new Container();
    $jobEnvelope = createSyncQueueJobEnvelope();
    $job = new ContainerAwareCaptureJob();

    createSyncQueue($container, $jobEnvelope)->push($job);

    expect($job->receivedContainer)->toBe($container)
        ->and($job->receivedJobEnvelope)->toBe($jobEnvelope)
        ->and($job->hadBothWhenHandled)->toBeTrue();
});

it('implements QueueInterface', function (): void {
    $queue = createSyncQueue();

    expect($queue)->toBeInstanceOf(QueueInterface::class);
});

it('push executes job immediately', function (): void {
    $queue = createSyncQueue();
    $executed = false;

    $job = new class ($executed) extends Job
    {
        public function __construct(
            private bool &$executed,
        ) {}

        public function handle(): void
        {
            $this->executed = true;
        }
    };

    $queue->push($job);

    expect($executed)->toBeTrue();
});

it('push returns job ID', function (): void {
    $queue = createSyncQueue();

    $job = new class () extends Job
    {
        public function handle(): void {}
    };

    $id = $queue->push($job);

    expect($id)->toBeString()
        ->not->toBeEmpty()
        ->and($job->id)->toBe($id);
});

it('later executes job immediately', function (): void {
    $queue = createSyncQueue();
    $executed = false;

    $job = new class ($executed) extends Job
    {
        public function __construct(
            private bool &$executed,
        ) {}

        public function handle(): void
        {
            $this->executed = true;
        }
    };

    $id = $queue->later(60, $job);

    expect($executed)->toBeTrue()
        ->and($id)->toBeString()->not->toBeEmpty()
        ->and($job->id)->toBe($id);
});

it('pop returns null', function (): void {
    $queue = createSyncQueue();

    expect($queue->pop())->toBeNull()
        ->and($queue->pop('custom'))->toBeNull();
});

it('size returns zero', function (): void {
    $queue = createSyncQueue();

    expect($queue->size())->toBe(0)
        ->and($queue->size('custom'))->toBe(0);
});

it('clear returns zero', function (): void {
    $queue = createSyncQueue();

    expect($queue->clear())->toBe(0)
        ->and($queue->clear('custom'))->toBe(0);
});

it('push throws JobFailedException on job failure', function (): void {
    $queue = createSyncQueue();

    $job = new class () extends Job
    {
        public function handle(): void
        {
            throw new RuntimeException('Job failed');
        }
    };

    $queue->push($job);
})->throws(JobFailedException::class, 'Job failed');

it('SyncQueue handles job exceptions properly', function (): void {
    $queue = createSyncQueue();

    $job = new class () extends Job
    {
        public function handle(): void
        {
            throw new RuntimeException('Test exception message');
        }
    };

    expect(fn () => $queue->push($job))
        ->toThrow(JobFailedException::class)
        ->and(fn () => $queue->later(60, $job))
        ->toThrow(JobFailedException::class);
});
