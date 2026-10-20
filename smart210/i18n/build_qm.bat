@echo off
rem Turns the .ts files into the .qm files the app embeds. Needs lrelease from any Qt 5 on the PATH (the .qm format is
rem the same for Qt 5.3 and later). Run update_ts.bat first when strings were added or changed.
cd /d "%~dp0"
for %%f in (smart210_*.ts) do lrelease %%f
