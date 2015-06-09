<?php
namespace Icicle\Socket\Datagram;

use Icicle\Socket\Exception\FailureException;
use Icicle\Socket\ParserTrait;

class DatagramFactory implements DatagramFactoryInterface
{
    use ParserTrait;

    /**
     * {@inheritdoc}
     */
    public function create($host, $port, array $options = null)
    {
        $context = [];
        
        $context['socket'] = [];
        $context['socket']['bindto'] = $this->makeName($host, $port);
        
        $context = stream_context_create($context);
        
        $uri = $this->makeUri('udp', $host, $port);
        // Error reporting suppressed since stream_socket_server() emits an E_WARNING on failure (checked below).
        $socket = @stream_socket_server($uri, $errno, $errstr, STREAM_SERVER_BIND, $context);
        
        if (!$socket || $errno) {
            throw new FailureException(
                sprintf('Could not create datagram on %s: Errno: %d; %s', $uri, $errno, $errstr)
            );
        }
        
        return new Datagram($socket);
    }
}
