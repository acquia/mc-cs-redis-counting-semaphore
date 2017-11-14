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
    private $name;
    private $uniqueClientId;
    private $timeoutSeconds;
    private $capacity;
    private $acquired;

    public function __construct(Predis\Client $rclient, string $resourceName, int $resourceCapacity, string $uniqueJobId, int $timeoutSeconds, Logger $logger = null) 
    {
        $this->rclient = $rclient;
        $this->logger = $logger;
        $this->resourceName = 'semaphore:'.$resourceName;
        $this->resourceCapacity = $resourceCapacity;
        $this->uniqueJobId = $uniqueJobId;
        $this->timeoutSeconds = $timeoutSeconds;
        $this->acquired = false;
    
        // register custom lua commands found in RedisCommands.php
        $rclient->getProfile()->defineCommand('acquiresemaphore', 'RedisAcquireSemaphore');
        $rclient->getProfile()->defineCommand('refreshsemaphore', 'RedisRefreshSemaphore');

        $this->acquire();
    }

    public function __destruct()
    {
        if ($acquired)
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

        //TODO: check return value

        $this->acquired = true;
    }

    public function refresh()
    {

    }

    public function release()
    {
        conn.zrem(semname, identifier)
    }
}
