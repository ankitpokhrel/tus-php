FROM tus-php-base

COPY ./entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

RUN mkdir -p /var/www/html
RUN mkdir -p /var/www/uploads && chmod 777 -R /var/www/uploads

VOLUME /var/www/html

WORKDIR /var/www
