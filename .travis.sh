#!/bin/bash
set -ev

if [ "$TRAVIS_PHP_VERSION" != "7.0" ]; then
    
    # Install libevent library.
    sudo apt-get install -y libevent-dev
    
    # Install event PHP extension.
    curl http://pecl.php.net/get/event | tar -xz
    pushd event-*
    phpize
    ./configure
    make
    make install
    popd
    echo "extension=event.so" >> "$(php -r 'echo php_ini_loaded_file();')"
    
    # Install libevent PHP extension.
    curl http://pecl.php.net/get/libevent | tar -xz
    pushd libevent-*
    phpize
    ./configure
    make
    make install
    popd
    echo "extension=libevent.so" >> "$(php -r 'echo php_ini_loaded_file();')"
    
fi

composer install --dev --no-interaction --prefer-source