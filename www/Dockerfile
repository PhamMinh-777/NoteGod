FROM php:8.4-apache
RUN docker-php-ext-install mysqli pdo pdo_mysql && a2enmod rewrite \
    && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
