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
     * @param int|null $length
     *
     * @return int|null
     */
    protected function parseLength($length)
    {
        if (null !== $length) {
            $length = (int) $length;
            if (0 > $length) {
                $length = 0;
            }
        }

        return $length;
    }
}
