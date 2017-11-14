<?php

namespace Mautic\Library\RedisLocking;

class CountingSemaphoreManager
{
    private $rclient;
    private $logger;

    public function __construct(Predis\Client $rclient, Logger $logger = null)
    {
        $this->rclient = $rclient;
        $this->logger = $logger;
    }

    public function acquireSemaphore(string $resourceName, int $resourceCapacity, string $uniqueJobId, int $timeoutSeconds): CountingSemaphore
    {
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
