<?php
namespace Icicle\Socket\Datagram;

interface DatagramFactoryInterface
{
    /**
     * @param   string $host
     * @param   int $port
     * @param   array $options
     *
     * @return  Datagram
     *
     * @throws  FailureException If creating the datagram fails.
     */
    public static function create($host, $port, array $options = []);
}
