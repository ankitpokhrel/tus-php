FROM tus-php-base

ADD ./configs/nginx.conf /etc/nginx/nginx.conf

COPY ./entrypoint.sh /usr/bin/entrypoint.sh
RUN chmod +x /usr/bin/entrypoint.sh

WORKDIR /var/www

EXPOSE 80
