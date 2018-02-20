sudo service nginx stop
sudo service php-fpm stop
sudo yum install -y epel-release redis php70w-pear
sudo pecl install igbinary igbinary-devel redis 
sudo bash -c 'echo -e "\nextension=igbinary.so" >> /etc/php.ini'
sudo bash -c 'echo -e "\nextension=redis.so" >> /etc/php.ini'
sudo service php-fpm start
sudo service nginx start
