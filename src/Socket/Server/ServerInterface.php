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
     * Returns the IP address on which the server is listening.
     *
     * @return  string
     *
     * @api
     */
    public function getAddress();
    
    /**
     * Returns the port on which the server is listening.
     *
     * @return  int
     *
     * @api
     */
    public function getPort();
}
