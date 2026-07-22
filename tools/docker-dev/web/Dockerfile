FROM ubuntu:24.04

# Web Server Setup
ARG DEBIAN_FRONTEND=noninteractive

RUN sed -i 's/ports.ubuntu.com/mirrors.ocf.berkeley.edu/' /etc/apt/sources.list.d/ubuntu.sources

# I would use ADD to download the key but ADD always invalidates the cache
# I don't really care about this getting stale
COPY node.sources /etc/apt/sources.list.d/node.sources
RUN apt-get update && apt-get install -y wget gnupg ca-certificates && \
    wget https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key -O /tmp/nodesource-repo.gpg.key && \
    mkdir -p /etc/apt/keyrings && \
    gpg --dearmor --yes --output /etc/apt/keyrings/node.asc /tmp/nodesource-repo.gpg.key

# php extensions also listed in .github/workflows/phpunit.yml and README.md
RUN apt-get update && apt-get install -y \
    apache2 \
    apache2-utils \
    php \
    php-curl \
    php-mysql \
    php-ldap \
    php-pdo \
    php-cli \
    php-mbstring \
    php-xml \
    php-intl \
    php-xdebug \
    composer \
    nodejs
COPY htpasswd /etc/apache2/.htpasswd
RUN chown www-data /etc/apache2/.htpasswd
COPY unity-apache.conf /etc/apache2/sites-available/unity.conf
RUN a2dissite 000-default
RUN a2ensite unity
RUN echo "ServerName 127.0.0.1" >> /etc/apache2/apache2.conf

RUN sed -i '/display_errors/c\display_errors = on' /etc/php/8.3/apache2/php.ini
RUN sed -i '/zend.assertions/c\zend.assertions = 1' /etc/php/8.3/apache2/php.ini
RUN sed -i '/zend.assertions/c\zend.assertions = 1' /etc/php/8.3/cli/php.ini
RUN sed -i '/memory_limit/c\memory_limit = -1' /etc/php/8.3/apache2/php.ini
RUN sed -i '/zend.exception_ignore_args/c\zend.exception_ignore_args = 0' /etc/php/8.3/apache2/php.ini
RUN sed -i '/zend.exception_ignore_args/c\zend.exception_ignore_args = 0' /etc/php/8.3/cli/php.ini
RUN echo 'xdebug.mode=develop,coverage,debug,gcstats,profile,trace' >> /etc/php/8.3/cli/php.ini
RUN echo 'xdebug.mode=develop,coverage,debug,gcstats,profile,trace' >> /etc/php/8.3/apache2/php.ini

RUN mkdir -p /srv/www/unity-web

COPY composer.json /srv/www/unity-web/composer.json
COPY composer.lock /srv/www/unity-web/composer.lock
RUN cd /srv/www/unity-web && COMPOSER_ALLOW_SUPERUSER=1 composer --dev --no-scripts --no-plugins install

COPY package.json /srv/www/unity-web/package.json
COPY package-lock.json /srv/www/unity-web/package-lock.json
COPY copy-node_modules-to-webroot.bash /srv/www/unity-web/tools/copy-node_modules-to-webroot.bash
RUN cd /srv/www/unity-web && npm install
RUN cd /srv/www/unity-web && mkdir webroot && mkdir webroot/js && mkdir webroot/css
RUN cd /srv/www/unity-web && ./tools/copy-node_modules-to-webroot.bash

# Start apache2 server
EXPOSE 80

CMD ["apache2ctl", "-D", "FOREGROUND"]
