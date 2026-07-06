@echo off
chcp 65001 >nul
cd /d "%~dp0"

rem Usa o PHP local configurado (.tooling); se nao existir, tenta o PHP do sistema.
set "PHP=%~dp0.tooling\php8.3\php.exe"
if not exist "%PHP%" set "PHP=php"

echo.
echo   Laudo Vet Generator
echo   Servidor em:  http://localhost:8080
echo   (feche esta janela ou tecle Ctrl+C para parar)
echo.

start "" http://localhost:8080
"%PHP%" -S localhost:8080 -t public
