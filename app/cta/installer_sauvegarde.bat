@echo off
chcp 1252 >nul
REM Planifie la sauvegarde chiffree tous les jours a 13h30 (a lancer en tant qu'administrateur : clic droit > Executer en tant qu'administrateur)
schtasks /create /tn "Sauvegarde CTA" /tr "\"%~dp0sauvegarde.bat\"" /sc daily /st 13:30 /ru SYSTEM /rl HIGHEST /f
echo.
echo Premiere sauvegarde maintenant (cree aussi la cle de chiffrement) :
call "%~dp0sauvegarde.bat"
echo.
echo IMPORTANT : copiez le fichier c:\xampp\cta_secret\sauvegarde.key sur une cle USB rangee a part.
echo Sans cette cle, les sauvegardes ne peuvent pas etre restaurees.
pause
