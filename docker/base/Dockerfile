FROM phpearth/php:7.3-nginx

LABEL maintainer="hello@ankit.pl,samundra@msn.com" \
  description="This builds tus-php-base image"

ENV LANG C.UTF-8
ENV LC_ALL C.UTF-8
ENV PHP_INI_DIR /etc/php/7.3
ENV COMPOSER_ALLOW_SUPERUSER 1

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

# Install APCu and Xdebug extension.
RUN pecl upgrade apcu xdebug

COPY ./bin/xdebug.sh /bin/xdebug
RUN chmod +x /bin/xdebug

COPY ./configs/nginx.conf /etc/nginx/nginx.conf
COPY ./configs/php.ini $PHP_INI_DIR/php.ini
COPY ./configs/xdebug.ini $PHP_INI_DIR/conf.d/00_xdebug.disable
COPY ./configs/www.conf $PHP_INI_DIR/php-fpm.d/www.conf

ENTRYPOINT [ "sh" ]
