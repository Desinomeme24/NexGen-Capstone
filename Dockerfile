FROM php:8.2-apache

ENV NEXGEN_PRIVATE_UPLOAD_DIR=/var/lib/nexgen/private \
    NEXGEN_PUBLIC_UPLOAD_DIR=/var/www/html/uploads

# Install Node.js and npm.
RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl \
    && curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY . .

# Stop the build if NexGen's required application folders are missing.
RUN test -f /app/CODE/PHP/index.php \
    && test -d /app/CODE/JS \
    && test -d /app/CODE/STYLE \
    && test -d /app/IMAGES

# Install PHP MySQL extensions.
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Install production Node dependencies.
RUN npm install --omit=dev

# Publish the PHP application and all browser-accessible assets. The source
# layout is mapped to root-level URLs such as /JS, /STYLE, and /IMAGES.
# VIDEOS is intentionally excluded from the deployed image.
RUN rm -rf /var/www/html/* \
    && cp -a /app/CODE/PHP/. /var/www/html/ \
    && cp -a /app/CODE/JS /var/www/html/JS \
    && cp -a /app/CODE/STYLE /var/www/html/STYLE \
    && cp -a /app/IMAGES /var/www/html/IMAGES \
    && rm -rf /var/www/html/uploads/valid_ids \
    && mkdir -p /var/lib/nexgen/private/valid_ids /var/www/html/uploads/products \
    && printf '%s\n' \
        'RewriteEngine On' \
        'RewriteRule ^nx-control-1407/?$ admin_login.php [END,NC]' \
        'RewriteCond %{THE_REQUEST} \s/+admin_login\.php(?:[?\s]|$) [NC]' \
        'RewriteRule ^admin_login\.php$ - [R=404,L,NC]' \
        'RewriteRule ^uploads/valid_ids(?:/|$) - [R=404,L,NC]' \
        > /var/www/html/.htaccess \
    && chown -R www-data:www-data /var/www/html /var/lib/nexgen

# Enable exactly one MPM and Apache URL rewriting.
RUN rm -f /etc/apache2/mods-enabled/mpm_*.load \
          /etc/apache2/mods-enabled/mpm_*.conf \
    && a2enmod mpm_prefork rewrite \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' \
        /etc/apache2/apache2.conf \
    && apache2ctl configtest \
    && apache2ctl -M | grep mpm

EXPOSE 80

CMD ["sh", "-c", "PORT=\"${PORT:-80}\"; rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf; a2enmod mpm_prefork; sed -ri \"s/Listen 80/Listen ${PORT}/\" /etc/apache2/ports.conf; sed -ri \"s/<VirtualHost \\\\*:80>/<VirtualHost *:${PORT}>/\" /etc/apache2/sites-available/000-default.conf; apache2ctl configtest; exec apache2-foreground"]
