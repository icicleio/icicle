#!/bin/bash
set -ev

# Skipping PHP 5.4 since it does not support generators.
# Skipping PHP 7.0 because xdebug is not available.

if [ "$TRAVIS_PHP_VERSION" != "5.4" ] && [ "$TRAVIS_PHP_VERSION" != "7.0" ]; then

    vendor/bin/coveralls -v --exclude-no-stmt

fi