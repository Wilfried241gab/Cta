@echo off
chcp 1252 >nul
REM ---- Sauvegarde quotidienne CHIFFREE de la base CTA (planifiee par installer_sauvegarde.bat) ----
set "PHP=c:\xampp\php\php.exe"
set "APP=%~dp0"
"%PHP%" "%APP%sauvegarde.php"
if errorlevel 1 (
 echo ECHEC de la sauvegarde : voir logs\cta_erreurs.log
 exit /b 1
)
echo Sauvegarde terminee.
