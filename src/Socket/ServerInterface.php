<?php
namespace Icicle\Socket;

interface ServerInterface extends SocketInterface
{
    /**
     * Accepts incoming client connections.
     *
     * @return  PromiseInterface
     *
     * @resolve ClientInterface
     *
     * @reject  AcceptException If an error occurs when accepting the client.
     * @reject  UnavailableException If an accept request was already pending on the server.
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
