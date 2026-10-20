@echo off
rem Refreshes the .ts files of every screen group: lupdate reads the source files listed in i18n\<page>.lst (paths from the smart210
rem folder). A new .cpp or .ui file with tr() strings must be added to the list of its page. Needs lupdate from any Qt 5 on the PATH.
rem Translate the new entries, then run build_qm.bat.
cd /d "%~dp0.."
lupdate @i18n/shell.lst -ts i18n/smart210_shell_en.ts i18n/smart210_shell_zh_CN.ts i18n/smart210_shell_zh_TW.ts
lupdate @i18n/login.lst -ts i18n/smart210_login_en.ts i18n/smart210_login_zh_CN.ts i18n/smart210_login_zh_TW.ts
lupdate @i18n/sales.lst -ts i18n/smart210_sales_en.ts i18n/smart210_sales_zh_CN.ts i18n/smart210_sales_zh_TW.ts
lupdate @i18n/supplies.lst -ts i18n/smart210_supplies_en.ts i18n/smart210_supplies_zh_CN.ts i18n/smart210_supplies_zh_TW.ts
lupdate @i18n/remainders.lst -ts i18n/smart210_remainders_en.ts i18n/smart210_remainders_zh_CN.ts i18n/smart210_remainders_zh_TW.ts
lupdate @i18n/cards.lst -ts i18n/smart210_cards_en.ts i18n/smart210_cards_zh_CN.ts i18n/smart210_cards_zh_TW.ts
lupdate @i18n/dispensers.lst -ts i18n/smart210_dispensers_en.ts i18n/smart210_dispensers_zh_CN.ts i18n/smart210_dispensers_zh_TW.ts
lupdate @i18n/ticket_debt.lst -ts i18n/smart210_ticket_debt_en.ts i18n/smart210_ticket_debt_zh_CN.ts i18n/smart210_ticket_debt_zh_TW.ts
lupdate @i18n/review.lst -ts i18n/smart210_review_en.ts i18n/smart210_review_zh_CN.ts i18n/smart210_review_zh_TW.ts
lupdate @i18n/settings.lst -ts i18n/smart210_settings_en.ts i18n/smart210_settings_zh_CN.ts i18n/smart210_settings_zh_TW.ts
lupdate @i18n/sync.lst -ts i18n/smart210_sync_en.ts i18n/smart210_sync_zh_CN.ts i18n/smart210_sync_zh_TW.ts
