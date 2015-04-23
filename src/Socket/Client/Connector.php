<?php
namespace Icicle\Socket\Client;

use Icicle\Loop\Loop;
use Icicle\Promise\Promise;
use Icicle\Socket\Exception\InvalidArgumentException;
use Icicle\Socket\Exception\FailureException;
use Icicle\Socket\Exception\TimeoutException;
use Icicle\Socket\ParserTrait;

class Connector implements ConnectorInterface
{
    use ParserTrait;

    const DEFAULT_CONNECT_TIMEOUT = 10;
    const DEFAULT_ALLOW_SELF_SIGNED = false;
    const DEFAULT_VERIFY_DEPTH = 10;
    const DEFAULT_PROTOCOL = 'tcp';
    
    /**
     * @inheritdoc
     */
    public function connect($host, $port, array $options = null)
    {
        $protocol = isset($options['protocol']) ? (string) $options['protocol'] : self::DEFAULT_PROTOCOL;
        $allowSelfSigned = isset($options['allow_self_signed']) ?
            (bool) $options['allow_self_signed'] :
            self::DEFAULT_ALLOW_SELF_SIGNED;
        $timeout = isset($options['timeout']) ? (float) $options['timeout'] : self::DEFAULT_CONNECT_TIMEOUT;
        $verifyDepth = isset($options['verify_depth']) ? (int) $options['verify_depth'] : self::DEFAULT_VERIFY_DEPTH;
        $cafile = isset($options['cafile']) ? (string) $options['cafile'] : null;
        $name = isset($options['name']) ? (string) $options['name'] : $this->parseAddress($host);
        
        $context = [];
        
        $context['socket'] = [];
        $context['socket']['connect'] = $this->makeName($host, $port);
        
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
        
        if (null !== $cafile) {
            if (!file_exists($cafile)) {
                return Promise::reject(new InvalidArgumentException('No file exists at path given for cafile.'));
            }
            $context['ssl']['cafile'] = $cafile;
        }

        $context = stream_context_create($context);
        
        $uri = $this->makeUri($protocol, $host, $port);
        // Error reporting suppressed since stream_socket_client() emits an E_WARNING on failure (checked below).
        $socket = @stream_socket_client(
            $uri,
            $errno,
            $errstr,
            null, // Timeout does not apply for async connect. Timeout enforced by await below.
            STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT,
            $context
        );
        
        if (!$socket || $errno) {
            return Promise::reject(new FailureException(
                sprintf('Could not connect to %s; Errno: %d; %s', $uri, $errno, $errstr)
            ));
        }
        
        return new Promise(function ($resolve, $reject) use ($socket, $timeout) {
            $await = Loop::await($socket, function ($resource, $expired) use (&$await, $resolve, $reject) {
                /** @var \Icicle\Loop\Events\SocketEventInterface $await */
                $await->free();
                
                if ($expired) {
                    $reject(new TimeoutException('Connection attempt timed out.'));
                    return;
                }

                $resolve(new Client($resource));
            });
            
            $await->listen($timeout);
        });
    }
}
