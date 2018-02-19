sudo service nginx stop
sudo service php-fpm stop
sudo yum install -y zlib zlib-devel libevent libevent-devel memcached php70w-devel php70w-pear libmemcached libmemcached-devel gcc
sudo pecl install memcached
# [libmemcached directory = /usr]
sudo bash -c 'echo -e "\nextension=memcache.so" >> /etc/php.ini'
sudo service php-fpm start
sudo service nginx start
