<?php

namespace Mautic\Library\RedisLocking;

class RedisAcquireSemaphore extends Predis\Command\ScriptCommand
{
    public function getKeysCount()
    {
        return 1;
    }

    public function getScript()
    {
        /*
         *  Lua keys: name of the resource (redis key to operate on)
         *  Lua args:
         *      - latest timestamp that should be expired (now - ttl)
         *      - semaphore counting limit (how many locks at the same time we should allow)
         *      - current timestamp (now)
         *      - our unique job identifier
         *
         *  First we clear out expired locks, then check if there is an open space in the semaphore
         *  If it is we add ourselves to the zset
         *
         *  Script is executed atomically.
         */
        return <<<'LUA'
redis.call('zremrangebyscore', KEYS[1], '-inf', ARGV[1])
if redis.call('zcard', KEYS[1]) < tonumber(ARGV[2]) then
    redis.call('zadd', KEYS[1], ARGV[3], ARGV[4])
    return ARGV[4]
end
LUA;
    }
}

class RedisRefreshSemaphore extends Predis\Command\ScriptCommand
{
    public function getKeysCount()
    {
        return 1;
    }

    public function getScript()
    {
        /*
         *  Lua keys: name of the resource (redis key to operate on)
         *  Lua args:
         *      - our unique job identifier
         *      - current timestamp (now)
         *
         *  First check if we still hold the lock. If so refresh the timestamp.
         */
        return <<<'LUA'
if redis.call('zscore', KEYS[1], ARGV[1]) then
    return redis.call('zadd', KEYS[1], ARGV[2], ARGV[1]) or true
end
LUA;
    }
}
