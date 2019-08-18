FROM phpearth/php:7.2-nginx

LABEL maintainer="hello@ankit.pl,samundra@msn.com" \
  description="This builds tus-php-base image"

ENV LANG C.UTF-8
ENV LC_ALL C.UTF-8

RUN ln -sf /usr/share/zoneinfo/Asia/Kathmandu /etc/localtime

RUN apk add --no-cache \
  php7.2-gd \
  php7.2-imap \
  php7.2-intl \
  php7.2-mcrypt \
  php7.2-mbstring \
  php7.2-ldap \
  php7.2-soap \
  php7.2-curl

## Composer installation
RUN apk add --no-cache php7.2-pcntl composer

COPY ./configs/nginx.conf /etc/nginx/nginx.conf

ENV PHP_INI_DIR /etc/php/7.2

COPY ./configs/php.ini $PHP_INI_DIR/php.ini
COPY ./configs/www.conf $PHP_INI_DIR/php-fpm.d/www.conf
