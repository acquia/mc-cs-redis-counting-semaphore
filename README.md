Implementation of redis counting semaphore following
https://redislabs.com/ebook/part-3-next-steps/chapter-11-scripting-redis-with-lua/11-2-rewriting-locks-and-semaphores-with-lua/

Should be fair and without race conditions as long as all system clocks are synchronized to 1s precision.

When using this implementation you have to be careful to always release your semaphores on time (either by $sem->release() or unse($sem)). Releasing expired semaphore is considered a failure and raises an exception.
