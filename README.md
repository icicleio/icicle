Icicle
======

# Example

Some example code using coroutines to create an asynchronous echo server.

    use Icicle\Coroutine\Coroutine;
    use Icicle\Loop\Loop;
    use Icicle\Socket\Client;
    use Icicle\Socket\Server;
    
    $server = Server::create('localhost', 8080);
    
    $coroutine = Coroutine::async(function (Server $server) {
        $coroutine = Coroutine::async(function (Client $client) {
            try {
                echo "Accepted client.\n";
                
                while ($client->isReadable()) {
                    $data = (yield $client->read());
                    
                    echo "Read {$data} from client.\n";
                    
                    yield $client->write();
                    
                    if ("exit\r\n" === $data) {
                        $client->close();
                    }
                }
            } catch (Exception $e) {
                $client->close();
            }
        });
        
        while ($server->isOpen()) {
            try {
                $coroutine(yield $server->accept());
            } catch (Exception $e) {
                echo "Error accepting client: {$e->getMessage()}\n";
            }
        }
    });

    Loop::run();
