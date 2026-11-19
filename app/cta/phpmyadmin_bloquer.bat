@echo off
chcp 1252 >nul
REM Bloque phpMyAdmin en renommant son dossier : aucune configuration Apache, aucun redemarrage necessaire.
set "P=c:\xampp\phpMyAdmin"
set "B=c:\xampp\phpMyAdmin_BLOQUE"
if exist "%B%" (echo phpMyAdmin est deja bloque. & goto fin)
if not exist "%P%" (echo Dossier introuvable : %P% & echo Verifiez que XAMPP est installe dans c:\xampp & goto fin)
ren "%P%" phpMyAdmin_BLOQUE
if exist "%B%" (echo phpMyAdmin est BLOQUE.) else (echo ECHEC : fermez le panneau XAMPP, relancez ce fichier en administrateur.)
:fin
echo.
pause
