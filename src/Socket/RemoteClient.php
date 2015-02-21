<?php
namespace Icicle\Socket;

use Exception;
use Icicle\Promise\Promise;
use Icicle\Socket\Exception\FailureException;

class RemoteClient extends Client
{
    /**
     * @var int
     */
    private $crypto = 0;
    
    /**
     * @param   int $method One of the server crypto flags, e.g. STREAM_CRYPTO_METHOD_TLS_SERVER
     *
     * @return  PromiseInterface Fulfilled with the number of seconds elapsed while enabling crypto.
     */
    public function enableCrypto($method = STREAM_CRYPTO_METHOD_TLS_SERVER)
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
        
        return $this->poll()->then($enable);
    }
    
    /**
     * @return  PromiseInterface Fulfilled with the number of seconds elapsed while disabling crypto.
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
