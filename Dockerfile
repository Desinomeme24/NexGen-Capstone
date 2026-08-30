# Use official PHP image with Apache
FROM php:8.2-apache

# Install Node.js and npm
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - && \
    apt-get install -y nodejs && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /app

# Copy application files
COPY . .

# Install PHP extensions (if needed for MySQL)
RUN docker-php-ext-install mysqli

# Install npm dependencies
RUN npm install --production

# Disable default Apache modules that might conflict
RUN a2dismod mpm_prefork mpm_worker mpm_event 2>/dev/null || true

# Enable mpm_prefork (single threading, safest for PHP)
RUN a2enmod mpm_prefork

# Disable Apache's default config
RUN a2dissite 000-default

# Remove default Apache config
RUN rm -f /etc/apache2/sites-available/000-default.conf

# Create Apache config to serve from CODE/PHP directory
RUN echo '<VirtualHost *:80>\n\
    ServerName localhost\n\
    DocumentRoot /app/CODE/PHP\n\
    <Directory /app/CODE/PHP>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    <FilesMatch "\.php$">\n\
        SetHandler application/x-httpd-php\n\
    </FilesMatch>\n\
</VirtualHost>' > /etc/apache2/sites-available/nexgen.conf

# Enable the new config
RUN a2ensite nexgen

# Enable mod_rewrite
RUN a2enmod rewrite

# Expose port 80 (Railway will map to PORT env variable)
EXPOSE 80

# Start Apache in foreground
CMD ["apache2-foreground"]

