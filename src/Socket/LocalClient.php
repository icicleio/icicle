<?php
namespace Icicle\Socket;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\Promise;
use Icicle\Socket\Exception\FailureException;
use Icicle\Socket\Exception\InvalidArgumentException;

class LocalClient extends Client
{
    const DEFAULT_CONNECT_TIMEOUT = 30;
    const DEFAULT_ALLOW_SELF_SIGNED = false;
    const DEFAULT_VERIFY_DEPTH = 10;
    const DEFAULT_PROTOCOL = 'tcp';
    
    /**
     * @var int
     */
    private $crypto = 0;
    
    /**
     * @param   string $host Hostname or IP address.
     * @param   int $port Port number.
     * @param   array $options
     *
     * @return  PromiseInterface Fulfilled with a LocalClient object once the connection is established.
     *
     * @resolve LocalClient
     *
     * @reject  FailureException If connecting fails.
     * @reject  InvalidArgumentException If a CA file does not exist at the path given.
     */
    public static function connect($host, $port, array $options = null)
    {
        if (false !== strpos($host, ':')) {
            $host = '[' . trim($host, '[]') . ']';
        }
        
        $protocol = isset($options['protocol']) ? (string) $options['protocol'] : self::DEFAULT_PROTOCOL;
        $allowSelfSigned = isset($options['allow_self_signed']) ? (bool) $options['allow_self_signed'] : self::DEFAULT_ALLOW_SELF_SIGNED;
        $timeout = isset($options['timeout']) ? (float) $options['timeout'] : self::DEFAULT_CONNECT_TIMEOUT;
        $verifyDepth = isset($options['verify_depth']) ? (int) $options['verify_depth'] : self::DEFAULT_VERIFY_DEPTH;
        $cafile = isset($options['cafile']) ? (string) $options['cafile'] : null;
        $cn = isset($options['cn']) ? (string) $options['cn'] : (string) $host;
        
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
        
        $context['ssl']['CN_match'] = $cn;
        $context['ssl']['disable_compression'] = true;
        
        if (null !== $cafile) {
            if (!file_exists($cafile)) {
                return Promise::reject(new InvalidArgumentException('No file exists at path given for cafile.'));
            }
            
            $context['ssl']['cafile'] = $cafile;
        }
        
        $context = stream_context_create($context);
        
        $uri = sprintf('%s://%s:%d', $protocol, $host, $port);
        $socket = @stream_socket_client($uri, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT, $context);
        
        if (!$socket || $errno) {
            return Promise::reject(new FailureException("Could not connect to {$uri}; Errno: {$errno}; {$errstr}"));
        }
        
        return new Promise(function ($resolve, $reject) use ($socket, $timeout) {
            $await = Loop::await($socket, function ($resource, $expired) use (&$await, $resolve, $reject) {
                $await->free();
                
                if ($expired) {
                    $reject(new FailureException('Connection attempt timed out.'));
                } else {
                    $resolve(new static($resource));
                }
            });
            
            $await->listen($timeout);
        });
    }
    
    /**
     * @param   int $method One of the server crypto flags, e.g. STREAM_CRYPTO_METHOD_TLS_SERVER
     *
     * @return  PromiseInterface Fulfilled with the number of seconds elapsed while enabling crypto.
     */
    public function enableCrypto($method = STREAM_CRYPTO_METHOD_TLS_CLIENT)
    {
        $start = microtime(true);
        $method = (int) $method;
        
        $enable = function () use (&$enable, $start, $method) {
            $result = @stream_socket_enable_crypto($this->getResource(), true, $method);
            
            if (false === $result) {
                $message = 'Failed to enable crypto';
                $error = error_get_last();
                if (null !== $error) {
                    $message .= "; Errno: {$error['type']}; {$error['message']}";
                }
                throw new FailureException($message);
            }
            
            if (0 === $result) {
                return $this->poll()->then($enable);
            }
            
            $this->crypto = $method;
            
            return microtime(true) - $start;
        };
        
        return $this->await()->then($enable);
    }
    
    /**
     * @return  PromiseInterface Fulfilled when crypto has been disabled.
     */
    public function disableCrypto()
    {
        if (0 === $this->crypto) {
            return Promise::reject(new FailureException('Crypto was not enabled on the stream.'));
        }
        
        $start = microtime(true);
        
        $disable = function () use (&$disable, $start) {
            $result = @stream_socket_enable_crypto($this->getResource(), false, $this->crypto);
            
            if (false === $result) {
                $message = 'Failed to disable crypto';
                $error = error_get_last();
                if (null !== $error) {
                    $message .= "; Errno: {$error['type']}; {$error['message']}";
                }
                throw new FailureException($message);
            }
            
            if (0 === $result) {
                return $this->poll()->then($disable);
            }
            
            $this->crypto = 0;
            
            return microtime(true) - $start;
        };
        
        return $this->await()->then($disable);
    }
    
    /**
     * @return  bool
     */
    public function isCryptoEnabled()
    {
        return 0 !== $this->crypto;
    }
}
