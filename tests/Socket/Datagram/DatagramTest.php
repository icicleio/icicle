<?php
namespace Icicle\Tests\Socket\Datagram;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Socket\Datagram\Datagram;
use Icicle\Socket\Socket;
use Icicle\Tests\TestCase;

class DatagramTest extends TestCase
{
    const HOST_IPv4 = '127.0.0.1';
    const HOST_IPv6 = '[::1]';
    const PORT = 51337;
    const CONNECT_TIMEOUT = 1;
    
    const CHUNK_SIZE = 8192;
    const TIMEOUT = 0.1;
    const WRITE_STRING = 'abcdefghijklmnopqrstuvwxyz';
    
    protected $datagram;
    
    public function tearDown()
    {
        Loop::clear();
        
        if ($this->datagram instanceof Datagram) {
            $this->datagram->close();
        }
    }
    
    public function createDatagram()
    {
        $host = self::HOST_IPv4;
        $port = self::PORT;
        
        $context = [];
        
        $context['socket'] = [];
        $context['socket']['bindto'] = "{$host}:{$port}";
        
        $context = stream_context_create($context);
        
        $uri = sprintf('udp://%s:%d', $host, $port);
        $socket = @stream_socket_server($uri, $errno, $errstr, STREAM_SERVER_BIND, $context);
        
        if (!$socket || $errno) {
            $this->fail("Could not create datagram on {$host}:{$port}: [Errno: {$errno}] {$errstr}");
        }
        
        return new Datagram($socket);
    }
    
    public function createDatagramIPv6()
    {
        $host = self::HOST_IPv6;
        $port = self::PORT;
        
        $context = [];
        
        $context['socket'] = [];
        $context['socket']['bindto'] = "{$host}:{$port}";
        
        $context = stream_context_create($context);
        
        $uri = sprintf('udp://%s:%d', $host, $port);
        $socket = @stream_socket_server($uri, $errno, $errstr, STREAM_SERVER_BIND, $context);
        
        if (!$socket || $errno) {
            $this->fail("Could not create datagram on {$host}:{$port}: [Errno: {$errno}] {$errstr}");
        }
        
        return new Datagram($socket);
    }
    
    public function testInvalidSocketType()
    {
        $this->datagram = new Datagram(fopen('php://memory', 'r+'));
        
        $this->assertFalse($this->datagram->isOpen());
    }
    
