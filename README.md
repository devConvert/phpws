# The phpws framework

phpws is a php framework that provides the following infrastructure:
* mvc with a base controller
* db connectivity: mysql, redis, memcached
* aws integration: ses, s3
* log service for sending and receiving logs
* rolling file logger
* geoip
* user agent parsing (MobileDetect)
* url routing
* crons management
* various linux maintenance scripts

## Installation Instructions

### phpws + nginx + php7

To install the Offertoro middletier web service, first ssh into a fresh virtual machine and then:
```
$ sudo mkdir /var/www
$ sudo mkdir /var/www/html
$ sudo yum install -y git
$ sudo git clone https://github.com/offertoro/phpws.git /var/www/html
```
Then enter email and password of your github user. Your user must be a contributer to the phpws repo
in order for it to checkout. Then run the server main installation script:
```
$ sudo /var/www/html/scripts/install_server.sh
```
To test if the server is up and running navigate to `localhost/ws/1/base/ping`. The result should
be `{"code":200,"value":"pong"}`.

### mysql57

To install mysql run:
```
$ sudo /var/www/html/scripts/install_mysql.sh
```
You'll probably need to reset the root password. So get the info here:
```
$ sudo cat /var/www/html/scripts/install_mysql.sh
```

## How to use phpws as a platform for other projects

The "includes" directory is git ignored, so creating it wouldn't change the status of the local branch.
First make sure the path `/var/www/html/includes` exists and create some files there:

* `/var/www/html/includes/config.php` - this file will be included automatically in the web service if it exists and can hold
routing and db configurations.

* `/var/www/html/includes/crontab.txt` - this file should hold all crons.

* `/var/www/html/includes/{CtrlName}ControllerV{VersionNum}.php` - a controller reachable at the following uri: localhost/ws/{VersionNum}/{CtrlName}/{Method}.

For example the file /var/www/html/includes/MainControllerV1.php:
```
<?php

class MainControllerV1 extends BaseControllerV1{
    public function test(){
        die();
    }

    public function test2($p1, $p2){
        die($p1 . $p2);
    }
}
```
The "test" method is reachable at `localhost/ws/1/main/test`.
Note that the first letter in {CtrlName} in the file name and class declaration must be capital while other letters lowercased but in the uri all letters are lowercased.

The "test2" method is reachable at `localhost/ws/1/main/test2/a/b` and outputs "ab".

## Managing crons

Running crons run on the nginx user.
There are 5 methods for managing crons on the local host:
* `localhost/ws/1/base/get_crons` - gets all crons saved to the file /var/www/html/includes/crontab.txt and also all crons currently running.

* `localhost/ws/1/base/start_crons` - start all crons saved to the file.

* `localhost/ws/1/base/stop_crons` - stop all crons.

* `localhost/ws/1/base/add_cron_to_file/{b64_cron}` - add another cron to the file. The {b64_cron} parameter is the base64 representation of the cron format. For example, to add a cron with the format `0 */1 * * * sudo service php-fpm reload` the following uri should be invoked `localhost/ws/1/base/add_cron_to_file/MCAqLzEgKiAqICogc3VkbyBzZXJ2aWNlIHBocC1mcG0gcmVsb2Fk`.
The added cron isn't started automatically.

* `localhost/ws/1/base/remove_cron_from_file/{b64_cron}` - removes a cron from the file.
