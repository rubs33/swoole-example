FROM php:7.4-cli-alpine

# Prepare PHP deps
RUN apk add --no-cache \
        openssl \
        libstdc++

# Install swoole
RUN apk add --no-cache --virtual .build-swoole-deps \
        $PHPIZE_DEPS \
        autoconf \
        automake \
        g++ \
        gcc \
        git \
        linux-headers \
        make \
        openssl-dev && \
    mkdir -p /build && \
    cd /build && \
    git clone https://github.com/swoole/swoole-src.git && \
    cd swoole-src && \
    git checkout v4.5.2 && \
    phpize && \
    ./configure --with-php-config=/usr/local/bin/php-config --enable-openssl --enable-http2 && \
    make -j$(nproc) && make install && \
    cd / && \
    rm -rf /build && \
    echo 'extension=swoole.so' > /usr/local/etc/php/conf.d/swoole.ini && \
    apk del .build-swoole-deps

# Install PHP deps
RUN apk add --no-cache --virtual .build-php-deps \
        autoconf \
        automake \
        g++ \
        gcc \
        make && \
    docker-php-ext-install pdo_mysql && \
    pecl install redis && \
    docker-php-ext-enable redis && \
    apk del .build-php-deps

# Clean container
RUN rm -rf \
        /usr/src/php.tar.xz \
        /usr/local/include/php/ \
        /usr/local/bin/php-cgi \
        /usr/local/bin/phpdbg \
        /usr/local/bin/phpize \
        /tmp/*

# Ensure PHP deps are available
RUN php -m | grep -iE '^swoole$' && \
    php -m | grep -iE '^pdo$' && \
    php -m | grep -iE '^pdo_mysql$' && \
    php -m | grep -iE '^redis$'

# Prepare app
WORKDIR /app
COPY app /app

CMD ["php", "/app/server.php"]