    public function testReceive()
    {
        $this->datagram = $this->createDatagram();
        
        $client = stream_socket_client(
            'udp://' . self::HOST_IPv4 . ':' . self::PORT,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT
        );
        
        if (0 >= stream_socket_sendto($client, self::WRITE_STRING)) {
            $this->fail('Could not write to datagram.');
        }
        
        $promise = $this->datagram->receive();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->will($this->returnCallback(function ($data) {
                     list($address, $port, $message) = $data;
                     $this->assertSame(self::HOST_IPv4, $address);
                     $this->assertInternalType('integer', $port);
                     $this->assertGreaterThan(0, $port);
                     $this->assertSame(self::WRITE_STRING, $message);
                 }));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    public function testReceiveFromIPv6()
    {
        $this->datagram = $this->createDatagramIPv6();
        
        $client = stream_socket_client(
            'udp://' . self::HOST_IPv6 . ':' . self::PORT,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT
        );
        
        if (0 >= stream_socket_sendto($client, self::WRITE_STRING)) {
            $this->fail('Could not write to datagram.');
        }
        
        $promise = $this->datagram->receive();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->will($this->returnCallback(function ($data) {
                     list($address, $port, $message) = $data;
                     $this->assertSame(self::HOST_IPv6, $address);
                     $this->assertInternalType('integer', $port);
                     $this->assertGreaterThan(0, $port);
                     $this->assertSame(self::WRITE_STRING, $message);
                 }));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testReceive
     */
    public function testReceiveAfterClose()
    {
        $this->datagram = $this->createDatagram();
        
        $this->datagram->close();
        
        $promise = $this->datagram->receive();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\UnavailableException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @depends testReceive
     */
    public function testReceiveThenClose()
    {
        $this->datagram = $this->createDatagram();
        
        $promise = $this->datagram->receive();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\ClosedException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        $this->datagram->close();
        
        Loop::run();
    }
    
    /**
     * @depends testReceive
     */
    public function testSimultaneousReceive()
    {
        $this->datagram = $this->createDatagram();
        
        $client = stream_socket_client(
            'udp://' . self::HOST_IPv4 . ':' . self::PORT,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT
        );
        
        if (0 >= stream_socket_sendto($client, self::WRITE_STRING)) {
            $this->fail('Could not write to datagram.');
        }
        
        $promise1 = $this->datagram->receive();
        
        $promise2 = $this->datagram->receive();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->will($this->returnCallback(function ($data) {
                     list($address, $port, $message) = $data;
                     $this->assertSame(self::HOST_IPv4, $address);
                     $this->assertInternalType('integer', $port);
                     $this->assertGreaterThan(0, $port);
                     $this->assertSame(self::WRITE_STRING, $message);
                 }));
        
        $promise1->done($callback, $this->createCallback(0));
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceof('Icicle\Socket\Exception\BusyException'));
        
        $promise2->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @depends testReceive
     */
    public function testReceiveWithLength()
    {
        $this->datagram = $this->createDatagram();
        
        $client = stream_socket_client(
            'udp://' . self::HOST_IPv4 . ':' . self::PORT,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT
        );
        
        if (0 >= stream_socket_sendto($client, self::WRITE_STRING)) {
            $this->fail('Could not write to datagram.');
        }
        
        $length = (int) floor(strlen(self::WRITE_STRING / 2));
        
        $promise = $this->datagram->receive($length);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->will($this->returnCallback(function ($data) use ($length) {
                     list($address, $port, $message) = $data;
                     $this->assertSame(self::HOST_IPv4, $address);
                     $this->assertInternalType('integer', $port);
                     $this->assertGreaterThan(0, $port);
                     $this->assertSame(substr(self::WRITE_STRING, 0, $length), $message);
                 }));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testReceiveWithLength
     */
    public function testReceiveWithInvalidLength()
    {
        $this->datagram = $this->createDatagram();
        
        $client = stream_socket_client(
            'udp://' . self::HOST_IPv4 . ':' . self::PORT,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT
        );
        
        if (0 >= stream_socket_sendto($client, self::WRITE_STRING)) {
            $this->fail('Could not write to datagram.');
        }
        
        $promise = $this->datagram->receive(-1);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->will($this->returnCallback(function ($data) {
                     list($address, $port, $message) = $data;
                     $this->assertNull($address);
                     $this->assertNull($port);
                     $this->assertEmpty($message);
                 }));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testReceive
     */
    public function testCancelReceive()
    {
        $exception = new Exception();
        
        $this->datagram = $this->createDatagram();
        
        $client = stream_socket_client(
            'udp://' . self::HOST_IPv4 . ':' . self::PORT,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT
        );
        
        if (0 >= stream_socket_sendto($client, self::WRITE_STRING)) {
            $this->fail('Could not write to datagram.');
        }
        
        $promise = $this->datagram->receive();
        
        $promise->cancel($exception);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
        
        $promise = $this->datagram->receive();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->will($this->returnCallback(function ($data) {
                     list($address, $port, $message) = $data;
                     $this->assertSame(self::HOST_IPv4, $address);
                     $this->assertInternalType('integer', $port);
                     $this->assertGreaterThan(0, $port);
                     $this->assertSame(self::WRITE_STRING, $message);
                 }));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testReceive
     */
    public function testReceiveOnEmptyDatagram()
    {
        $this->datagram = $this->createDatagram();
        
        $promise = $this->datagram->receive();
        
        Loop::tick();
        
        $this->assertTrue($promise->isPending());
    }
    
    /**
     * @depends testReceive
     */
    public function testDrainThenReceive()
    {
        $this->datagram = $this->createDatagram();
        
        $client = stream_socket_client(
            'udp://' . self::HOST_IPv4 . ':' . self::PORT,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT
        );
        
        if (0 >= stream_socket_sendto($client, self::WRITE_STRING)) {
            $this->fail('Could not write to datagram.');
        }
        
        $promise = $this->datagram->receive();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->will($this->returnCallback(function ($data) {
                     list($address, $port, $message) = $data;
                     $this->assertSame(self::HOST_IPv4, $address);
                     $this->assertInternalType('integer', $port);
                     $this->assertGreaterThan(0, $port);
                     $this->assertSame(self::WRITE_STRING, $message);
                 }));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
        
        $string = "This is a string to write.\n";
        
        if (0 >= stream_socket_sendto($client, $string)) {
            $this->fail('Could not write to datagram.');
        }
        
        $promise = $this->datagram->receive();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->will($this->returnCallback(function ($data) use ($string) {
                     list($address, $port, $message) = $data;
                     $this->assertSame(self::HOST_IPv4, $address);
                     $this->assertInternalType('integer', $port);
                     $this->assertGreaterThan(0, $port);
                     $this->assertSame($string, $message);
                 }));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testReceive
     */
    public function testReceiveWithTimeout()
    {
        $this->datagram = $this->createDatagram();
        
        $promise = $this->datagram->receive(null, self::TIMEOUT);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\TimeoutException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }

    public function testPoll()
    {
        $this->datagram = $this->createDatagram();
        
        $client = stream_socket_client(
            'udp://' . self::HOST_IPv4 . ':' . self::PORT,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT
        );
        
        if (0 >= stream_socket_sendto($client, self::WRITE_STRING)) {
            $this->fail('Could not write to datagram.');
        }
        
        $promise = $this->datagram->poll();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->will($this->returnCallback(function ($data) {
                     list($address, $port, $message) = $data;
                     $this->assertNull($address);
                     $this->assertNull($port);
                     $this->assertEmpty($message);
                 }));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
        
        $promise = $this->datagram->receive(); // Empty the datagram and ignore data.
        
        Loop::run();
        
        $promise = $this->datagram->poll();
        
        $promise->done($this->createCallback(0), $this->createCallback(0));
        
        Loop::tick();
    }
    
    /**
     * @depends testPoll
     */
    public function testPollAfterClose()
    {
        $this->datagram = $this->createDatagram();
        
        $this->datagram->close();
        
        $promise = $this->datagram->poll();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\UnavailableException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @depends testPoll
     */
    public function testPollThenClose()
    {
        $this->datagram = $this->createDatagram();
        
        $promise = $this->datagram->poll();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\ClosedException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        $this->datagram->close();
        
        Loop::run();
    }
    
    public function testSend()
    {
        $this->datagram = $this->createDatagram();
        
        $client = stream_socket_client(
            'udp://' . self::HOST_IPv4 . ':' . self::PORT,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT
        );
        
        $name = stream_socket_get_name($client, false);
        list($address, $port) = explode(':', $name);
        
        $string = "{'New String\0To Write'}\r\n";
        
        $promise = $this->datagram->send($address, $port, $string);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(strlen($string)));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
        
        $data = stream_socket_recvfrom($client, self::CHUNK_SIZE);
        
        $this->assertSame($string, $data);
    }
    
    /**
     * @depends testSend
     */
    public function testSendIPv6()
    {
        $this->datagram = $this->createDatagramIPv6(self::HOST_IPv6, self::PORT);
        
        $client = stream_socket_client(
            'udp://' . self::HOST_IPv6 . ':' . self::PORT,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT
        );
        
        $name = stream_socket_get_name($client, false);
        $colon = strrpos($name, ':');
        $address = substr($name, 0, $colon);
        $port = (int) substr($name, $colon + 1);
        
        $string = "{'New String\0To Write'}\r\n";
        
        $promise = $this->datagram->send($address, $port, $string);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(strlen($string)));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
        
        $data = stream_socket_recvfrom($client, self::CHUNK_SIZE);
        
        $this->assertSame($string, $data);
    }
    
    /**
     * @depends testSend
     */
    public function testSendIntegerIP()
    {
        $this->datagram = $this->createDatagram();
        
        $client = stream_socket_client(
            'udp://' . self::HOST_IPv4 . ':' . self::PORT,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT
        );
        
        $name = stream_socket_get_name($client, false);
        list($address, $port) = explode(':', $name);
        
        $address = ip2long($address);
        
        $string = "{'New String\0To Write'}\r\n";
        
        $promise = $this->datagram->send($address, $port, $string);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(strlen($string)));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
        
        $data = stream_socket_recvfrom($client, self::CHUNK_SIZE);
        
        $this->assertSame($string, $data);
    }
    
    /**
     * @depends testSend
     */
    public function testSendAfterClose()
    {
        $this->datagram = $this->createDatagram();
        
        $this->datagram->close();
        
        $promise = $this->datagram->send(0, 0, self::WRITE_STRING);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\UnavailableException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    public function testSendEmptyString()
    {
        $this->datagram = $this->createDatagram();
        
        $client = stream_socket_client(
            'udp://' . self::HOST_IPv4 . ':' . self::PORT,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT
        );
        
        $name = stream_socket_get_name($client, false);
        list($address, $port) = explode(':', $name);
        
        $promise = $this->datagram->send($address, $port, '');
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(0));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
        
        $promise = $this->datagram->send($address, $port, '0');
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(1));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
        
        $data = stream_socket_recvfrom($client, self::CHUNK_SIZE);
        
        $this->assertSame('0', $data);
    }
    
    /**
     * @depends testSend
     */
    public function testSendAfterPendingSend()
    {
        $this->datagram = $this->createDatagram();
        
        $client = stream_socket_client(
            'udp://' . self::HOST_IPv4 . ':' . self::PORT,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT
        );
        
        $name = stream_socket_get_name($client, false);
        list($address, $port) = explode(':', $name);
        
        $buffer = null;
        for ($i = 0; $i < self::CHUNK_SIZE; ++$i) {
            $buffer .= self::WRITE_STRING;
        }
        
        $promise = $this->datagram->send($address, $port, $buffer);
        
        $this->assertTrue($promise->isPending());
        
        $promise = $this->datagram->send($address, $port, self::WRITE_STRING);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(strlen(self::WRITE_STRING)));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testSend
     */
    public function testCloseAfterPendingSend()
    {
        $this->datagram = $this->createDatagram();
        
        $client = stream_socket_client(
            'udp://' . self::HOST_IPv4 . ':' . self::PORT,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT
        );
        
        $name = stream_socket_get_name($client, false);
        list($address, $port) = explode(':', $name);
        
        $buffer = null;
        for ($i = 0; $i < self::CHUNK_SIZE; ++$i) {
            $buffer .= self::WRITE_STRING;
        }
        
        $promise = $this->datagram->send($address, $port, $buffer);
        
        $this->assertTrue($promise->isPending());
        
        $this->datagram->close();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\ClosedException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    public function testAwait()
    {
        $this->datagram = $this->createDatagram();
        
        $promise = $this->datagram->await();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(0));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testAwait
     */
    public function testAwaitAfterClose()
    {
        $this->datagram = $this->createDatagram();
        
        $this->datagram->close();
        
        $promise = $this->datagram->await();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\UnavailableException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @depends testAwait
     */
    public function testAwaitThenClose()
    {
        $this->datagram = $this->createDatagram();
        
        $promise = $this->datagram->await();
        
        $this->datagram->close();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\ClosedException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @depends testAwait
     */
    public function testAwaitAfterPendingSend()
    {
        $this->datagram = $this->createDatagram();
        
        $client = stream_socket_client(
            'udp://' . self::HOST_IPv4 . ':' . self::PORT,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT
        );
        
        $name = stream_socket_get_name($client, false);
        list($address, $port) = explode(':', $name);
        
        $buffer = null;
        for ($i = 0; $i < self::CHUNK_SIZE; ++$i) {
            $buffer .= self::WRITE_STRING;
        }
        
        $promise = $this->datagram->send($address, $port, $buffer);
        
        $this->assertTrue($promise->isPending());
        
        $promise = $this->datagram->await();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(0));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
}
