FROM ubuntu:24.04

ARG DEBIAN_FRONTEND=noninteractive
RUN apt-get update && apt-get install -y \
    mariadb-server \
    mariadb-client \
    apache2 \
    php8.3 \
    phpmyadmin \
    netcat-openbsd
RUN sed -i '/bind-address/c\bind-address = 0.0.0.0' /etc/mysql/mariadb.conf.d/50-server.cnf

# PHPMYadmin Setup
COPY phpmyadmin-config.php /etc/phpmyadmin/config.inc.php
COPY phpmyadmin-apache.conf /etc/apache2/sites-available/phpmyadmin.conf
RUN a2dissite 000-default
RUN a2ensite phpmyadmin
RUN echo "ServerName 127.0.0.1" >> /etc/apache2/apache2.conf

COPY bootstrap.sql /tmp/bootstrap.sql
COPY bootstrap-users.sql /tmp/bootstrap-users.sql

# bootstrap.sql uses current_timestamp so it should not be cached
# this random ADD busts the cache: https://stackoverflow.com/a/58801213/18696276
ADD "https://www.random.org/cgi-bin/randbyte?nbytes=10&format=h" skipcache
RUN service mariadb start; \
    mariadb -e "CREATE DATABASE unity"; \
    mariadb -e "CREATE USER 'unity'@'%' IDENTIFIED BY 'password'"; \
    mariadb -e "GRANT ALL PRIVILEGES ON unity.* TO 'unity'@'%'"; \
    mariadb -e "FLUSH PRIVILEGES"; \
    mariadb unity < /tmp/bootstrap.sql; \
    mariadb unity < /tmp/bootstrap-users.sql

RUN rm -rf /tmp/bootstrap.sql

EXPOSE 80
EXPOSE 3306

CMD apache2ctl -D FOREGROUND & mysqld --user=root
