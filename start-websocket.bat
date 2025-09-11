@echo off
echo Starting Laravel WebSocket Server...
echo.
echo Make sure you have installed the required packages:
echo composer install
echo php artisan websockets:serve
echo.
echo If packages are not installed, run:
echo composer require beyondcode/laravel-websockets pusher/pusher-php-server
echo.
pause
php artisan websockets:serve --host=127.0.0.1 --port=6001
