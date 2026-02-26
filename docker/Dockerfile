FROM php:8.4-fpm

# -------------------------------------------------------
# Match host user UID/GID to avoid bind-mount permission issues
# -------------------------------------------------------
ARG HOST_UID=1000
ARG HOST_GID=1000
RUN groupmod -o -g ${HOST_GID} www-data && \
    usermod -o -u ${HOST_UID} -g ${HOST_GID} www-data

# -------------------------------------------------------
# System dependencies
# -------------------------------------------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
    cron \
    curl \
    default-mysql-client \
    git \
    gnupg2 \
    libfreetype6-dev \
    libicu-dev \
    libjpeg62-turbo-dev \
    libmagickwand-dev \
    libmcrypt-dev \
    libonig-dev \
    libpng-dev \
    libsodium-dev \
    libwebp-dev \
    libxml2-dev \
    libxslt1-dev \
    libzip-dev \
    lsof \
    mailutils \
    msmtp \
    msmtp-mta \
    nginx \
    procps \
    sudo \
    supervisor \
    unzip \
    vim \
    zip \
    && rm -rf /var/lib/apt/lists/*

# -------------------------------------------------------
# Node.js 18 (required for Magento frontend build: grunt, less)
# -------------------------------------------------------
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && npm install -g grunt-cli \
    && rm -rf /var/lib/apt/lists/*

# -------------------------------------------------------
# PHP extensions required by Magento 2.4.8
# -------------------------------------------------------
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        ftp \
        gd \
        intl \
        mbstring \
        opcache \
        pdo_mysql \
        soap \
        sockets \
        sodium \
        xsl \
        zip

# PECL extensions
RUN pecl install imagick && docker-php-ext-enable imagick
RUN pecl install redis   && docker-php-ext-enable redis
RUN pecl install xdebug  && docker-php-ext-enable xdebug

# -------------------------------------------------------
# Composer 2
# -------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# -------------------------------------------------------
# n98-magerun2 (Magento CLI power-tool)
# -------------------------------------------------------
RUN curl -sS -o /usr/local/bin/n98-magerun2 https://files.magerun.net/n98-magerun2.phar \
    && chmod +x /usr/local/bin/n98-magerun2

# -------------------------------------------------------
# PHP configuration
# -------------------------------------------------------
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY docker/php/magento.ini  /usr/local/etc/php/conf.d/magento.ini
COPY docker/php/opcache.ini  /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/xdebug.ini   /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# -------------------------------------------------------
# Nginx configuration
# -------------------------------------------------------
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
RUN rm -f /etc/nginx/sites-enabled/default

# -------------------------------------------------------
# Mail relay → Mailpit
# -------------------------------------------------------
RUN echo "account default"      >  /etc/msmtprc && \
    echo "host mailpit"          >> /etc/msmtprc && \
    echo "port 1025"             >> /etc/msmtprc && \
    echo "auto_from on"          >> /etc/msmtprc && \
    echo "logfile /var/log/msmtp.log" >> /etc/msmtprc

# -------------------------------------------------------
# Supervisor config (manages nginx + php-fpm + cron)
# -------------------------------------------------------
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# -------------------------------------------------------
# Magento cron
# -------------------------------------------------------
COPY docker/cron/magento /etc/cron.d/magento
RUN chmod 0644 /etc/cron.d/magento && crontab -u www-data /etc/cron.d/magento
RUN chown -R www-data:www-data /var/www

# -------------------------------------------------------
# Working directory & permissions
# -------------------------------------------------------
WORKDIR /var/www/html
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html

# -------------------------------------------------------
# Shared group: root joins www-data so both users can
# read/write the same Magento directories via group perms
# -------------------------------------------------------
RUN usermod -aG www-data root
RUN echo 'umask 002' >> /root/.bashrc

# -------------------------------------------------------
# Entrypoint
# -------------------------------------------------------
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80 9003

ENTRYPOINT ["entrypoint.sh"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
