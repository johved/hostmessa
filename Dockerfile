FROM php:8.3-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends libjpeg62-turbo-dev libpng-dev libwebp-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" gd exif pdo_mysql \
 && rm -rf /var/lib/apt/lists/*

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/hostmessa.ini"
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/hostmessa-entrypoint
RUN chmod +x /usr/local/bin/hostmessa-entrypoint

COPY src/ /var/www/src/
COPY public/ /var/www/public/

ENTRYPOINT ["hostmessa-entrypoint"]
CMD ["apache2-foreground"]
