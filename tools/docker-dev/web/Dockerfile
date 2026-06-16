FROM ubuntu:24.04

# Web Server Setup
ARG DEBIAN_FRONTEND=noninteractive
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
    php-xdebug
COPY htpasswd /etc/apache2/.htpasswd
RUN chown www-data /etc/apache2/.htpasswd
COPY unity-apache.conf /etc/apache2/sites-available/unity.conf
RUN a2dissite 000-default
RUN a2ensite unity
RUN a2enmod rewrite
RUN a2enmod actions
RUN echo "ServerName 127.0.0.1" >> /etc/apache2/apache2.conf

RUN sed -i '/display_errors/c\display_errors = on' /etc/php/8.3/apache2/php.ini
RUN sed -i '/zend.assertions/c\zend.assertions = 1' /etc/php/8.3/apache2/php.ini
RUN sed -i '/zend.assertions/c\zend.assertions = 1' /etc/php/8.3/cli/php.ini
RUN sed -i '/memory_limit/c\memory_limit = -1' /etc/php/8.3/apache2/php.ini
RUN sed -i '/zend.exception_ignore_args/c\zend.exception_ignore_args = 0' /etc/php/8.3/apache2/php.ini
RUN sed -i '/zend.exception_ignore_args/c\zend.exception_ignore_args = 0' /etc/php/8.3/cli/php.ini
RUN echo 'xdebug.mode=develop,coverage,debug,gcstats,profile,trace' >> /etc/php/8.3/cli/php.ini
RUN echo 'xdebug.mode=develop,coverage,debug,gcstats,profile,trace' >> /etc/php/8.3/apache2/php.ini

# Start apache2 server
EXPOSE 80

CMD ["apache2ctl", "-D", "FOREGROUND"]
