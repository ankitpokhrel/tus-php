FROM tus-php-base

# RUN apk add --no-cache php-xdebug
# Redis Installation
RUN apk add --no-cache redis

## Composer installation
RUN apk add --no-cache php7.2-pcntl composer

# Install Composer
# RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && chmod +x /usr/local/bin/composer

## Expose environment variables
ENV REDIS_HOST tus-php-redis
ENV REDIS_PORT 6379

# Configurations
ENV PHP_INI_DIR /etc/php/7.2

COPY ./configs/php.ini $PHP_INI_DIR/php.ini
COPY ./configs/www.conf $PHP_INI_DIR/php-fpm.d/www.conf
COPY ./configs/default.conf /etc/nginx/conf.d/default.conf
COPY ./configs/nginx.conf /etc/nginx/nginx.conf

ENV DOC_ROOT /var/www
ENV SOURCE_CODE ${DOC_ROOT}/html
RUN mkdir -p ${SOURCE_CODE}
VOLUME ${SOURCE_CODE}

WORKDIR ${DOC_ROOT}
RUN mkdir -p ${DOC_ROOT}/uploads
RUN chmod 777 ${DOC_ROOT}/uploads

COPY ./entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
