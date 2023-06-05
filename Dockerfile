# syntax=docker/dockerfile:1
# The line above must be the first line in the file!

FROM webdevops/php-nginx:8.1-alpine AS build-backend
RUN install -o application -d /app
WORKDIR /app
USER application
ENV PHP_DISMOD=bz2,calendar,exiif,ffi,intl,gettext,ldap,mysqli,imap,soap,sockets,sysvmsg,sysvsm,sysvshm,shmop,xsl,zip,gd,apcu,vips,imagick,mongodb,amqp

COPY --chown=application composer.json composer.lock .
RUN composer install --no-interaction --no-dev --no-autoloader
COPY --chown=application . .
RUN composer dump-autoload --classmap-authoritative --no-dev

FROM node:20-alpine AS build-frontend
RUN install -o node -d /app
WORKDIR /app
USER node
COPY --chown=node package.json yarn.lock .
RUN yarn
COPY --chown=node vite.config.js tailwind.config.js postcss.config.js .
COPY --chown=node resources ./resources
RUN yarn build && rm -rf node_modules

FROM webdevops/php-nginx:8.1-alpine AS production

ADD https://pecl.php.net/get/krb5-1.1.4.tgz ./
RUN <<'SHELL'
    # System dependencies
    apk add --no-cache autoconf krb5-libs krb5-dev \
        oniguruma-dev postgresql-dev libxml2-dev \
        gcc make g++ zlib-dev

    docker-php-ext-enable redis
    tar -xzf ./krb5-1.1.4.tgz 
    rm krb5-1.1.4.tgz 
    (   cd ./krb5-1.1.4 
        phpize 
        ./configure --with-krb5
        make
        make install
    )
    echo extension=krb5.so >> /opt/docker/etc/php/php.ini
SHELL

# Dockerfile configuration
ENV WEB_DOCUMENT_ROOT=/app/public
ENV PHP_DISMOD=bz2,calendar,exiif,ffi,intl,gettext,ldap,mysqli,imap,soap,sockets,sysvmsg,sysvsm,sysvshm,shmop,xsl,zip,gd,apcu,vips,imagick,mongodb,amqp
RUN install -o application -d /app
WORKDIR /app
USER application

COPY --chown=application . .
COPY --chown=application --from=build-backend /app .
COPY --chown=application --from=build-frontend /app .

RUN <<'SHELL'
    # Create and apply permissions for the storage and cache directories
    mkdir -p /app/storage/framework/sessions
    mkdir -p /app/storage/framework/views
    mkdir -p /app/storage/framework/cache
    mkdir -p /app/storage/logs
    mkdir -p /app/storage/app/purify
    mkdir -p /app/storage/app/purify/HTML
    mkdir -p /app/storage/app/purify/JSON
    chmod -R 775 /app/storage

    # Post build activities and optimisation
    php artisan route:cache 
    php artisan view:cache 
    php artisan event:cache
SHELL

RUN alias pa='php artisan'
