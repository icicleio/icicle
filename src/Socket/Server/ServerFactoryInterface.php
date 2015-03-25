<?php
namespace Icicle\Socket\Server;

interface ServerFactoryInterface
{
    /**
     * Creates a server on the given host and port.
     *
     * Note: Current CA file in PEM format can be downloaded from http://curl.haxx.se/ca/cacert.pem
     *
     * @param   string $host
     * @param   int $port
     * @param   mixed[] $options {
     *     @var int $backlog Connection backlog size. Note that operating system setting SOMAXCONN may set an upper
     *          limit and may need to be changed to allow a larger backlog size.
     *     @var string $pem Path to PEM file containing certificate and private key to enable SSL on client connections.
     *     @var string $passphrase PEM passphrase if applicable.
     *     @var string $name Name to use as SNI identifier. If not set, name will be guessed based on $host.
     * }
     *
     * @return  \Icicle\Socket\Server\ServerInterface
     *
     * @throws  \Icicle\Socket\Exception\InvalidArgumentException If PEM file path given does not exist.
     * @throws  \Icicle\Socket\Exception\FailureException If the server socket could not be created.
     */
    public function create($host, $port, array $options = null);
}
