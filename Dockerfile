FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        libsqlite3-dev \
        libzip-dev \
        libpq-dev \
        unzip \
    && docker-php-ext-install pdo_sqlite pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY docker/entrypoint.sh /usr/local/bin/bpmn-entrypoint
RUN chmod +x /usr/local/bin/bpmn-entrypoint

EXPOSE 8000

ENTRYPOINT ["bpmn-entrypoint"]
CMD ["php", "-S", "0.0.0.0:8000", "router.php"]
