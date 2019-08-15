FROM tus-php-base

COPY ./configs/nginx.conf /etc/nginx/nginx.conf
COPY ./configs/client.conf /etc/nginx/conf.d/client.conf

EXPOSE 80
ENV SERVER_URL http://0.0.0.0:8081

WORKDIR /var/www/example
