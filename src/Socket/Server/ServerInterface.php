<?php
namespace Icicle\Socket\Server;

use Icicle\Socket\SocketInterface;

interface ServerInterface extends SocketInterface
{
    /**
     * Accepts incoming client connections.
     *
     * @return  \Icicle\Promise\PromiseInterface
     *
     * @resolve \Icicle\Socket\Client\ClientInterface
     *
     * @reject  \Icicle\Socket\Exception\AcceptException If an error occurs when accepting the client.
     * @reject  \Icicle\Socket\Exception\FailureException If creating the client fails.
     * @reject  \Icicle\Socket\Exception\UnavailableException If an accept request was already pending on the server.
     *
     * @api
     */
    public function accept();
    
    /**
     * Returns the IP address or socket path on which the server is listening.
     *
     * @return  string
     *
     * @api
     */
    public function getAddress();
    
    /**
     * Returns the port on which the server is listening (or null if unix socket).
     *
     * @return  int|null
     *
     * @api
     */
    public function getPort();
}
