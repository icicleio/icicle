<?php
namespace Icicle\Socket\Datagram;

use Icicle\Socket\Exception\FailureException;

class DatagramFactory implements DatagramFactoryInterface
{
    /**
     * @inheritdoc
     */
    public static function create($host, $port, array $options = [])
    {
        if (false !== strpos($host, ':')) { // IPv6 address
            $host = '[' . trim($host, '[]') . ']';
        }
        
        $context = [];
        
        $context['socket'] = [];
        $context['socket']['bindto'] = "{$host}:{$port}";
        
        $context = stream_context_create($context);
        
        $uri = sprintf('udp://%s:%d', $host, $port);
        $socket = @stream_socket_server($uri, $errno, $errstr, STREAM_SERVER_BIND, $context);
        
        if (!$socket || $errno) {
            throw new FailureException("Could not create datagram on {$host}:{$port}: [Errno: {$errno}] {$errstr}");
        }
        
        return new Datagram($socket);
    }
}
