@echo off
chcp 65001 >nul
setlocal
cd /d "%~dp0"

rem ==========================================================================
rem  Laudo Vet Generator - inicializador (Windows)
rem
rem  Usa o PHP portatil em .tooling\php8.3; se nao existir, tenta o PHP do
rem  sistema (PATH). As extensoes necessarias sao passadas direto na linha de
rem  comando (-d), entao NAO depende de editar o php.ini nem de onde a pasta
rem  foi extraida. Antes de subir, valida as dependencias e mostra mensagem
rem  clara se faltar algo (em vez de erro feio na hora de gerar o PDF).
rem ==========================================================================

set "TOOLING=%~dp0.tooling\php8.3"
set "PHP=%TOOLING%\php.exe"
set "PORTABLE=1"
if not exist "%PHP%" (
    set "PHP=php"
    set "PORTABLE=0"
)

rem --- PHP executavel? --------------------------------------------------------
"%PHP%" -v >nul 2>&1
if errorlevel 1 (
    echo.
    echo   [ERRO] PHP nao encontrado.
    echo   Falta a pasta .tooling\php8.3 ^(o pacote veio incompleto^) e nao ha
    echo   PHP instalado no sistema. Peca um novo arquivo a quem te enviou.
    echo.
    pause
    exit /b 1
)

rem --- Lib de PDF instalada? --------------------------------------------------
if not exist "%~dp0vendor\autoload.php" (
    echo.
    echo   [ERRO] A biblioteca de PDF ^(pasta vendor^) nao veio no pacote.
    echo   Peca um novo arquivo a quem te enviou.
    echo.
    pause
    exit /b 1
)

rem --- Extensao mbstring disponivel? -----------------------------------------
if "%PORTABLE%"=="1" (
    "%PHP%" -d "extension_dir=%TOOLING%\ext" -d extension=mbstring -m 2>nul | findstr /I /C:"mbstring" >nul
) else (
    "%PHP%" -m 2>nul | findstr /I /C:"mbstring" >nul
)
if errorlevel 1 (
    echo.
    echo   [ERRO] Extensao PHP "mbstring" indisponivel.
    echo   O texto do laudo precisa dela. Peca um novo arquivo a quem te enviou.
    echo.
    pause
    exit /b 1
)

echo.
echo   Laudo Vet Generator
echo   Servidor em:  http://localhost:8080
echo   ^(feche esta janela ou tecle Ctrl+C para parar^)
echo.

start "" http://localhost:8080
if "%PORTABLE%"=="1" (
    "%PHP%" -d "extension_dir=%TOOLING%\ext" -d extension=mbstring -S localhost:8080 -t public
) else (
    "%PHP%" -S localhost:8080 -t public
)

endlocal
