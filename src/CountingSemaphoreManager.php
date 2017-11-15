<?php

namespace Mautic\Library\RedisLocking;

class CountingSemaphoreManager
{
    private $rclient;
    private $logger;

    public function __construct(\Predis\Client $rclient, \Monolog\Logger $logger = null)
    {
        $this->rclient = $rclient;
        $this->logger = $logger;
    }

    // The timeout is used to cleanup the whole semaphore, not just this single lock !
    public function acquireSemaphore(string $resourceName, int $resourceCapacity, string $jobId, int $timeoutSeconds)
    {
        $uniqueJobId = uniqid($jobId.':', true);
        try
        {
            return new CountingSemaphore($this->rclient, $resourceName, $resourceCapacity, $uniqueJobId, $timeoutSeconds, $this->logger);
        }
        catch (SemaphoreFullException $e)
        {
            return null;
        }
    }
}
