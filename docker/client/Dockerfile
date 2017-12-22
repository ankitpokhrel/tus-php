FROM tus-php-base

ADD ./configs/nginx.conf /etc/nginx/nginx.conf

WORKDIR /var/www/example

EXPOSE 80

ENTRYPOINT ["/usr/bin/supervisord", "-n", "-c",  "/etc/supervisord.conf"]
