<?php
namespace Icicle\Stream;

trait ParserTrait
{
    /**
     * @param string|int|null $byte
     *
     * @return string|null Single-byte string or null.
     */
    protected function parseByte($byte)
    {
        if (null !== $byte) {
            $byte = is_int($byte) ? pack('C', $byte) : (string) $byte;
            $byte = strlen($byte) ? $byte[0] : null;
        }
        return $byte;
    }

    /**
     * @param int $length
     *
     * @return int
     */
    protected function parseLength($length)
    {
        $length = (int) $length;
        if (0 <= $length) {
            return $length;
        }
        return 0;
    }
}
