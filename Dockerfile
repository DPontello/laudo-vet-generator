# Imagem unica: PHP 8.3 CLI + servidor embutido servindo public/.
#
# Extensoes instaladas:
#   - gd        -> os testes fabricam JPEG de amostra (o app usa getimagesize, do core);
#   - mbstring  -> conversao UTF-8 -> Windows-1252 para as fontes core do FPDF;
#   - zip       -> usado pelo composer ao extrair pacotes.
# A dependencia PHP (setasign/fpdf) e instalada via composer durante o build.
FROM php:8.3-cli

# Libs de sistema + extensoes PHP.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg-dev libfreetype6-dev libzip-dev libonig-dev unzip \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" gd zip mbstring \
    && rm -rf /var/lib/apt/lists/*

# Composer (copiado da imagem oficial).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Instala dependencias primeiro para aproveitar o cache de camada.
COPY composer.json ./
RUN composer install --no-interaction --no-progress --no-dev --optimize-autoloader

# Codigo da aplicacao.
COPY . .

EXPOSE 8080

# Servidor embutido atendendo requisicoes em paralelo (fork; so em Linux).
ENV PHP_CLI_SERVER_WORKERS=4

# Verifica se o endpoint responde (rota ?health devolve JSON de servico).
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD php -r "exit(@file_get_contents('http://127.0.0.1:8080/?health') !== false ? 0 : 1);"

# Servidor embutido do PHP servindo o diretorio public/.
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
