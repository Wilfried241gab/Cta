@echo off
chcp 1252 >nul
REM Cree l'utilisateur MySQL dedie "cta_app" (mot de passe aleatoire ecrit dans config.php) et fixe un mot de passe a root.
REM MySQL doit etre demarre. Lancez : durcir_mysql.bat  puis saisissez le NOUVEAU mot de passe root (notez-le !).
set /p NEWROOT=Nouveau mot de passe pour root MySQL (a noter, jamais vide) : 
if "%NEWROOT%"=="" (echo Mot de passe vide : abandon. & pause & exit /b 1)
c:\xampp\php\php.exe "%~dp0outil_securite.php" "" "%NEWROOT%"
if errorlevel 1 (echo Echec : root a peut-etre deja un mot de passe. Voir DURCISSEMENT.txt & pause & exit /b 1)
echo.
echo Fait. Ouvrez http://localhost/cta/etat.php pour verifier.
pause
