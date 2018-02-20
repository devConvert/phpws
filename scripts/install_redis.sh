sudo service nginx stop
sudo service php-fpm stop
sudo yum install -y epel-release redis php70w-devel php70w-pear gcc
sudo pecl install igbinary igbinary-devel redis
[ enable igbinary serializer support? YES! ]
# sudo bash -c 'echo -e "\nextension=igbinary.so" >> /etc/php.ini'
sudo bash -c 'echo -e "\nextension=redis.so" >> /etc/php.ini'
sudo service php-fpm start
sudo service nginx start
