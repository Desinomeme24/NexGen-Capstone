#!/bin/bash
# Install npm dependencies first
npm install --production

# Start PHP server on the port Railway provides
php -S 0.0.0.0:${PORT:-8080} -t CODE/PHP
