# PHP runtime for the Bulkstream cargo demo. Code is bind-mounted from the host,
# so the image carries only PHP, extensions and Composer.
FROM php:8.4-fpm-bookworm
COPY --from=mlocati/php-extension-installer:2.7.0 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer
RUN install-php-extensions pdo_mysql intl zip bcmath opcache pcntl \
 && apt-get update && apt-get install -y --no-install-recommends unzip git && rm -rf /var/lib/apt/lists/*
RUN { \
      echo 'memory_limit=256M'; \
      echo 'expose_php=Off'; \
      echo 'opcache.enable=1'; \
      echo 'opcache.enable_cli=0'; \
      echo 'opcache.validate_timestamps=1'; \
      echo 'opcache.revalidate_freq=2'; \
    } > /usr/local/etc/php/conf.d/bulkstream.ini \
 && { \
      echo '[www]'; \
      echo 'pm = dynamic'; \
      echo 'pm.max_children = 5'; \
      echo 'pm.start_servers = 2'; \
      echo 'pm.min_spare_servers = 1'; \
      echo 'pm.max_spare_servers = 3'; \
    } > /usr/local/etc/php-fpm.d/zz-bulkstream.conf
WORKDIR /var/www/html
