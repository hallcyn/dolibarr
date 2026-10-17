FROM dolibarr/dolibarr:24.0.2 AS upstream

# Keep the official filesystem, but drop its two VOLUME declarations. Docker
# otherwise creates anonymous mounts that cannot be replaced by our symlinks.
FROM scratch
COPY --from=upstream / /

LABEL org.opencontainers.image.title="Dolibarr on Railway" \
      org.opencontainers.image.description="Dolibarr with persistent files, private MariaDB, health checks and optional cron" \
      org.opencontainers.image.source="https://github.com/hallcyn/dolibarr"

COPY theme/ /opt/indy-theme/
RUN bash /opt/indy-theme/install.sh /var/www/html

COPY docker/ /opt/railway/
COPY scripts/backup.sh /usr/local/bin/dolibarr-backup
RUN chmod +x /opt/railway/entrypoint.sh /usr/local/bin/dolibarr-backup \
    && cp /opt/railway/health.php /var/www/html/railway-health.php \
    && mkdir -p /var/www/scripts/before-starting.d \
    && cp /opt/railway/before-start.sh /var/www/scripts/before-starting.d/90-railway.sh \
    && a2enmod headers setenvif \
    && cp /opt/railway/apache.conf /etc/apache2/conf-enabled/railway.conf

ENV PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin \
    PHP_INI_DIR=/usr/local/etc/php \
    APACHE_CONFDIR=/etc/apache2 \
    APACHE_ENVVARS=/etc/apache2/envvars \
    DOLI_VERSION=24.0.2 \
    DOLI_VERSION_FOR_INIT_DEMO=24.0 \
    DOLI_DB_TYPE=mysqli \
    DOLI_DB_HOST_PORT=3306 \
    DOLI_DB_SSL=false \
    DOLI_AUTH=dolibarr \
    WWW_USER_ID=33 \
    WWW_GROUP_ID=33 \
    PORT=80 \
    DOLI_INSTALL_AUTO=1 \
    DOLI_INIT_DEMO=0 \
    DOLI_PROD=1 \
    DOLI_CRON=0 \
    DOLI_RAILWAY_CRON=0 \
    PHP_INI_DATE_TIMEZONE=Europe/Paris \
    PHP_INI_MEMORY_LIMIT=256M \
    PHP_INI_UPLOAD_MAX_FILESIZE=20M \
    PHP_INI_POST_MAX_SIZE=24M \
    PHP_INI_ALLOW_URL_FOPEN=0

WORKDIR /var/www/html
EXPOSE 80
ENTRYPOINT ["/opt/railway/entrypoint.sh"]
CMD ["apache2-foreground"]
