FROM phpearth/php:7.4-nginx

LABEL maintainer="hello@ankit.pl,samundra@msn.com" \
  description="This builds tus-php-base image"

ENV LANG C.UTF-8
ENV LC_ALL C.UTF-8

ADD https://repos.php.earth/alpine/phpearth.rsa.pub /etc/apk/keys/phpearth.rsa.pub

RUN ln -sf /usr/share/zoneinfo/Asia/Kathmandu /etc/localtime
RUN set -x \
    && echo "https://uk.alpinelinux.org/alpine/v3.9/community" >> /etc/apk/repositories \
    && echo "https://uk.alpinelinux.org/alpine/v3.9/main" >> /etc/apk/repositories \
    && apk add --no-cache $DEPS \
    && ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log

RUN apk update && apk add --no-cache \
  php7.4-imap \
  php7.4-intl \
  php7.4-mbstring \
  php7.4-openssl \
  php7.4-ldap \
  php7.4-curl \
  php7.4-pcntl \
  php7.4-dev \
  php7.4-pear \
  composer

# Install APCu extension
RUN pecl upgrade apcu

## Xdebug Installation, Currently Alpine repo is missing Php7.4-xdebug,
# RUN apk add --no-cache php7.4-xdebug

COPY ./configs/nginx.conf /etc/nginx/nginx.conf

ENV PHP_INI_DIR /etc/php/7.4

COPY ./configs/php.ini $PHP_INI_DIR/php.ini
COPY ./configs/www.conf $PHP_INI_DIR/php-fpm.d/www.conf

ENTRYPOINT [ "sh" ]
