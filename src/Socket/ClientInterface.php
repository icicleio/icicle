<?php
namespace Icicle\Socket;

interface ClientInterface extends SocketInterface
{
    /**
     * @param   int $method One of the server crypto flags, e.g. STREAM_CRYPTO_METHOD_TLS_SERVER for incoming (remote)
     *          clients, STREAM_CRYPTO_METHOD_TLS_CLIENT for outgoing (local) clients.
     *
     * @return  PromiseInterface
     *
     * @resolve self
     *
     * @reject  FailureException If enabling crypto fails.
     * @reject  ClosedException If the client has been closed.
     * @reject  BusyException If the client was already busy waiting to read.
     *
     * @api
     */
    public function enableCrypto($method);
    
    /**
     * Determines if cyrpto has been enabled.
     *
     * @return  bool
     *
     * @api
     */
    public function isCryptoEnabled();
    
    /**
     * Returns the remote IP as a string representation.
     *
     * @return  string
     *
     * @api
     */
    public function getRemoteAddress();
    
    /**
     * Returns the remote port number.
     *
     * @return  int
     *
     * @api
     */
    public function getRemotePort();
    
    /**
     * Returns the remote IP as a string representation.
     *
     * @return  string
     *
     * @api
     */
    public function getLocalAddress();
    
    /**
     * Returns the local port number.
     *
     * @return  int
     *
     * @api
     */
    public function getLocalPort();
}
