FROM tus-php-base

# ADD ./configs/nginx.conf /etc/nginx/nginx.conf
# php7.1-pcntl
RUN apk add --no-cache php7.1-pcntl
COPY ./entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
RUN mkdir -p /var/www/html
VOLUME /var/www/html

WORKDIR /var/www/html
