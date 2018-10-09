#!/bin/sh


# after a fresh install of a new virtual machine checkout the phpws project into /var/www/html
# and then run /var/www/html/scripts/install_server_centos7.sh

# for centos 7.x


sudo yum update -y
sudo yum install -y epel-release

# update latest yum
sudo rpm -Uvh https://mirror.webtatic.com/yum/el7/webtatic-release.rpm

# install php on nginx
sudo yum install -y git vim htop nginx php70w php70w-bcmath php70w-cli php70w-common php70w-fpm php70w-mbstring php70w-mcrypt php70w-mysql php70w-xml unzip

# configure nginx conf file
sudo cp /etc/nginx/nginx.conf /etc/nginx/nginx.original.conf
sudo bash -c 'cat /var/www/html/scripts/nginx.conf > /etc/nginx/nginx.conf'

# configure php-fpm conf file
sudo sed -i 's/user \= apache/user \= nginx/g' /etc/php-fpm.d/www.conf
sudo sed -i 's/group \= apache/group \= nginx/g' /etc/php-fpm.d/www.conf
sudo sed -i 's/listen \= 127.0.0.1:9000/listen \= \/var\/run\/php-fpm\/php-fpm.sock/g' /etc/php-fpm.d/www.conf
sudo sed -i 's/;listen.owner \= nobody/listen.owner \= nginx/g' /etc/php-fpm.d/www.conf
sudo sed -i 's/;listen.group \= nobody/listen.group \= nginx/g' /etc/php-fpm.d/www.conf
sudo sed -i 's/;listen.mode \= 0660/listen.mode \= 0664/g' /etc/php-fpm.d/www.conf

# set up creds
sudo groupadd devgroup
sudo usermod -a -G devgroup nginx
sudo chown -R root:devgroup /var/www/html
sudo chmod 775 /var/www/html
sudo chown nginx:nginx /var/lib/php/session

# start the system
sudo service nginx start
sudo service php-fpm start

