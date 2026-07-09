#!/usr/bin/env bash
#
# Laudo Vet Generator - montador de pacote (Fedora/Linux)
#
# Gera, a partir do Fedora, o .zip pronto para entregar a medica (que usa
# Windows). Nao roda o .bat (aquilo e Windows); aqui apenas montamos os
# ingredientes que vao dentro do pacote:
#   1. Baixa o PHP 8.3 portatil do WINDOWS (php.exe) para .tooling\php8.3.
#      Sao so arquivos; nao executamos php.exe aqui - quem executa e ela.
#   2. Garante as dependencias PHP (setasign/fpdf) via podman/composer.
#      fpdf e PHP puro, funciona igual em Windows.
#   3. Valida a logica de geracao de PDF via podman (mesmo codigo, PHP Linux).
#   4. Zipa tudo em dist/laudo-vet-generator.zip.
#
# Ela recebe o zip, extrai e da duplo-clique em iniciar.bat. So isso.
#
# Requisitos (ja checados no Fedora): curl, unzip, zip, podman.
# Uso:  bash montar-pacote.sh
set -euo pipefail
cd "$(dirname "$0")"

# --- Configuravel: versao/URL do PHP portatil do Windows --------------------
# Se o download der 404, a versao saiu do diretorio principal e foi para
# /archives/. Pegue a 8.3 NTS x64 atual em https://windows.php.net/download/
# e ajuste a URL abaixo (ou exporte PHP_ZIP_URL antes de rodar).
PHP_ZIP_URL="${PHP_ZIP_URL:-https://windows.php.net/downloads/releases/archives/php-8.3.14-nts-Win32-vs16-x64.zip}"

TOOLING=".tooling/php8.3"
DIST="dist"
ZIPFILE="$DIST/laudo-vet-generator.zip"

echo
echo "=== Montando pacote Windows a partir do Fedora ==="
echo

# --- 1. PHP portatil do Windows --------------------------------------------
if [ -f "$TOOLING/php.exe" ]; then
    echo "[1/4] PHP do Windows ja presente em $TOOLING - ok."
else
    echo "[1/4] Baixando PHP do Windows..."
    mkdir -p "$TOOLING"
    tmpzip="$(mktemp --suffix=.zip)"
    if ! curl -fL "$PHP_ZIP_URL" -o "$tmpzip"; then
        echo "  [ERRO] Download falhou. A versao pode ter ido para /archives/."
        echo "         Ajuste PHP_ZIP_URL no topo do script (ou exporte a variavel)"
        echo "         com a 8.3 NTS x64 atual de https://windows.php.net/download/"
        rm -f "$tmpzip"
        exit 1
    fi
    unzip -oq "$tmpzip" -d "$TOOLING"
    rm -f "$tmpzip"
    [ -f "$TOOLING/php.exe" ] || { echo "  [ERRO] php.exe nao encontrado apos extrair."; exit 1; }
    echo "      PHP extraido em $TOOLING - ok."
fi

# --- 2. Dependencias PHP (fpdf) --------------------------------------------
if [ -f vendor/autoload.php ]; then
    echo "[2/4] vendor ja presente - ok."
else
    echo "[2/4] Instalando dependencias via podman (composer)..."
    podman run --rm -v "$PWD":/app:Z -w /app docker.io/library/composer:2 \
        install --no-dev --no-interaction --no-progress --optimize-autoloader
    [ -f vendor/autoload.php ] || { echo "  [ERRO] vendor nao foi criado."; exit 1; }
fi

# --- 3. Validacao (via podman, PHP Linux - mesmo codigo) --------------------
echo "[3/4] Validando geracao de PDF (via podman)..."
podman run --rm -v "$PWD":/app:Z -w /app docker.io/library/php:8.3-cli \
    php tests/pdf-gerar.php

# --- 4. Zip do pacote -------------------------------------------------------
echo "[4/4] Gerando o zip..."
mkdir -p "$DIST"
rm -f "$ZIPFILE"
# Inclui o essencial para ela rodar; exclui git, testes e docs pesados.
# data/ vai vazia (o app cria checklists.json quando ela salva algo); o zip
# guarda a pasta vazia para o app ter onde gravar.
mkdir -p data
rm -f data/*.log 2>/dev/null || true
zip -rq "$ZIPFILE" \
    public src vendor .tooling iniciar.bat composer.json data LEIA.md \
    -x "*.log"

echo
echo "=== Pronto: $ZIPFILE ($(du -h "$ZIPFILE" | cut -f1)) ==="
echo
echo "  Envie esse zip para a medica. Ela extrai e da duplo-clique em iniciar.bat."
echo
