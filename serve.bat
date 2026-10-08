@echo off
rem Menjalankan Aureus ERP (PHP 8.3) di http://127.0.0.1:8080/admin/login
set PATH=C:\laragon\bin\php\php-8.3.35-nts-Win32-vs16-x64;%PATH%
set PHP_CLI_SERVER_WORKERS=4
cd /d "%~dp0public"
php -S 127.0.0.1:8080 ..\vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php
