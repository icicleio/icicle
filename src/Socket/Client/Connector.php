<?php
namespace Icicle\Socket\Client;

use Icicle\Loop\Loop;
use Icicle\Socket\Exception\InvalidArgumentException;
use Icicle\Socket\Exception\FailureException;
use Icicle\Socket\Exception\TimeoutException;
use Icicle\Promise\Promise;

class Connector implements ConnectorInterface
{
    const DEFAULT_CONNECT_TIMEOUT = 10;
    const DEFAULT_ALLOW_SELF_SIGNED = false;
    const DEFAULT_VERIFY_DEPTH = 10;
    const DEFAULT_PROTOCOL = 'tcp';
    
    /**
     * @inheritdoc
     */
    public function connect($host, $port, array $options = null)
    {
        if (false !== strpos($host, ':')) {
            $host = '[' . trim($host, '[]') . ']';
        }
        
        $protocol = isset($options['protocol']) ? (string) $options['protocol'] : self::DEFAULT_PROTOCOL;
        $allowSelfSigned = isset($options['allow_self_signed']) ? (bool) $options['allow_self_signed'] : self::DEFAULT_ALLOW_SELF_SIGNED;
        $timeout = isset($options['timeout']) ? (float) $options['timeout'] : self::DEFAULT_CONNECT_TIMEOUT;
        $verifyDepth = isset($options['verify_depth']) ? (int) $options['verify_depth'] : self::DEFAULT_VERIFY_DEPTH;
        $cafile = isset($options['cafile']) ? (string) $options['cafile'] : null;
        $name = isset($options['name']) ? (string) $options['name'] : (string) $host;
        
        $context = [];
        
        $context['socket'] = [];
        $context['socket']['connect'] = "{$host}:{$port}";
        
        $context['ssl'] = [];
        $context['ssl']['capture_peer_cert'] = true;
        $context['ssl']['capture_peer_chain'] = true;
        $context['ssl']['capture_peer_cert_chain'] = true;
        
        $context['ssl']['verify_peer'] = true;
        $context['ssl']['allow_self_signed'] = $allowSelfSigned;
        $context['ssl']['verify_depth'] = $verifyDepth;
        
        $context['ssl']['CN_match'] = $name;
        $context['ssl']['peer_name'] = $name;
        $context['ssl']['disable_compression'] = true;
        
        // @codeCoverageIgnoreStart
        if (null !== $cafile) {
            if (!file_exists($cafile)) {
                return Promise::reject(new InvalidArgumentException('No file exists at path given for cafile.'));
            }
            $context['ssl']['cafile'] = $cafile;
        } // @codeCoverageIgnoreEnd
        
        $context = stream_context_create($context);
        
        $uri = sprintf('%s://%s:%d', $protocol, $host, $port);
        $socket = @stream_socket_client($uri, $errno, $errstr, null, STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT, $context);
        
        if (!$socket || $errno) {
            return Promise::reject(new FailureException("Could not connect to {$uri}; Errno: {$errno}; {$errstr}"));
        }
        
        return new Promise(function ($resolve, $reject) use ($socket, $timeout) {
            $await = Loop::await($socket, function ($resource, $expired) use (&$await, $resolve, $reject) {
                $await->free();
                
                if ($expired) {
                    $reject(new TimeoutException('Connection attempt timed out.'));
                } else {
                    $resolve(new Client($resource));
                }
            });
            
            $await->listen($timeout);
        });
    }
}
