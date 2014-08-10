<?php
namespace Icicle\Server;

interface ServerInterface
{
    const DEFAULT_HOST = '127.0.0.1';
    
    /**
     * Defines a port and optional host which the server should use to listen for connections.
     * @param   int $port
     * @param   string $host
     */
    public function listen($port, $host = self::DEFAULT_HOST);
    
    /**
     * Starts the server loop, listening for and accepting connections.
     * This function should not return until the server stops.
     */
    public function run();
    
    /**
     * Determines if the server is running, listening for connections.
     * @return  bool
     */
    public function isRunning();
    
    /**
     * Stops the server loop.
     */
    public function stop();
}
