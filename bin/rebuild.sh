echo 'Stop and remove tus-php containers ...'
docker stop tus-php-server tus-php-client tus-php-base tus-php-base tus-php-redis >> /dev/null
docker rm tus-php-server tus-php-client tus-php-base tus-php-base tus-php-redis >> /dev/null

echo 'Remove tus-php docker images ...'
docker rmi tusphp_tus-server tusphp_tus-client tus-php-base >> /dev/null
