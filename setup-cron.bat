@echo off
echo Setting up Laravel Scheduler for BambooHR Time-off Requests
echo ============================================================
echo.
echo This script will help you set up the Laravel scheduler to run every hour.
echo.
echo IMPORTANT: You need to set up a Windows Task Scheduler task to run this command:
echo.
echo Command: php artisan schedule:run
echo Working Directory: %CD%
echo.
echo Steps to set up Windows Task Scheduler:
echo 1. Open Task Scheduler (taskschd.msc)
echo 2. Create Basic Task
echo 3. Name: "Laravel Scheduler - BambooHR Time-off"
echo 4. Trigger: Daily, repeat every 1 hour
echo 5. Action: Start a program
echo    - Program: php
echo    - Arguments: artisan schedule:run
echo    - Start in: %CD%
echo.
echo Alternative: You can also run this command manually to test:
echo php artisan schedule:run
echo.
echo The scheduler will automatically run the bamboohr:fetch-timeoff command every hour.
echo.
pause

