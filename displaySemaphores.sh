#!/bin/bash

redis_server="$1"
redis_password="$2"

jobIds=""
jobCount=0

semaphores=$(redis-cli -h "$redis_server" -a "$redis_password" --raw keys '*' | grep semaphore)
for sem in `echo $semaphores`; do
    echo zrange "$sem" 0 -1
    echo "echo '>> $sem'"
done | redis-cli -h "$redis_server" -a "$redis_password" --raw | \
while read line; do
    header=${line:0:2}
    if [[ "$header" == ">>" ]]; then
        echo "> $jobCount ${line:3} $jobIds"
        jobIds=""
        jobCount=0
    else
        line=$(echo "$line" | cut -f1 -d":")
        jobIds="$jobIds $line"
        jobCount=$((jobCount+1))
    fi
done
