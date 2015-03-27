<?php
namespace Icicle\Socket;

trait ParserTrait
{
    /**
     * Parses a name of the format ip:port, returning an array containing the ip and port.
     *
     * @param string $name
     *
     * @return array IP address and port pair.
     */
    protected function parseName($name)
    {
        $colon = strrpos($name, ':');

        $address = trim(substr($name, 0, $colon), '[]');
        $port = (int) substr($name, $colon + 1);

        $address = $this->parseAddress($address);

        return [$address, $port];
    }

    /**
     * Formats given address into a string. Converts integer to IPv4 address, wraps IPv6 address in brackets.
     *
     * @param   string|int $address
     *
     * @return  string
     */
    protected function parseAddress($address)
    {
        if (is_int($address)) {
            return long2ip($address);
        }

        if (false !== strpos($address, ':')) { // IPv6 address
            return '[' . trim($address, '[]') . ']';
        }

        return $address;
    }

    /**
     * Creates ip:port formatted name.
     *
     * @param   string|int $address
     * @param   int $port
     *
     * @return  string
     */
    protected function makeName($address, $port)
    {
        return sprintf('%s:%d', $this->parseAddress($address), $port);
    }

    /**
     * Creates string of format protocol://ip:port.
     *
     * @param   string $protocol
     * @param   string|int $address
     * @param   int $port
     *
     * @return string
     */
    protected function makeUri($protocol, $address, $port)
    {
        return sprintf('%s://%s:%d', $protocol, $this->parseAddress($address), $port);
    }
}
