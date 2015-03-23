<?php
namespace Icicle\Socket\Server;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\Deferred;
use Icicle\Promise\Promise;
use Icicle\Socket\Client\Client;
use Icicle\Socket\Exception\AcceptException;
use Icicle\Socket\Exception\BusyException;
use Icicle\Socket\Exception\ClosedException;
use Icicle\Socket\Exception\TimeoutException;
use Icicle\Socket\Exception\UnavailableException;
use Icicle\Socket\Socket;

class Server extends Socket implements ServerInterface
{
    /**
     * Listening hostname or IP address.
     *
     * @var int
     */
    private $address;
    
    /**
     * Listening port.
     *
     * @var int
     */
    private $port;
    
    /**
     * @var Deferred
     */
    private $deferred;
    
    /**
     * @var PollInterface
     */
    private $poll;
    
    /**
     * @param   resource $socket
     */
    public function __construct($socket)
    {
        parent::__construct($socket);
        
        $this->poll = $this->createPoll($socket);
        
        try {
            list($this->address, $this->port) = self::parseSocketName($socket, false);
        } catch (Exception $exception) {
            $this->close($exception);
        }
    }
    
    /**
     * @inheritdoc
     *
     * @param   Exception|null $exception Reason for closing.
     */
    public function close(Exception $exception = null)
    {
        $this->poll->free();
        
        if (null !== $this->deferred) {
            if (null === $exception) {
                $exception = new ClosedException('The server has closed.');
            }
            
            $this->deferred->reject($exception);
            $this->deferred = null;
        }
        
        parent::close();
    }
    
    /**
     * Accepts incoming client connections.
     *
     * @param   int|float|null $timeout
     *
     * @return  PromiseInterface
     */
    public function accept($timeout = null)
    {
        if (null !== $this->deferred) {
            return Promise::reject(new BusyException('Already waiting on server.'));
        }
        
        if (!$this->isOpen()) {
            return Promise::reject(new UnavailableException('The server has been closed.'));
        }
        
        $this->poll->listen($timeout);
        
        $this->deferred = new Deferred(function () {
            $this->poll->cancel();
            $this->deferred = null;
        });
        
        return $this->deferred->getPromise();
    }
    
    /**
     * Returns the IP address on which the server is listening.
     *
     * @return  string
     */
    public function getAddress()
    {
        return $this->address;
    }
    
    /**
     * Returns the port on which the server is listening.
     *
     * @return  int
     */
    public function getPort()
    {
        return $this->port;
    }
    
    /**
     * @param   resource $socket Stream socket resource.
     *
     * @return  ClientInterface
     *
     * @throws  FailureException If creating the client fails.
     */
    protected function createClient($socket)
    {
        return new Client($socket);
    }
    
    /**
     * @param   resource $socket Stream socket server resource.
     *
     * @return  PollInterface
     */
    protected function createPoll($socket)
    {
        return Loop::poll($socket, function ($resource, $expired) {
            if ($expired) {
                $this->close(new TimeoutException('Client accept timed out.'));
                return;
            }
            
            // Error reporting suppressed since stream_socket_accept() emits E_WARNING on client accept failure.
            $client = @stream_socket_accept($resource, 0); // Timeout of 0 to be non-blocking.
            
            // Having difficultly finding a test to cover this scenario, but it has been seen in production.
            // @codeCoverageIgnoreStart
            if (!$client) {
                $message = 'Could not accept client.';
                $error = error_get_last();
                if (null !== $error) {
                    $message .= " Errno: {$error['type']}; {$error['message']}";
                }
                $this->deferred->reject(new AcceptException($message));
                $this->deferred = null;
                return;
            } // @codeCoverageIgnoreEnd
            
            try {
                $client = $this->createClient($client);
            } catch (Exception $exception) {
                $this->deferred->reject($exception);
                $this->deferred = null;
                return;
            }
            
            $this->deferred->resolve($client);
            $this->deferred = null;
        });
    }
}
