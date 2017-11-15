<?php
use PHPUnit\Framework\TestCase;

use Mautic\Library\RedisLocking\CountingSemaphoreManager;
use Mautic\Library\RedisLocking\CountingSemaphore;
use Mautic\Library\RedisLocking\SemaphoreLostException;

class CountingSemaphoreTest extends TestCase
{
    private $manager;
    private $rclient;

    public function __construct()
    {
        parent::__construct();

        $rurl = isset($_ENV["REDIS_SERVER_URL"]) ? $_ENV["REDIS_SERVER_URL"] : 'tcp://localhost:6379';

        $this->rclient = new Predis\Client($rurl);
        $this->manager = new CountingSemaphoreManager($this->rclient);
    }
    public function testSemaphoreAcquire()
    {
        $starttime = time();
        $sem = $this->manager->acquireSemaphore('testlock', 1, 'first-test-job', 10);
        $endtime = time();

        $this->assertInstanceOf(CountingSemaphore::class, $sem);
        $this->assertTrue($sem->isAcquired());

        $score = (int) $this->rclient->zscore('semaphore:testlock', $sem->getJobId());

        $this->assertGreaterThanOrEqual($score, $starttime);
        $this->assertGreaterThanOrEqual($endtime, $score);

    }
    
    public function testSemaphoreAcquireFailure()
    {
        $sem1 = $this->manager->acquireSemaphore('testlock2', 2, 'first', 10);
        $sem2 = $this->manager->acquireSemaphore('testlock2', 2, 'second', 10);
        $sem3 = $this->manager->acquireSemaphore('testlock2', 2, 'third', 10);

        $this->assertInstanceOf(CountingSemaphore::class, $sem1);
        $this->assertInstanceOf(CountingSemaphore::class, $sem2);
        $this->assertNull($sem3);
    }
    
    public function testSemaphoreRelease()
    {
        // either release() or destruction or timeout should release the semaphore
        $sem1 = $this->manager->acquireSemaphore('testlock3', 2, 'first', 2);
        $sem2 = $this->manager->acquireSemaphore('testlock3', 2, 'second', 2);
        $this->assertInstanceOf(CountingSemaphore::class, $sem1);
        $this->assertInstanceOf(CountingSemaphore::class, $sem2);
        $sem1->release();
        $sem3 = $this->manager->acquireSemaphore('testlock3', 2, 'third', 2);
        unset($sem2);
        $this->assertInstanceOf(CountingSemaphore::class, $sem3);
        $sem4 = $this->manager->acquireSemaphore('testlock3', 2, 'fourth', 2);
        $this->assertInstanceOf(CountingSemaphore::class, $sem4);
        sleep(2);
        $sem5 = $this->manager->acquireSemaphore('testlock3', 2, 'fifth', 2);
        $this->assertInstanceOf(CountingSemaphore::class, $sem5);

        // two semaphores will timeout and complain
        $this->expectException(SemaphoreLostException::class);
    }
    
    public function testSemaphoreRefresh()
    {
        $sem1 = $this->manager->acquireSemaphore('testlock4', 2, 'first', 2);
        $sem2 = $this->manager->acquireSemaphore('testlock4', 2, 'second', 2);
        sleep(1);
        $sem1->refresh();
        sleep(1);
        $sem1->refresh();
        $sem3 = $this->manager->acquireSemaphore('testlock4', 2, 'third', 2);

        $thrown = false;
        try
        {
            $sem2->refresh();
            $this->fail('Should have thrown SemaphoreLostException.');
        }
        catch (SemaphoreLostException $e) {
            $thrown = true;
        }
        $this->assertTrue($thrown);
    }
}
