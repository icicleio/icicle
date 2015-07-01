<?php
namespace Icicle\Socket\Server;

use Exception;
use Icicle\Loop;
use Icicle\Promise;
use Icicle\Promise\Deferred;
use Icicle\Socket\Client\Client;
use Icicle\Socket\Exception\BusyError;
use Icicle\Socket\Exception\ClosedException;
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
     * @var \Icicle\Promise\Deferred
     */
    private $deferred;
    
    /**
     * @var \Icicle\Loop\Events\SocketEventInterface
     */
    private $poll;
    
    /**
     * @param resource $socket
     */
    public function __construct($socket)
    {
        parent::__construct($socket);
        
        $this->poll = $this->createPoll($socket);
        
        try {
            list($this->address, $this->port) = $this->getName(false);
        } catch (Exception $exception) {
            $this->free($exception);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function close()
    {
        $this->free();
    }

    /**
     * Frees resources associated with the server and closes the server.
     *
     * @param Exception $exception Reason for closing the server.
     */
    protected function free(Exception $exception = null)
    {
        if (null !== $this->poll) {
            $this->poll->free();
            $this->poll = null;
        }

        if (null !== $this->deferred) {
            if (null === $exception) {
                $exception = new ClosedException('The stream was unexpectedly closed.');
            }

            $this->deferred->reject($exception);
            $this->deferred = null;
        }

        parent::close();
    }
    
    /**
     * {@inheritdoc}
     */
    public function accept()
    {
        if (null !== $this->deferred) {
            return Promise\reject(new BusyError('Already waiting on server.'));
        }
        
        if (!$this->isOpen()) {
            return Promise\reject(new UnavailableException('The server has been closed.'));
        }

        $this->poll->listen();
        
        $this->deferred = new Deferred(function () {
            $this->poll->cancel();
            $this->deferred = null;
        });
        
        return $this->deferred->getPromise();
    }
    
    /**
     * {@inheritdoc}
     */
    public function getAddress()
    {
        return $this->address;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @param resource $socket Stream socket resource.
     *
     * @return \Icicle\Socket\Client\ClientInterface
     */
    protected function createClient($socket)
    {
        return new Client($socket);
    }

    /**
     * @param resource $socket Stream socket server resource.
     *
     * @return \Icicle\Loop\Events\SocketEventInterface
     */
    private function createPoll($socket)
    {
        return Loop\poll($socket, function ($resource) {
            // Error reporting suppressed since stream_socket_accept() emits E_WARNING on client accept failure.
            $client = @stream_socket_accept($resource, 0); // Timeout of 0 to be non-blocking.

            // Having difficulty finding a test to cover this scenario, but it has been seen in production.
            if (!$client) {
                $this->poll->listen(); // Accept failed, let's go around again.
                return;
            }

            try {
                $this->deferred->resolve($this->createClient($client));
            } catch (Exception $exception) {
                $this->deferred->reject($exception);
            }

            $this->deferred = null;
        });
    }
}
