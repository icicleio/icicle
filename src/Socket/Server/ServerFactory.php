<?php
namespace Icicle\Socket\Server;

use Icicle\Socket\Exception\InvalidArgumentException;
use Icicle\Socket\Exception\FailureException;
use Icicle\Socket\ParserTrait;

class ServerFactory implements ServerFactoryInterface
{
    use ParserTrait;

    const DEFAULT_BACKLOG = SOMAXCONN;
    
    /**
     * @inheritdoc
     */
    public function create($host, $port, array $options = null)
    {
        $queue = isset($options['backlog']) ? (int) $options['backlog'] : self::DEFAULT_BACKLOG;
        $pem = isset($options['pem']) ? (string) $options['pem'] : null;
        $passphrase = isset($options['passphrase']) ? (string) $options['passphrase'] : null;
        $name = isset($options['name']) ? (string) $options['name'] : $this->parseAddress($host);
        
        $context = [];
        
        $context['socket'] = [];
        $context['socket']['bindto'] = $this->makeName($host, $port);
        $context['socket']['backlog'] = $queue;
        
        if (null !== $pem) {
            if (!file_exists($pem)) {
                throw new InvalidArgumentException('No file found at given PEM path.');
            }
            
            $context['ssl'] = [];
            $context['ssl']['local_cert'] = $pem;
            $context['ssl']['disable_compression'] = true;
            $context['ssl']['SNI_enabled'] = true;
            $context['ssl']['SNI_server_name'] = $name;
            $context['ssl']['peer_name'] = $name;
            
            if (null !== $passphrase) {
                $context['ssl']['passphrase'] = $passphrase;
            }
        }
        
        $context = stream_context_create($context);
        
        $uri = $this->makeUri('tcp', $host, $port);
        // Error reporting suppressed since stream_socket_server() emits an E_WARNING on failure (checked below).
        $socket = @stream_socket_server($uri, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
        
        if (!$socket || $errno) {
            throw new FailureException("Could not create server {$uri}: [Errno: {$errno}] {$errstr}");
        }
        
        return new Server($socket);
    }
}
