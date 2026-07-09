@echo off
chcp 65001 >nul
setlocal enabledelayedexpansion
cd /d "%~dp0"

rem ==========================================================================
rem  Laudo Vet Generator - montador de pacote (Windows)
rem
rem  Deixa a pasta pronta para entregar a medica (uso local, sem instalar nada).
rem  As extensoes sao passadas pelo iniciar.bat na linha de comando (-d), entao
rem  aqui NAO mexemos no php.ini. Passos:
rem    1. Baixa o PHP 8.3 portatil (NTS x64) para .tooling\php8.3, se faltar.
rem    2. Instala as dependencias PHP (setasign/fpdf) via composer, se faltar.
rem    3. Valida gerando um PDF de teste (tests\pdf-gerar.php).
rem
rem  Depois, zipe a pasta inteira e envie. A medica extrai e da duplo-clique
rem  em iniciar.bat.  (No Fedora, use montar-pacote.sh no lugar deste.)
rem
rem  Requisitos: Windows 10+ (PowerShell) e internet na primeira execucao.
rem ==========================================================================

rem --- Configuravel: versao/URL do PHP portatil ------------------------------
rem  Se o download falhar (404), a versao saiu do diretorio principal e foi
rem  para /archives/. Ajuste para a 8.3 NTS x64 atual em
rem  https://windows.php.net/download/
set "PHP_ZIP_URL=https://windows.php.net/downloads/releases/archives/php-8.3.14-nts-Win32-vs16-x64.zip"

set "TOOLING=%~dp0.tooling\php8.3"
set "PHP=%TOOLING%\php.exe"

echo.
echo   === Montando pacote do Laudo Vet Generator ===
echo.

rem --- 1. PHP portatil --------------------------------------------------------
if exist "%PHP%" (
    echo   [1/3] PHP portatil ja presente - ok.
) else (
    echo   [1/3] Baixando PHP portatil...
    if not exist "%~dp0.tooling" mkdir "%~dp0.tooling"
    set "ZIP=%TEMP%\php-portatil.zip"
    powershell -NoProfile -Command "try { [Net.ServicePointManager]::SecurityProtocol=[Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -Uri '%PHP_ZIP_URL%' -OutFile '!ZIP!' -UseBasicParsing } catch { Write-Host $_.Exception.Message; exit 1 }"
    if errorlevel 1 (
        echo   [ERRO] Falha ao baixar o PHP. Baixe o zip "PHP 8.3 NTS x64" de
        echo          https://windows.php.net/download/ e extraia para %TOOLING%
        pause
        exit /b 1
    )
    powershell -NoProfile -Command "Expand-Archive -Path '!ZIP!' -DestinationPath '%TOOLING%' -Force"
    del "!ZIP!" >nul 2>&1
    if not exist "%PHP%" (
        echo   [ERRO] php.exe nao encontrado apos extrair. Verifique %TOOLING%.
        pause
        exit /b 1
    )
    echo         PHP instalado - ok.
)

rem --- 2. Dependencias PHP (fpdf) via composer -------------------------------
if exist "%~dp0vendor\autoload.php" (
    echo   [2/3] Dependencias ja instaladas - ok.
) else (
    echo   [2/3] Instalando dependencias com composer...
    set "COMPOSER=%TEMP%\composer.phar"
    powershell -NoProfile -Command "try { [Net.ServicePointManager]::SecurityProtocol=[Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -Uri 'https://getcomposer.org/composer.phar' -OutFile '!COMPOSER!' -UseBasicParsing } catch { Write-Host $_.Exception.Message; exit 1 }"
    if errorlevel 1 (
        echo   [ERRO] Falha ao baixar o composer. Verifique a internet.
        pause
        exit /b 1
    )
    "%PHP%" -d extension_dir="%TOOLING%\ext" -d extension=mbstring -d extension=openssl "!COMPOSER!" install --no-interaction --no-progress --no-dev --optimize-autoloader
    del "!COMPOSER!" >nul 2>&1
    if not exist "%~dp0vendor\autoload.php" (
        echo   [ERRO] vendor nao foi criado. Veja as mensagens do composer acima.
        pause
        exit /b 1
    )
    echo         Dependencias instaladas - ok.
)

rem --- 3. Validacao: gera um PDF de teste ------------------------------------
echo   [3/3] Validando geracao de PDF...
"%PHP%" -d extension_dir="%TOOLING%\ext" -d extension=mbstring tests\pdf-gerar.php
if errorlevel 1 (
    echo   [ERRO] O teste de geracao de PDF falhou. Nao entregue ainda.
    pause
    exit /b 1
)

echo.
echo   === Pacote pronto! ===
echo   Zipe esta pasta inteira ^(inclui .tooling e vendor^) e envie a medica.
echo.
pause
endlocal
