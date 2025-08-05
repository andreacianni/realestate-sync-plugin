@echo off
echo ================================================
echo REALESTATE SYNC PLUGIN - GIT REPOSITORY SETUP
echo ================================================

cd /d "C:\Users\Andrea\OneDrive\Lavori\novacom\Trentino-immobiliare\realestate-sync-plugin"

echo.
echo [1/6] Initialize Git Repository...
git init

echo.
echo [2/6] Add all plugin files...
git add .

echo.
echo [3/6] Initial commit...
git commit -m "init: RealEstate Sync Plugin v1.0.0

- Complete WordPress plugin for automated XML property import
- Professional admin interface with dashboard and controls
- Chunked processing with performance optimization
- WordPress cron integration for automation
- Comprehensive logging and error handling
- Ready for production deployment"

echo.
echo [4/6] Set main branch as default...
git branch -M main

echo.
echo [5/6] Add GitHub origin remote...
git remote add origin https://github.com/andreacianni/realestate-sync-plugin.git

echo.
echo [6/6] Push to GitHub...
git push -u origin main

echo.
echo ================================================
echo GITHUB REPOSITORY SETUP COMPLETED!
echo ================================================
pause
