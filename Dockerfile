FROM php:7.4-cli-alpine

# Prepare PHP deps
RUN apk add --no-cache \
        openssl \
        libstdc++

# Install PHP deps
RUN apk add --no-cache --virtual .build-php-deps \
        $PHPIZE_DEPS \
        autoconf \
        automake \
        g++ \
        gcc \
        git \
        linux-headers \
        make \
        openssl-dev && \
    docker-php-ext-install pdo_mysql && \
    pecl install swoole && \
    pecl install redis && \
    docker-php-ext-enable swoole && \
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
