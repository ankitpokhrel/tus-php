FROM php:7.2-fpm-alpine

LABEL maintainer="hello@ankit.pl,samundra@msn.com" \
  description="This builds tus-php-base image"

ENV LANG C.UTF-8
ENV LC_ALL C.UTF-8
ENV PHP_INI_DIR /usr/local/etc/php
ENV COMPOSER_ALLOW_SUPERUSER 1

RUN ln -sf /usr/share/zoneinfo/Asia/Kathmandu /etc/localtime

RUN apk update && apk add --no-cache \
  make \
  supervisor \
  nginx \
  composer \
  curl \
  ca-certificates

# Install required extensions.
RUN docker-php-ext-install pcntl

# Install extensions not available with docker-php-ext.
COPY ./bin/php-ext.sh /bin/php-ext
RUN chmod +x /bin/php-ext && php-ext

COPY ./bin/xdebug.sh /bin/xdebug
RUN chmod +x /bin/xdebug

# There are some default configurations from the image that will override
# our php-fpm settings and we don't want that. To avoid that, we will
# delete all configurations available by default. Also, we will
# create a folder for the php-fpm socket.
RUN mkdir -p /var/run/php-fpm && rm -rf /usr/local/etc/php-fpm.d/*.conf

COPY ./configs/supervisord.conf /etc/supervisord.conf
COPY ./configs/nginx.conf /etc/nginx/nginx.conf
COPY ./configs/php.ini $PHP_INI_DIR/php.ini
COPY ./configs/xdebug.2.ini $PHP_INI_DIR/conf.d/00_xdebug.disable
COPY ./configs/www.conf /usr/local/etc/php-fpm.d/www.conf

ENTRYPOINT [ "sh" ]
