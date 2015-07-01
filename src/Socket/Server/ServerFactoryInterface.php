<?php
namespace Icicle\Socket\Server;

interface ServerFactoryInterface
{
    /**
     * Creates a server on the given host and port.
     *
     * Note: Current CA file in PEM format can be downloaded from http://curl.haxx.se/ca/cacert.pem
     *
     * @param string|int $host IP address or unix socket path.
     * @param int|null $port Port number or null for unix socket.
     * @param mixed[]|null $options {
     *     @var int $backlog Connection backlog size. Note that operating system setting SOMAXCONN may set an upper
     *     limit and may need to be changed to allow a larger backlog size.
     *     @var string $pem Path to PEM file containing certificate and private key to enable SSL on client connections.
     *     @var string $passphrase PEM passphrase if applicable.
     *     @var string $name Name to use as SNI identifier. If not set, name will be guessed based on $host.
     *     @var bool $verify_peer True to verify client certificate. Normally should be false on the server.
     *     @var bool $allow_self_signed Set to true to allow self-signed certificates. Defaults to false.
     *     @var int $verify_depth Max levels of certificate authorities the verifier will transverse. Defaults to 10.
     * }
     *
     * @return \Icicle\Socket\Server\ServerInterface
     *
     * @throws \Icicle\Socket\Exception\InvalidArgumentError If PEM file path given does not exist.
     * @throws \Icicle\Socket\Exception\FailureException If the server socket could not be created.
     */
    public function create($host, $port, array $options = null);
}
