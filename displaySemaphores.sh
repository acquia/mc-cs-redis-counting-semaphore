#!/bin/bash
# this command will output the name of each semaphore preceded by number of active jobs holding it and followed by job identifiers:
# > <number of lock holding jobs> <semaphore_name> <job id> <job id> <job id> ...
#
# requires netcet-openbsd (regular netcat hangs)
#
# crazy bash implementation of redis protocol done because redis-cli cannot send multiple commands at once (it waits for rtt before sending next command) making it crazy slow
# redis-cli has --pipe option that does pretty much this but doesn't return server output making it useless for us
redis_server="$1"
redis_password="$2"

jobIds=""
jobCount=0

semaphores=$(redis-cli -h "$redis_server" -a "$redis_password" --raw keys '*' | grep semaphore)
commands=$(
    echo -en "*2\r\n\$4\r\nAUTH\r\n\$${#redis_password}\r\n$redis_password\r\n"
    for sem in `echo $semaphores`; do
        # raw redis protocol for: zrange "$sem" 0 -1
        echo -en "*4\r\n\$6\r\nZRANGE\r\n\$${#sem}\r\n$sem\r\n\$1\r\n0\r\n\$2\r\n-1\r\n"
        # raw redis protocol for: echo >> $sem
        toecho=">> $sem"
        echo -en "*2\r\n\$4\r\nECHO\r\n\$${#toecho}\r\n$toecho\r\n"
    done
    echo -en "*2\r\n\$4\r\nECHO\r\n\$20\r\nREPORT_DONE_EXIT_NOW\r\n"
)
echo "$commands" | nc "$redis_server" 6379 | \
while read line; do
    line="$(echo "${line}" | tr -d '[:space:]')"
    if [[ ${line:0:1} =~ [*+\$-] ]]; then
        # redis protocol balast
        continue;
    fi
    if [[ $line == "REPORT_DONE_EXIT_NOW" ]]; then
        exit
    fi
    if [[ "${line:0:2}" == ">>" ]]; then
        echo "> $jobCount ${line:2} $jobIds"
        jobIds=""
        jobCount=0
    else
        line=$(echo "$line" | cut -f1 -d":")
        jobIds="$jobIds $line"
        jobCount=$((jobCount+1))
    fi
done
