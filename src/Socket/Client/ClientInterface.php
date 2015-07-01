<?php
namespace Icicle\Socket\Client;

use Icicle\Socket\SocketInterface;
use Icicle\Stream\DuplexStreamInterface;

interface ClientInterface extends SocketInterface, DuplexStreamInterface
{
    /**
     * @coroutine
     *
     * @param int $method One of the server crypto flags, e.g. STREAM_CRYPTO_METHOD_TLS_SERVER for incoming (remote)
     *     clients, STREAM_CRYPTO_METHOD_TLS_CLIENT for outgoing (local) clients.
     * @param int|float $timeout Seconds to wait between reads/writes to enable crypto before failing.
     *
     * @return \Generator
     *
     * @resolve $this
     *
     * @reject \Icicle\Socket\Exception\FailureException If enabling crypto fails.
     * @reject \Icicle\Socket\Exception\ClosedException If the client has been closed.
     * @reject \Icicle\Socket\Exception\BusyError If the client was already busy waiting to read.
     */
    public function enableCrypto($method, $timeout = 0);
    
    /**
     * Determines if cyrpto has been enabled.
     *
     * @return bool
     */
    public function isCryptoEnabled();
    
    /**
     * Returns the remote IP or socket path as a string representation.
     *
     * @return string
     */
    public function getRemoteAddress();
    
    /**
     * Returns the remote port number (or null if unix socket).
     *
     * @return int|null
     */
    public function getRemotePort();
    
    /**
     * Returns the local IP or socket path as a string representation.
     *
     * @return string
     */
    public function getLocalAddress();
    
    /**
     * Returns the local port number (or null if unix socket).
     *
     * @return int|null
     */
    public function getLocalPort();
}
