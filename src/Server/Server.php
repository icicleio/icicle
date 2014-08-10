<?php
namespace Icicle\Server;

use Exception;

use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;

use Icicle\Loop\LoopInterface;
use Icicle\Loop\LoopFactory;
use Icicle\Server\Exception\ServerException;
use Icicle\Socket\Client as SocketClient;
//use Icicle\Socket\Exception\SocketException;
use Icicle\Socket\Server as SocketServer;

class Server implements ServerInterface
{
    use EventEmitterTrait;
    
    /**
     * @var     Icicle\Socket\Server[]
     */
    private $servers;
    
    /**
     * @var     Icicle\Loop\LoopInterface
     */
    private $loop;
    
    /**
     * @var     callable
     */
    private $onConnection;
    
    /**
     * @var     callable
     */
    private $onClose;
    
    /**
     * @var     callable
     */
    private $onError;
    
    /**
     * @param   LoopInterface|null $loop If none is provided, the best available loop type is automatically generated.
     */
    public function __construct(LoopInterface $loop = null)
    {
        if (null === $loop) {
            $this->loop = LoopFactory::create();
        } else {
            $this->loop = $loop;
        }
        
        $this->onConnection = $this->createOnConnectionCallback();
        $this->onClose = $this->createOnCloseCallback();
        $this->onError = $this->createOnErrorCallback();
    }
    
    /**
     * @param   int $port
     * @param   string $host
     */
    public function listen($port, $host = self::DEFAULT_HOST)
    {
        $server = SocketServer::create($this->loop, $host, $port);
        
        $server->on('connection', $this->onConnection);
        $server->on('close', $this->onClose);
        $server->on('error', $this->onError);
        
        $this->servers[] = $server;
    }
    
    /**
     * @return  callable function (SocketServer $server)
     */
    protected function createOnConnectionCallback()
    {
        return function (SocketServer $server) {
            $client = $server->accept();
            
            if ($client instanceof SocketClient) {
                $this->emit('connection', [$this, $client, $server, $this->loop]);
            }
        };
    }
    
    /**
     * @return  callable function (SocketServer $server)
     */
    protected function createOnCloseCallback()
    {
        return function (SocketServer $server) {
            if ($this->isRunning()) {
                $this->stop();
            }
            
            $this->emit('error', [$this, new ServerException("Server {$server->getHost()}:{$server->getPort()} unexpectedly closed!")]);
        };
    }
    
    /**
     * @return  callable function (SocketServer $server, Exception $exception)
     */
    protected function createOnErrorCallback()
    {
        return function (SocketServer $server, Exception $exception) {
            $this->emit('error', [$this, $exception]);
        };
    }
    
    public function run()
    {
        $this->loop->run();
    }
    
    public function isRunning()
    {
        return $this->loop->isRunning();
    }
    
    public function stop()
    {
        $this->loop->stop();
    }
}
