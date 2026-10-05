<?php

declare(strict_types=1);

namespace Marko\Queue\Sync\Factory;

use Marko\Core\Container\ContainerInterface;
use Marko\Queue\JobEnvelope;
use Marko\Queue\QueueConfig;
use Marko\Queue\QueueInterface;
use Marko\Queue\Sync\SyncQueue;

readonly class SyncQueueFactory
{
    public function __construct(
        private QueueConfig $config,
        private ContainerInterface $container,
        private JobEnvelope $jobEnvelope,
    ) {}

    public function create(): QueueInterface
    {
        return new SyncQueue($this->container, $this->jobEnvelope);
    }
}
