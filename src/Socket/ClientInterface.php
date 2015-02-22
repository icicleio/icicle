<?php
namespace Icicle\Socket;

interface ClientInterface extends SocketInterface
{
    /**
     * @param   int $method One of the server crypto flags, e.g. STREAM_CRYPTO_METHOD_TLS_SERVER
     *
     * @return  PromiseInterface Fulfilled with the number of seconds elapsed while enabling crypto.
     *
     * @resolve float Number of seconds elapsed while enabling crypto.
     *
     * @reject  FailureException If enabling crypto fails.
     *
     * @api
     */
    public function enableCrypto($method);
    
    /**
     * @return  PromiseInterface Fulfilled with the number of seconds elapsed while disabling crypto.
     *
     * @resolve float Number of seconds elapsed while disabling crypto.
     *
     * @reject  FailureException If disabling crypto fails.
     *
     * @api
     */
    public function disableCrypto();
    
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
