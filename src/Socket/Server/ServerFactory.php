<?php
namespace Icicle\Socket\Server;

use Icicle\Socket\Exception\InvalidArgumentError;
use Icicle\Socket\Exception\FailureException;
use Icicle\Socket\ParserTrait;

class ServerFactory implements ServerFactoryInterface
{
    use ParserTrait;

    const DEFAULT_BACKLOG = SOMAXCONN;

    // Verify peer should normally be off on the server side.
    const DEFAULT_VERIFY_PEER = false;
    const DEFAULT_ALLOW_SELF_SIGNED = false;
    const DEFAULT_VERIFY_DEPTH = 10;

    /**
     * {@inheritdoc}
     */
    public function create($host, $port, array $options = null)
    {
        $protocol = isset($options['protocol'])
            ? (string) $options['protocol']
            : (null === $port ? 'unix' : 'tcp');
        $queue = isset($options['backlog']) ? (int) $options['backlog'] : self::DEFAULT_BACKLOG;
        $pem = isset($options['pem']) ? (string) $options['pem'] : null;
        $passphrase = isset($options['passphrase']) ? (string) $options['passphrase'] : null;
        $name = isset($options['name']) ? (string) $options['name'] : null;

        $verify = isset($options['verify_peer']) ? (string) $options['verify_peer'] : self::DEFAULT_VERIFY_PEER;
        $allowSelfSigned = isset($options['allow_self_signed'])
            ? (bool) $options['allow_self_signed']
            : self::DEFAULT_ALLOW_SELF_SIGNED;
        $verifyDepth = isset($options['verify_depth']) ? (int) $options['verify_depth'] : self::DEFAULT_VERIFY_DEPTH;

        $context = [];
        
        $context['socket'] = [];
        $context['socket']['bindto'] = $this->makeName($host, $port);
        $context['socket']['backlog'] = $queue;
        
        if (null !== $pem) {
            if (!file_exists($pem)) {
                throw new InvalidArgumentError('No file found at given PEM path.');
            }
            
            $context['ssl'] = [];

            $context['ssl']['verify_peer'] = $verify;
            $context['ssl']['verify_peer_name'] = $verify;
            $context['ssl']['allow_self_signed'] = $allowSelfSigned;
            $context['ssl']['verify_depth'] = $verifyDepth;

            $context['ssl']['local_cert'] = $pem;
            $context['ssl']['disable_compression'] = true;

            $context['ssl']['SNI_enabled'] = true;
            $context['ssl']['SNI_server_name'] = $name;
            $context['ssl']['peer_name'] = $name;
            
            if (null !== $passphrase) {
                $context['ssl']['passphrase'] = $passphrase;
            }
        }
        
        $context = stream_context_create($context);
        
        $uri = $this->makeUri($protocol, $host, $port);
        // Error reporting suppressed since stream_socket_server() emits an E_WARNING on failure (checked below).
        $socket = @stream_socket_server($uri, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
        
        if (!$socket || $errno) {
            throw new FailureException(sprintf('Could not create server %s: Errno: %d; %s', $uri, $errno, $errstr));
        }
        
        return new Server($socket);
    }
}
