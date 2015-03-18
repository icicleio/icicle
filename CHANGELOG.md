# Changelog

### v0.1.2

Updated the behavior the Socket component to be similar the stream socket implementation in PHP 7.

- `Icicle\Socket\ReadableStreamTrait::poll()` will fulfill with an empty string even if the connection has closed (stream socket is at EOF) instead of closing the stream and rejecting the promise. This change was made to match the stream socket behavior in PHP 7 that will not return true on `feof()` until a read has been attempted past EOF.

---

### v0.1.1

Moved Process into separate branch for further development. Will likely be released as a separate package in the future.

---

### v0.1.0

Initial Release.
