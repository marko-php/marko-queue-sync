<?php

declare(strict_types=1);

namespace Marko\Queue\Sync;

use Marko\Core\Container\ContainerInterface;
use Marko\Queue\ContainerAwareJobInterface;
use Marko\Queue\Exceptions\JobFailedException;
use Marko\Queue\JobEnvelope;
use Marko\Queue\JobInterface;
use Marko\Queue\QueueInterface;
use Random\RandomException;
use Throwable;

readonly class SyncQueue implements QueueInterface
{
    public function __construct(
        private ContainerInterface $container,
        private JobEnvelope $jobEnvelope,
    ) {}

    /**
     * Run the job immediately. Container-aware jobs (such as AsyncObserverJob)
     * get the container and job envelope first, exactly as the Worker gives them,
     * and release them once handle() returns or throws.
     *
     * @throws JobFailedException|RandomException
     */
    public function push(
        JobInterface $job,
        ?string $queue = null,
    ): string {
        $id = bin2hex(random_bytes(16));
        $job->setId($id);
        $job->incrementAttempts();

        if ($job instanceof ContainerAwareJobInterface) {
            $job->setContainer($this->container);
            $job->setJobEnvelope($this->jobEnvelope);
        }

        try {
            $job->handle();
        } catch (Throwable $e) {
            throw JobFailedException::fromException($job::class, $e);
        } finally {
            if ($job instanceof ContainerAwareJobInterface) {
                $job->releaseContainer();
            }
        }

        return $id;
    }

    /**
     * @throws JobFailedException|RandomException
     */
    public function later(
        int $delay,
        JobInterface $job,
        ?string $queue = null,
    ): string {
        return $this->push($job, $queue);
    }

    public function pop(
        ?string $queue = null,
    ): ?JobInterface {
        return null;
    }

    public function size(
        ?string $queue = null,
    ): int {
        return 0;
    }

    public function clear(
        ?string $queue = null,
    ): int {
        return 0;
    }

    public function delete(
        string $jobId,
    ): bool {
        return true;
    }

    public function release(
        string $jobId,
        int $delay = 0,
    ): bool {
        return true;
    }
}
