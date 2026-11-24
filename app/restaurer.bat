@echo off
chcp 1252 >nul
REM Usage : restaurer.bat "c:\xampp\cta_sauvegarde\cta_2026-11-11_150000.enc"
if "%~1"=="" (
 echo Indiquez le fichier .enc a restaurer. Fichiers disponibles :
 dir /b "c:\xampp\cta_sauvegarde\*.enc"
 pause
 exit /b 1
)
c:\xampp\php\php.exe "%~dp0restaurer.php" "%~1"
pause
