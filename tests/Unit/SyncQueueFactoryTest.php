<?php

declare(strict_types=1);

namespace Marko\Queue\Sync\Tests\Unit;

use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Queue\ContainerAwareJobInterface;
use Marko\Queue\Job;
use Marko\Queue\JobEnvelope;
use Marko\Queue\QueueConfig;
use Marko\Queue\QueueInterface;
use Marko\Queue\Sync\Factory\SyncQueueFactory;
use Marko\Queue\Sync\SyncQueue;
use Marko\Testing\Fake\FakeConfigRepository;

it('uses FakeConfigRepository in SyncQueueFactoryTest', function (): void {
    $config = new FakeConfigRepository(['queue.driver' => 'sync']);

    expect($config)->toBeInstanceOf(FakeConfigRepository::class);
});

it('SyncQueueFactory creates configured queue', function (): void {
    $config = createQueueConfigMock();

    $factory = new SyncQueueFactory($config, new Container(), createFactoryJobEnvelope());
    $queue = $factory->create();

    expect($queue)->toBeInstanceOf(QueueInterface::class)
        ->and($queue)->toBeInstanceOf(SyncQueue::class);
});

it('builds a SyncQueue with the container and job envelope from SyncQueueFactory', function (): void {
    $container = new Container();
    $jobEnvelope = createFactoryJobEnvelope();
    $job = new FactoryContainerAwareJob();

    new SyncQueueFactory(createQueueConfigMock(), $container, $jobEnvelope)->create()->push($job);

    expect($job->receivedContainer)->toBe($container)
        ->and($job->receivedJobEnvelope)->toBe($jobEnvelope);
});

class FactoryContainerAwareJob extends Job implements ContainerAwareJobInterface
{
    public ?ContainerInterface $receivedContainer = null;

    public ?JobEnvelope $receivedJobEnvelope = null;

    public function setContainer(ContainerInterface $container): void
    {
        $this->receivedContainer = $container;
    }

    public function setJobEnvelope(JobEnvelope $jobEnvelope): void
    {
        $this->receivedJobEnvelope = $jobEnvelope;
    }

    public function handle(): void {}
}

function createQueueConfigMock(
    string $driver = 'sync',
    string $queue = 'default',
): QueueConfig {
    $repository = new FakeConfigRepository([
        'queue.driver' => $driver,
        'queue.queue' => $queue,
        'queue.connection' => 'default',
        'queue.retry_after' => 90,
        'queue.max_attempts' => 3,
    ]);

    return new QueueConfig($repository);
}

function createFactoryJobEnvelope(): JobEnvelope
{
    return new JobEnvelope(
        new EncryptionConfig(new FakeConfigRepository(['encryption.key' => 'sync-factory-test-key'])),
    );
}
