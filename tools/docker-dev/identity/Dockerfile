FROM ubuntu:24.04

# OpenLDAP Server
ARG DEBIAN_FRONTEND=noninteractive
RUN apt-get update && apt-get install -y \
    slapd \
    ldap-utils \
    apache2 \
    phpldapadmin \
    netcat-openbsd
RUN rm -rf /var/lib/ldap
RUN mkdir /var/lib/ldap
RUN chown openldap:openldap /var/lib/ldap
COPY ssh.ldif /etc/ldap/schema/ssh.ldif
COPY unity-cluster-schema.ldif /etc/ldap/schema/unity-cluster.ldif
COPY ldap-config.ldif /tmp/ldap-config.ldif
COPY bootstrap.ldif /tmp/bootstrap.ldif
COPY load-modules.ldif /tmp/load-modules.ldif
COPY configure-unique.ldif /tmp/configure-unique.ldif

RUN service slapd start; \
    ldapadd    -Y EXTERNAL -H ldapi:/// -f /etc/ldap/schema/cosine.ldif; \
    ldapadd    -Y EXTERNAL -H ldapi:/// -f /etc/ldap/schema/nis.ldif; \
    ldapadd    -Y EXTERNAL -H ldapi:/// -f /etc/ldap/schema/inetorgperson.ldif; \
    ldapadd    -Y EXTERNAL -H ldapi:/// -f /etc/ldap/schema/ssh.ldif; \
    ldapadd    -Y EXTERNAL -H ldapi:/// -f /etc/ldap/schema/unity-cluster.ldif; \
    ldapmodify -Y EXTERNAL -H ldapi:/// -f /tmp/ldap-config.ldif; \
    ldapmodify -Y EXTERNAL -H ldapi:/// -f /tmp/load-modules.ldif; \
    ldapadd    -Y EXTERNAL -H ldapi:/// -f /tmp/configure-unique.ldif; \
    slapadd                             -l /tmp/bootstrap.ldif

# PHPLDAPAdmin Setup
RUN rm -rf /etc/phpldapadmin/config.php
COPY phpldapadmin-config.php /etc/phpldapadmin/config.php
COPY phpldapadmin-apache.conf /etc/apache2/sites-available/phpldapadmin.conf
RUN a2dissite 000-default
RUN a2ensite phpldapadmin
RUN echo "ServerName 127.0.0.1" >> /etc/apache2/apache2.conf
RUN sed -i '/memory_limit/c\memory_limit = -1' /etc/php/8.3/apache2/php.ini

EXPOSE 80
EXPOSE 389

CMD apache2ctl -D FOREGROUND & slapd -h "ldap:/// ldapi:///" -u openldap -g openldap -d 0
