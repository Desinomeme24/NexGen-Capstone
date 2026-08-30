FROM php:8.2-apache

# Install Node.js and npm.
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY . .

# MySQL support for the PHP application.
RUN docker-php-ext-install mysqli

# Install only production Node dependencies.
RUN npm install --omit=dev

# Ensure Apache loads exactly one MPM and enable URL rewriting.
RUN rm -f /etc/apache2/mods-enabled/mpm_*.load \
          /etc/apache2/mods-enabled/mpm_*.conf \
    && a2enmod mpm_prefork rewrite \
    && apache2ctl configtest \
    && apache2ctl -M | grep mpm

# Serve the NexGen PHP application.
RUN a2dissite 000-default \
    && rm -f /etc/apache2/sites-available/000-default.conf \
    && printf '%s\n' \
        '<VirtualHost *:80>' \
        '    ServerName localhost' \
        '    DocumentRoot /app/CODE/PHP' \
        '    <Directory /app/CODE/PHP>' \
        '        AllowOverride All' \
        '        Require all granted' \
        '        DirectoryIndex index.php' \
        '    </Directory>' \
        '</VirtualHost>' \
        > /etc/apache2/sites-available/nexgen.conf \
    && a2ensite nexgen \
    && apache2ctl configtest

EXPOSE 80

# Normalize the MPM again at runtime before starting Apache. This protects
# against startup-time configuration overrides in the deployment environment.
CMD ["sh", "-c", "rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf && a2enmod mpm_prefork && apache2ctl configtest && exec apache2-foreground"]
