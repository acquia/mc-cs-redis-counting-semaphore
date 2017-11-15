<?php

namespace Mautic\Library\RedisLocking;

/*
 * Implementation of redis counting semaphore following
 * https://redislabs.com/ebook/part-3-next-steps/chapter-11-scripting-redis-with-lua/11-2-rewriting-locks-and-semaphores-with-lua/
 *
 * Should be fair and without race conditions as long as all system clocks are synchronized to 1s precision.
 *
 */

class CountingSemaphore
{
    private $rclient;
    private $logger;
    private $uniqueJobId;
    private $timeoutSeconds;
    private $acquired;
    private $resourceName;
    private $resourceCapacity;

    public function __construct(\Predis\Client $rclient, string $resourceName, int $resourceCapacity, string $uniqueJobId, int $timeoutSeconds, \Monolog\Logger $logger = null) 
    {
        $this->rclient = $rclient;
        $this->logger = $logger;
        $this->resourceName = 'semaphore:'.$resourceName;
        $this->resourceCapacity = $resourceCapacity;
        $this->uniqueJobId = $uniqueJobId;
        $this->timeoutSeconds = $timeoutSeconds;
        $this->acquired = false;
    
        // register custom lua commands found in RedisCommands.php
        $rclient->getProfile()->defineCommand('acquiresemaphore', '\\Mautic\\Library\\RedisLocking\\RedisAcquireSemaphore');
        $rclient->getProfile()->defineCommand('refreshsemaphore', '\\Mautic\\Library\\RedisLocking\\RedisRefreshSemaphore');

        $this->acquire();
    }

    public function __destruct()
    {
        if ($this->acquired)
        {
            $this->release();
        }
    }

    private function acquire()
    {
        $unixTime = time();

        $ret = $this->rclient->acquiresemaphore(
            $this->resourceName,
            $unixTime - $this->timeoutSeconds,
            $this->resourceCapacity,
            $unixTime,
            $this->uniqueJobId
        );

        if($ret != $this->uniqueJobId) {
            throw new SemaphoreFullException();
        }

        $this->acquired = true;
    }

    public function refresh()
    {
        $unixTime = time();

        $ret = $this->rclient->refreshsemaphore(
            $this->resourceName,
            $this->uniqueJobId,
            $unixTime
        );

        if($ret !== 0) {
            $this->acquired = false;
            throw new SemaphoreLostException();
        }
    }

    public function release()
    {
        $ret = $this->rclient->zrem($this->resourceName, $this->uniqueJobId);
        $this->acquired = false;

        if($ret !== 1) {
            // throw here to point out that the semaphore was lost but the application
            // clearly did not know (and attempted to release it)
            throw new SemaphoreLostException();
        }
    }

    // Does not check server-side! Returns last known state.
    public function isAcquired()
    {
        return $this->acquired;
    }
    public function getJobId()
    {
        return $this->uniqueJobId;
    }
}
