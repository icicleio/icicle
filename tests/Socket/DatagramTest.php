<?php
namespace Icicle\Tests\Socket;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Socket\Datagram;
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
    
    public function testCreate()
    {
        $this->datagram = Datagram::create(self::HOST_IPv4, self::PORT);
        
        $this->assertSame(self::HOST_IPv4, $this->datagram->getAddress());
        $this->assertSame(self::PORT, $this->datagram->getPort());
    }
    
    public function testCreateIPv6()
    {
        $this->datagram = Datagram::create(self::HOST_IPv6, self::PORT);
        
        $this->assertSame(self::HOST_IPv6, $this->datagram->getAddress());
        $this->assertSame(self::PORT, $this->datagram->getPort());
    }
    
    /**
     * @medium
     * @depends testCreate
     * @expectedException Icicle\Socket\Exception\FailureException
     */
    public function testCreateInvalidHost()
    {
        $this->datagram = Datagram::create('invalid.host', self::PORT);
        
        $this->datagram->close();
    }
    
    public function testInvalidSocketType()
    {
        $this->datagram = new Datagram(fopen('php://memory', 'r+'));
        
        $this->assertFalse($this->datagram->isOpen());
    }
    
    /**
     * @depends testCreate
     */
    public function testReceive()
    {
        $this->datagram = Datagram::create(self::HOST_IPv4, self::PORT);
        
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
    
    /**
     * @depends testCreateIPv6
     */
    public function testReceiveFromIPv6()
    {
        $this->datagram = Datagram::create(self::HOST_IPv6, self::PORT);
        
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
        $this->datagram = Datagram::create(self::HOST_IPv4, self::PORT);
        
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
        $this->datagram = Datagram::create(self::HOST_IPv4, self::PORT);
        
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
        $this->datagram = Datagram::create(self::HOST_IPv4, self::PORT);
        
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
        $this->datagram = Datagram::create(self::HOST_IPv4, self::PORT);
        
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
        $this->datagram = Datagram::create(self::HOST_IPv4, self::PORT);
        
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
        
        $this->datagram = Datagram::create(self::HOST_IPv4, self::PORT);
        
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
        $this->datagram = Datagram::create(self::HOST_IPv4, self::PORT);
        
        $promise = $this->datagram->receive();
        
        Loop::tick();
        
        $this->assertTrue($promise->isPending());
    }
    
    /**
     * @depends testReceive
     */
    public function testDrainThenReceive()
    {
        $this->datagram = Datagram::create(self::HOST_IPv4, self::PORT);
        
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
        $this->datagram = Datagram::create(self::HOST_IPv4, self::PORT);
        
        $promise = $this->datagram->receive(null, self::TIMEOUT);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\TimeoutException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @depends testReceive
     */
    public function testReceiveAfterEof()
    {
        $this->datagram = Datagram::create(self::HOST_IPv4, self::PORT);
        
        fclose($this->datagram->getResource());
        
        $promise = $this->datagram->receive();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\UnavailableException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @depends testCreate
     */
    public function testPoll()
    {
        $this->datagram = Datagram::create(self::HOST_IPv4, self::PORT);
        
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
        $this->datagram = Datagram::create(self::HOST_IPv4, self::PORT);
        
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
        $this->datagram = Datagram::create(self::HOST_IPv4, self::PORT);
        
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
        $this->datagram = Datagram::create(self::HOST_IPv4, self::PORT);
        
        $client = stream_socket_client(
            'udp://' . self::HOST_IPv4 . ':' . self::PORT,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT
        );
        
        list($address, $port) = Socket::parseSocketName($client, false);
        
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
        $this->datagram = Datagram::create(self::HOST_IPv6, self::PORT);
        
        $client = stream_socket_client(
            'udp://' . self::HOST_IPv6 . ':' . self::PORT,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT
        );
        
        list($address, $port) = Socket::parseSocketName($client, false);
        
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
        $this->datagram = Datagram::create(self::HOST_IPv4, self::PORT);
        
        $client = stream_socket_client(
            'udp://' . self::HOST_IPv4 . ':' . self::PORT,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT
        );
        
        list($address, $port) = Socket::parseSocketName($client, false);
        
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
        $this->datagram = Datagram::create(self::HOST_IPv4, self::PORT);
        
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
        $this->datagram = Datagram::create(self::HOST_IPv4, self::PORT);
        
        $client = stream_socket_client(
            'udp://' . self::HOST_IPv4 . ':' . self::PORT,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT
        );
        
        list($address, $port) = Socket::parseSocketName($client, false);
        
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
        $this->datagram = Datagram::create(self::HOST_IPv4, self::PORT);
        
        $client = stream_socket_client(
            'udp://' . self::HOST_IPv4 . ':' . self::PORT,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT
        );
        
        list($address, $port) = Socket::parseSocketName($client, false);
        
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
        $this->datagram = Datagram::create(self::HOST_IPv4, self::PORT);
        
        $client = stream_socket_client(
            'udp://' . self::HOST_IPv4 . ':' . self::PORT,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT
        );
        
        list($address, $port) = Socket::parseSocketName($client, false);
        
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
        $this->datagram = Datagram::create(self::HOST_IPv4, self::PORT);
        
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
        $this->datagram = Datagram::create(self::HOST_IPv4, self::PORT);
        
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
        $this->datagram = Datagram::create(self::HOST_IPv4, self::PORT);
        
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
        $this->datagram = Datagram::create(self::HOST_IPv4, self::PORT);
        
        $client = stream_socket_client(
            'udp://' . self::HOST_IPv4 . ':' . self::PORT,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT
        );
        
        list($address, $port) = Socket::parseSocketName($client, false);
        
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
