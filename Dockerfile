FROM php:8.1-fpm
COPY . /usr/src/myapp
WORKDIR /usr/src/myapp
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    cron \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install pdo_mysql
# Run every day at 1AM
#/proc/1/fd/1 2>/proc/1/fd/2 is used to redirect cron logs to standard output and standard error
RUN (crontab -l ; echo "*/15 * * * * php /usr/src/myapp/ctb.php  > /proc/1/fd/1 2>/proc/1/fd/2") | crontab

#--------------- Start Cron ------------------
# Grant execution rights
RUN chmod 755 startup.sh
CMD ["./startup.sh"]
