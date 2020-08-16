FROM phpearth/php:7.3-nginx

LABEL maintainer="hello@ankit.pl,samundra@msn.com" \
  description="This builds tus-php-base image"

ENV LANG C.UTF-8
ENV LC_ALL C.UTF-8

ADD https://repos.php.earth/alpine/phpearth.rsa.pub /etc/apk/keys/phpearth.rsa.pub

RUN ln -sf /usr/share/zoneinfo/Asia/Kathmandu /etc/localtime

RUN apk update && apk add --no-cache \
  php7.3-imap \
  php7.3-intl \
  php7.3-mbstring \
  php7.3-openssl \
  php7.3-ldap \
  php7.3-curl \
  php7.3-pcntl \
  php7.3-dev \
  php7.3-pear \
  composer

# Install APCu extension
RUN pecl upgrade apcu

## Xdebug Installation, Currently Alpine repo is missing Php7.4-xdebug,
# RUN apk add --no-cache php7.4-xdebug

COPY ./configs/nginx.conf /etc/nginx/nginx.conf

ENV PHP_INI_DIR /etc/php/7.3

COPY ./configs/php.ini $PHP_INI_DIR/php.ini
COPY ./configs/www.conf $PHP_INI_DIR/php-fpm.d/www.conf

ENTRYPOINT [ "sh" ]
