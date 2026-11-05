@echo off
REM Lance le site en local sur http://localhost:8000 (PHP 8.1+ avec l'extension pdo_mysql requis).
REM Les identifiants de la base se renseignent dans app\Config\config.local.php (voir README.md).

REM Se place à la racine du site (le dossier parent de scripts\)
cd /d "%~dp0.."

where php >nul 2>nul
if errorlevel 1 (
    echo PHP n'est pas installe. Voir le fichier README.md.
    pause
    exit /b 1
)

php -m | findstr /i "pdo_mysql" >nul
if errorlevel 1 (
    echo ATTENTION : ce PHP n'a pas l'extension pdo_mysql, le site ne pourra pas joindre la base MySQL.
    echo Utilisez le PHP de WAMP ou activez extension=pdo_mysql dans php.ini.
    pause
)

echo Site disponible sur http://localhost:8000  (Ctrl+C pour arreter)
start "" http://localhost:8000
php -d upload_max_filesize=2048M -d post_max_size=2100M -d max_execution_time=3600 -d max_input_time=3600 -S localhost:8000 -t . index.php
