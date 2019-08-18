FROM tus-php-base

## Redis Installation
RUN apk add --no-cache redis

## Expose environment variables
ENV REDIS_HOST tus-php-redis
ENV REDIS_PORT 6379

## Configurations
COPY ./configs/default.conf /etc/nginx/conf.d/default.conf

ENV DOC_ROOT /var/www
ENV SOURCE_CODE ${DOC_ROOT}/html
RUN mkdir -p ${SOURCE_CODE}
VOLUME ${SOURCE_CODE}

WORKDIR ${DOC_ROOT}
RUN mkdir -p ${DOC_ROOT}/uploads
RUN chown -R www-data:root ${DOC_ROOT}/uploads

COPY ./entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
