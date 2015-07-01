<?php
namespace Icicle\Socket\Datagram;

use Icicle\Socket\SocketInterface;

interface DatagramInterface extends SocketInterface
{
    /**
     * @return string
     */
    public function getAddress();
    
    /**
     * @return int
     */
    public function getPort();
    
    /**
     * @param int|null $length
     * @param float|int|null $timeout
     *
     * @return \Icicle\Promise\PromiseInterface
     *
     * @resolve [string, int, string] Array containing the senders remote address, remote port, and data received.
     *
     * @reject BusyError If a read was already pending on the datagram.
     * @reject UnreadableException If the datagram is no longer readable.
     * @reject ClosedException If the datagram has been closed.
     * @reject TimeoutException If receiving times out.
     * @reject FailureException If receiving fails.
     */
    public function receive($length = null, $timeout = null);

    /**
     * @param int|string $address IP address of receiver.
     * @param int $port Port of receiver.
     * @param string|null $data Data to send.
     *
     * @return \Icicle\Promise\PromiseInterface
     *
     * @resolve int Number of bytes written.
     *
     * @reject \Icicle\Socket\Exception\UnavailableException If the datagram is no longer writable.
     * @reject \Icicle\Socket\Exception\ClosedException If the datagram closes.
     * @reject \Icicle\Socket\Exception\TimeoutException If sending the data times out.
     * @reject \Icicle\Socket\Exception\FailureException If sending data fails.
     */
    public function send($address, $port, $data);
}
