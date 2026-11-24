@echo off
chcp 1252 >nul
echo ============================================================
echo   CTA Lambarene - installation de la base de donnees (XAMPP)
echo ============================================================
echo.
echo Pre-requis : XAMPP lance, MySQL demarre, dossier "cta" copie dans c:\xampp\htdocs\
echo.
set "user=cta" & set /p "user=Nom de l'utilisateur de la base, sans accent ni espace. Laisser vide pour 'cta' : "
set "pwd=" & set /p "pwd=Mot de passe de cet utilisateur de la base (sans accent, espace ni guillemet, 12 caracteres minimum) : "
if "%pwd%"=="" (echo Un mot de passe est obligatoire. & pause & exit /b 1)
set "dbms_path=c:\xampp\mysql" & set /p "dbms_path=Emplacement de MySQL. Laisser vide pour 'c:\xampp\mysql' : "
set "app_path=c:\xampp\htdocs\cta" & set /p "app_path=Emplacement de l'application. Laisser vide pour 'c:\xampp\htdocs\cta' : "
if not exist "%dbms_path%\bin\mysql.exe" (
 echo Erreur : mysql.exe introuvable dans %dbms_path%\bin
 pause
 exit /b 1
)
if not exist "%app_path%\base_cta.sql" (
 echo Erreur : base_cta.sql introuvable dans %app_path%
 pause
 exit /b 1
)
echo.
echo Recapitulatif :
echo - Base de donnees : cta
echo - Utilisateur     : %user%
echo - Mot de passe    : %pwd%
echo - MySQL           : %dbms_path%
echo - Application     : %app_path%
echo.
set /p "conf=Taper 'oui' pour installer, ou laisser vide pour quitter : "
if not "%conf%"=="oui" exit /b
echo.
echo Creation de la base et des tables...
"%dbms_path%\bin\mysql.exe" -u root < "%app_path%\base_cta.sql"
if errorlevel 1 (
 echo Erreur lors de l'import. Si root a un mot de passe, utilisez plutot phpMyAdmin (onglet Importer).
 pause
 exit /b 1
)
echo Creation de l'utilisateur...
"%dbms_path%\bin\mysql.exe" -u root -e "CREATE USER IF NOT EXISTS '%user%'@'localhost' IDENTIFIED BY '%pwd%'; GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX ON cta.* TO '%user%'@'localhost'; FLUSH PRIVILEGES;"
echo Mise a jour de config.php...
powershell -NoProfile -Command "$f='%app_path%\config.php'; $t=Get-Content -Raw -Encoding UTF8 $f; $t=$t -replace \"'utilisateur'\s*=>\s*'[^']*'\", \"'utilisateur'     => '%user%'\"; $t=$t -replace \"'mot_de_passe'\s*=>\s*'[^']*'\", \"'mot_de_passe'    => '%pwd%'\"; [IO.File]::WriteAllText($f,$t,(New-Object Text.UTF8Encoding($false)))"
echo.
echo ------------------------------------------------------------
echo Installation terminee.
echo Suite : lancez installer_sauvegarde.bat (sauvegarde chiffree quotidienne) et lisez DURCISSEMENT.txt
echo Ouvrez : http://localhost/cta/etat.php  (controle)
echo puis   : http://localhost/cta/           (application)
echo ------------------------------------------------------------
pause
