FROM tus-php-base

COPY ./configs/default.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
ENV SERVER_URL http://0.0.0.0:8081

WORKDIR /var/www/html

ENTRYPOINT ["/usr/bin/supervisord", "-n", "-c",  "/etc/supervisord.conf"]
