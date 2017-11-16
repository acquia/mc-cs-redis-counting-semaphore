<?php
use Mautic\Library\RedisLocking\CountingSemaphoreManager;
use Mautic\Library\RedisLocking\CountingSemaphore;

require './vendor/autoload.php';
define("PORT", "8081");
define("ADDRESS", "127.0.0.1");
define("ROUNDS", 100000);

function server()
{
    if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
        echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
        exit();
    }
    if (socket_bind($sock, ADDRESS, PORT) === false) {
        echo "socket_bind() failed: reason: " . socket_strerror(socket_last_error($sock)) . "\n";
        exit();
    }
    if (socket_listen($sock, 5) === false) {
        echo "socket_listen() failed: reason: " . socket_strerror(socket_last_error($sock)) . "\n";
    }
    socket_set_nonblock($sock);
    echo "listening for new connection".PHP_EOL;
    $connected_clients = [];
    $total_con_count = 0;
    $work_in_progress = [];
    $total_work_count = 0;
    $maxwip = 0;
    $iteration = 0;

    while (true)
    {
        $clientsock = socket_accept($sock);
        if($clientsock !== false){

            socket_set_nonblock($clientsock);
            $connected_clients[] = $clientsock;
            $total_con_count++;

            $msg = "OK\n";
            socket_write($clientsock, $msg, strlen($msg));
        }
        for($i=0;$i<count($connected_clients);$i++)
        {
            if(get_resource_type($connected_clients[$i]) !== "Socket")
            {   
                array_splice($connected_clients, $i, 1);
                $i--;
                die("Unclean disconnect!\n");
            }
            if (false === $buffl = socket_read($connected_clients[$i], 2048)) {
                continue;
            }
            $host = "";
            $port = "";
            socket_getpeername($connected_clients[$i], $host, $port);
            $client = $host.":".$port;
            $buffarr = explode("\n",$buffl);
            foreach($buffarr as $buff)
            {
                $buff = trim($buff);

                if ($buff == 'quit') {
                    socket_close($connected_clients[$i]);
                    array_splice($connected_clients, $i, 1);
                    $i--;
                    continue;
                }
                if ($buff == 'work') {
                    if(isset($work_in_progress[$client])) {
                        die("Error. Starting work twice from client $client\n");
                    }
                    $work_in_progress[$client] = true;
                    $msg = "OK\n";
                    socket_write($connected_clients[$i], $msg, strlen($msg));

                    continue;
                }
                if ($buff == 'done') {
                    if($work_in_progress[$client]) {
                        unset($work_in_progress[$client]);
                        $total_work_count++;
                    }
                    else
                    {
                        die("Error. Starting work twice from client $client\n");
                    }
                    $msg = "OK\n";
                    socket_write($connected_clients[$i], $msg, strlen($msg));
                    continue;
                }
            }
        }
        $wip = count($work_in_progress);
        if($wip > $maxwip)
        {
            $maxwip = $wip;
        }
        $iteration++;
        if($iteration > 10000)
        {
            echo count($connected_clients)." ($total_con_count) ".count($work_in_progress)." (max: $maxwip total: $total_work_count)\n";
            $iteration = 0;
        }
        usleep(100);
        }
}

function client($capacity, $id)
{
    $rurl = isset($_ENV["REDIS_SERVER_URL"]) ? $_ENV["REDIS_SERVER_URL"] : 'tcp://localhost:6379';

    $rclient = new Predis\Client($rurl);
    $manager = new CountingSemaphoreManager($rclient);
    
    $client = stream_socket_client("tcp://".ADDRESS.":".PORT, $errno, $errstr, 2);
    if(!$client) {
        exit();
    }

    for($i=0;$i<ROUNDS;$i++)
    {
        $sem = $manager->acquireSemaphore('stresslock', $capacity, $id."-".$i, 10);
        if(!$sem)
        {
            usleep(100);
            $i--;
            continue;
        }
        if(!fwrite($client, "work\n"))
        {
            die("connection broken");
        }
        if(fgets($client, 1024) !== "OK\n") {
            die("server did not confirm");
        }
        fwrite($client, "done\n");
        if(fgets($client, 1024) !== "OK\n") {
            die("server did not confirm");
        }
        unset($sem);
    }
    fwrite($client, "quit\n");
    while (!feof($client)) {
        fgets($client, 1024);
    }
    fclose($client);
}

switch ($argv[1])
{
    case "server":
        server();
        break;

    case "client":
        client($argv[2], $argv[3]);
        break;

    default:
        "Unknown command. Usage: ./stress-test.php <client / server> <semaphore capacity>";
        break;
}
