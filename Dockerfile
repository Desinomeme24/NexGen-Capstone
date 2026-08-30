FROM php:8.2-apache

RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY . .

RUN docker-php-ext-install mysqli

RUN npm install --omit=dev

RUN rm -f \
        /etc/apache2/mods-enabled/mpm_event.load \
        /etc/apache2/mods-enabled/mpm_event.conf \
        /etc/apache2/mods-enabled/mpm_worker.load \
        /etc/apache2/mods-enabled/mpm_worker.conf \
    && a2enmod mpm_prefork rewrite

RUN a2dissite 000-default \
    && rm -f /etc/apache2/sites-available/000-default.conf

RUN echo '<VirtualHost *:80>\n\
    ServerName localhost\n\
    DocumentRoot /app/CODE/PHP\n\
    <Directory /app/CODE/PHP>\n\
        AllowOverride All\n\
        Require all granted\n\
        DirectoryIndex index.php\n\
    </Directory>\n\
    <FilesMatch "\.php$">\n\
        SetHandler application/x-httpd-php\n\
    </FilesMatch>\n\
</VirtualHost>' > /etc/apache2/sites-available/nexgen.conf

RUN a2ensite nexgen

EXPOSE 80

CMD ["apache2-foreground"]