FROM php:8.1-fpm

COPY [".", "./"]

RUN apt-get update \
    && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    cron \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install pdo_mysql

#/proc/1/fd/1 2>/proc/1/fd/2 is used to redirect cron logs to standard output and standard error
RUN (crontab -l ; echo "*/1 * * * * /usr/local/bin/php /var/www/html/ctb.php  > /proc/1/fd/1 2>/proc/1/fd/2") | crontab

#--------------- Start Cron ------------------
# Grant execution rights
RUN chmod 755 startup.sh
CMD ["./startup.sh"]
