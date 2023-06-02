# Use another image to build backend and frontend assets - Base doesn't need composer
FROM composer:2.5.7 as build-backend

# Add required headers to build sockets PHP extension
RUN apk add --no-cache linux-headers

# Install required PHP extensions
RUN docker-php-ext-install bcmath sockets

# Create and apply permissions for the storage and cache directories
RUN mkdir -p /app/storage/framework/sessions
RUN mkdir -p /app/storage/framework/views
RUN mkdir -p /app/storage/framework/cache
RUN mkdir -p /app/storage/logs
RUN mkdir -p /app/storage/app/purify
RUN mkdir -p /app/storage/app/purify/HTML
RUN mkdir -p /app/storage/app/purify/JSON
RUN chmod -R 775 /app/storage
WORKDIR /app
COPY . /app/
RUN composer install --prefer-dist --no-dev --optimize-autoloader --no-interaction

FROM node:current-alpine as build-frontend
WORKDIR /app
COPY --from=build-backend /app /app
RUN yarn install --immutable --immutable-cache --check-cache
RUN yarn build
RUN rm -rf node_modules

FROM ghcr.io/roadrunner-server/roadrunner:2.12.3 AS roadrunner
FROM php:8.1-fpm-alpine as procuction
MAINTAINER Alex Godbehere

# =================================================================
# Install/enable required PHP extensions - This could be broken out

# Ensure that we're using the production configuration before we start to install extensions or they'll be installed into the wrong configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Install NGINX and required packages
RUN apk add --no-cache nginx mysql-client mariadb-connector-c-dev \
    libxml2-dev oniguruma-dev php81-fileinfo php81-session php81-bcmath php81-tokenizer php81-dom php81-xml php81-xmlwriter php81-simplexml php81-sodium php81-pdo \
    autoconf krb5-libs krb5-dev postgresql-dev gcc make g++ zlib-dev \
    && adduser -D -g 'www' www \
    && chown -R www:www /var/lib/nginx

## Prepare Redis
RUN mkdir -p /usr/src/php/ext/redis \
    && curl -L https://github.com/phpredis/phpredis/archive/5.3.7.tar.gz | tar xvz -C /usr/src/php/ext/redis --strip 1 \
    && echo 'redis' >> /usr/src/php-available-exts

## Install PHP extensions
RUN docker-php-ext-install opcache fileinfo bcmath redis session dom mysqli mbstring pdo pdo_mysql xml

ADD https://pecl.php.net/get/krb5-1.1.4.tgz ./
RUN tar -xzf ./krb5-1.1.4.tgz && rm krb5-1.1.4.tgz && cd ./krb5-1.1.4 && phpize && ./configure --with-krb5 && make && make install
RUN echo extension=krb5.so >> "$PHP_INI_DIR/php.ini"

# =================================================================

ENV APP_ENV=production
ENV APP_DEBUG=false

# Copy the roadrunner binary from the official image (https://roadrunner.dev/docs/intro-install/2023.x/en#docker)
COPY --from=roadrunner /usr/bin/rr /app/rr

# Set working directory
WORKDIR /app

COPY .docker/app/nginx.conf /etc/nginx/nginx.conf

# Copy the application
COPY --from=build-frontend --chown=root:root --chmod=644 /app /app

RUN php artisan route:cache && php artisan view:cache && php artisan event:cache

EXPOSE 80

STOPSIGNAL SIGTERM
CMD ["/bin/sh", "-c", "nginx -g 'daemon off;' & php artisan octane:start"]