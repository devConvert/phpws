#!/bin/sh

# run this file only after you run install_server_centos7.sh
# this file installs mysql community edition on an EL7-based system, latest version is mysql8

sudo rpm -ivh https://dev.mysql.com/get/mysql80-community-release-el7-1.noarch.rpm
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
