Implementation of redis counting semaphore following
https://redislabs.com/ebook/part-3-next-steps/chapter-11-scripting-redis-with-lua/11-2-rewriting-locks-and-semaphores-with-lua/

Should be fair and without race conditions as long as all system clocks are synchronized to 1s precision.

When using this implementation you have to be careful to always release your semaphores on time (either by `$sem->release()` or `unset($sem)`). Releasing expired semaphore is considered a failure and raises an exception.

Run tests with 'composer test'. You have to have redis server accessible. You can set it's url in env variable `REDIS_SERVER_URL`. Default is  'tcp://localhost:6379'

stress-test.php contains some basic stress testing checks. You can run it like this:
1st terminal:
`php stress-test.php server`

2nd terminal:
`php stress-test.php client 4 1 & php stress-test.php client 4 2 & php stress-test.php client 4 3 & php stress-test.php client 4 4 & php stress-test.php client 4 5 & php stress-test.php client 4 6 & php stress-test.php client 4 7 & php stress-test.php client 4 8`
