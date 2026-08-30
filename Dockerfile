FROM php:8.2-apache

# Install Node.js and npm.
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY . .

# Stop the build if NexGen's entry point is missing.
RUN test -f /app/CODE/PHP/index.php

# Install PHP MySQL extensions.
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Install production Node dependencies.
RUN npm install --omit=dev

# Copy the PHP application into Apache's standard web root.
RUN rm -rf /var/www/html/* \
    && cp -a /app/CODE/PHP/. /var/www/html/ \
    && chown -R www-data:www-data /var/www/html

# Enable exactly one MPM and Apache URL rewriting.
RUN rm -f /etc/apache2/mods-enabled/mpm_*.load \
          /etc/apache2/mods-enabled/mpm_*.conf \
    && a2enmod mpm_prefork rewrite \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' \
        /etc/apache2/apache2.conf \
    && apache2ctl configtest \
    && apache2ctl -M | grep mpm

EXPOSE 80

CMD ["sh", "-c", "rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf && a2enmod mpm_prefork && apache2ctl configtest && exec apache2-foreground"]