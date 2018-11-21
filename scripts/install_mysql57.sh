# run this file only after you run install_server.sh
# this file installs mysql community edition on an EL6-based system, latest version is mysql57, rc11

# more info here:
# https://dev.mysql.com/doc/mysql-repo-excerpt/5.6/en/linux-installation-yum-repo.html
# https://dev.mysql.com/downloads/repo/yum/

#!/bin/sh

# centos6:
sudo rpm -ivh https://dev.mysql.com/get/mysql57-community-release-el6-11.noarch.rpm

# centos7:
sudo rpm -ivh https://dev.mysql.com/get/mysql57-community-release-el7-10.noarch.rpm

sudo yum install -y mysql-community-server
sudo mysqld --initialize
sudo chown mysql:mysql -R /var/lib/mysql

# to reset the user password:

# $ sudo mysqld_safe --skip-grant-tables &
# $ mysql -u root
# mysql> update mysql.user set authentication_string = password("ot-mysql-default-pass"), password_expired = 'N', Host='%' where User='root';
# mysql> flush privileges;
# mysql> exit
# $ sudo service mysqld stop
# $ sudo service mysqld start
