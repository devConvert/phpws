sudo service nginx stop
sudo service php-fpm stop
sudo yum install -y epel-release redis
sudo pecl install igbinary igbinary-devel redis php70w-pear
sudo bash -c 'echo -e "\nextension=igbinary.so" >> /etc/php.ini'
sudo bash -c 'echo -e "\nextension=redis.so" >> /etc/php.ini'
sudo service php-fpm start
sudo service nginx start
