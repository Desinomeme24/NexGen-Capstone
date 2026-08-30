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

# Disable Apache's default config
RUN a2dissite 000-default

# Create Apache config to serve from CODE/PHP directory
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /app/CODE/PHP\n\
    <Directory /app/CODE/PHP>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/nexgen.conf

# Enable the new config
RUN a2ensite nexgen

# Enable mod_rewrite if needed
RUN a2enmod rewrite

# Expose port 8080 (Railway uses this)
EXPOSE 8080

# Start Apache on port 8080
ENV APACHE_PORT=8080
CMD ["apache2-foreground"]
