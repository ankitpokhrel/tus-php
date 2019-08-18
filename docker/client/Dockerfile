FROM tus-php-base

COPY ./configs/default.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
ENV SERVER_URL http://0.0.0.0:8081

WORKDIR /var/www/example

CMD ["/sbin/runit-wrapper"]
