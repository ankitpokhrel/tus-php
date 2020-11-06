FROM php:7.3-fpm-alpine

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
  php7-imap \
  php7-intl \
  php7-curl \
  composer \
  curl \
  ca-certificates

# Install required extensions.
RUN docker-php-ext-install pcntl

# Install pecl extensions.
#
# Some dependencies like c-compiler and autoconf are only required
# to compile pecl extensions. However, these dependencies take a
# lot of space. Therefore, we will remove these dependencies
# after installing the required extensions.
RUN apk add --no-cache g++ autoconf && \
  pecl upgrade apcu xdebug && \
  apk del g++ autoconf

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
COPY ./configs/xdebug.ini $PHP_INI_DIR/conf.d/00_xdebug.disable
COPY ./configs/www.conf /usr/local/etc/php-fpm.d/www.conf

ENTRYPOINT [ "sh" ]
