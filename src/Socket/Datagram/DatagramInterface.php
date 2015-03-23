<?php
namespace Icicle\Socket\Datagram;

use Icicle\Socket\SocketInterface;

interface DatagramInterface extends SocketInterface
{
    /**
     * @return  string
     */
    public function getAddress();
    
    /**
     * @return  int
     */
    public function getPort();
    
    /**
     * @param   int|null $length
     *
     * @return  PromiseInterface
     *
     * @resolve [string, int, string] Array containing the senders remote address, remote port, and data received.
     *
     * @reject  BusyException If a read was already pending on the datagram.
     * @reject  UnreadableException If the datagram is no longer readable.
     * @reject  ClosedException If the datagram has been closed.
     * @reject  TimeoutException If receiving times out.
     * @reject  FailureException If receiving fails.
     */
    public function receive($length = null, $timeout = null);
    
    /**
     * @param   int|float|null $timeout
     *
     * @return  PromiseInterface
     *
     * @resolve int Always resolves with 0.
     *
     * @reject  BusyException If the datagram was already waiting on a read.
     * @reject  UnavailableException If the datagram is no longer readable.
     * @reject  ClosedException If the datagram closes.
     * @reject  TimeoutException If polling times out.
     * @reject  FailureExcpetion If polling fails.
     */
    public function poll($timeout = null);
    
    /**
     * @param   int|string $address IP address of receiver.
     * @param   int $port Port of receiver.
     * @param   string|null $data Data to send.
     * @param   int|float|null $timeout
     *
     * @return  PromiseInterface
     *
     * @resolve int Number of bytes written.
     *
     * @reject  UnavailableException If the datagram is no longer writable.
     * @reject  ClosedException If the datagram closes.
     * @reject  TimeoutException If sending the data times out.
     * @reject  FailureException If sending data fails.
     */
    public function send($address, $port, $data);
    
    /**
     * @return  PromiseInterface
     */
    public function await();
}
