FROM php:8.2-apache

RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY . .

RUN docker-php-ext-install mysqli

RUN npm install --omit=dev

# Enable exactly one MPM
RUN rm -f /etc/apache2/mods-enabled/mpm_*.load \
          /etc/apache2/mods-enabled/mpm_*.conf \
    && a2enmod mpm_prefork \
    && a2enmod rewrite \
    && apache2ctl configtest \
    && apache2ctl -M | grep mpm

RUN a2dissite 000-default \
    && rm -f /etc/apache2/sites-available/000-default.conf

RUN printf '%s\n' \
    '<VirtualHost *:80>' \
    '    ServerName localhost' \
    '    DocumentRoot /app/CODE/PHP' \
    '    <Directory /app/CODE/PHP>' \
    '        AllowOverride All' \
    '        Require all granted' \
    '        DirectoryIndex index.php' \
    '    </Directory>' \
    '</VirtualHost>' \
    > /etc/apache2/sites-available/nexgen.conf

RUN a2ensite nexgen \
    && apache2ctl configtest

EXPOSE 80

CMD ["apache2-foreground"]