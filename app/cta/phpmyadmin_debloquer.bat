@echo off
chcp 1252 >nul
REM Rouvre phpMyAdmin (a refermer apres usage avec phpmyadmin_bloquer.bat).
set "P=c:\xampp\phpMyAdmin"
set "B=c:\xampp\phpMyAdmin_BLOQUE"
if exist "%P%" (echo phpMyAdmin est deja accessible. & goto fin)
if not exist "%B%" (echo Dossier introuvable : %B% & goto fin)
ren "%B%" phpMyAdmin
if exist "%P%" (echo phpMyAdmin est de nouveau accessible. Pensez a le rebloquer apres usage.) else (echo ECHEC : relancez ce fichier en administrateur.)
:fin
echo.
pause
