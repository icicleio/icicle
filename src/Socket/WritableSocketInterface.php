<?php
namespace Icicle\Socket;

interface WritableSocketInterface extends SocketInterface
{
    /**
     * Called by the loop when the socket is ready for writing.
     */
    public function onWrite();
}
