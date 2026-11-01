# Cashier terminal app for the Smart210 board (Qt 5.3.2, GCC 4.5.1: write C++03 style, no nullptr, auto, range-for,
# override or final). Builds with a newer desktop Qt too, for trying screens on a PC.
TEMPLATE = app
TARGET = smart210

QT += core gui widgets sql network

VERSION = 0.1.0
DEFINES += APP_VERSION=\\\"$$VERSION\\\"

INCLUDEPATH += src
include(src/folders.pri)

SOURCES += \
    src/shell/main.cpp \
    src/sync/ApiClient.cpp \
    src/sync/ApiReply.cpp \
    src/core/AppConfig.cpp \
    src/core/Barcode.cpp \
    src/dispensers/BoardBus.cpp \
    src/dispensers/BoardClock.cpp \
    src/dispensers/BoardProtocol.cpp \
    src/cards/Cards.cpp \
    src/cards/CardReader.cpp \
    src/cards/CardPage.cpp \
    src/sales/Checkout.cpp \
    src/core/Clock.cpp \
    src/core/Database.cpp \
    src/core/Decimal.cpp \
    src/printing/EscPos.cpp \
    src/login/FirstSyncPage.cpp \
    src/core/Fonts.cpp \
    src/ticket_debt/LoanSettlePage.cpp \
    src/ticket_debt/LoanSettlement.cpp \
    src/login/LoginPage.cpp \
    src/settings/MachineConfig.cpp \
    src/settings/AppReset.cpp \
    src/settings/MachineConfigPage.cpp \
    src/shell/MainWindow.cpp \
    src/core/Migrator.cpp \
    src/dispensers/NozzleConfigSync.cpp \
    src/cards/CardLedger.cpp \
    src/cards/CardSupplyDialog.cpp \
    src/cards/CardHistoryDialog.cpp \
    src/ticket_debt/LoanSettleDialog.cpp \
    src/dispensers/NozzleDialog.cpp \
    src/review/ReviewData.cpp \
    src/review/ReviewPage.cpp \
    src/sales/SaleDeletion.cpp \
    src/core/Session.cpp \
    src/core/Settings.cpp \
    src/settings/SettingsPage.cpp \
    src/settings/ServerForm.cpp \
    src/login/SetupFlow.cpp \
    src/login/SetupPage.cpp \
    src/sales/SpotSale.cpp \
    src/sales/SpotSalePage.cpp \
    src/sync/SyncClient.cpp \
    src/sync/SyncStore.cpp \
    src/settings/PinPage.cpp \
    src/core/PosHash.cpp \
    src/printing/PrintService.cpp \
    src/printing/PrinterConnection.cpp \
    src/sync/Purger.cpp \
    src/input/ScanDecoder.cpp \
    src/input/ScannerReader.cpp \
    src/input/InputDevices.cpp \
    src/supplies/SupplierScanner.cpp \
    src/supplies/SupplyPage.cpp \
    src/shell/AppShell.cpp \
    src/shell/ConfirmDialog.cpp \
    src/dispensers/DispensersPage.cpp \
    src/shell/TabbedPage.cpp \
    src/core/Theme.cpp \
    src/supplies/SupplySelection.cpp \
    src/supplies/SupplyQueue.cpp \
    src/supplies/SupplyRecord.cpp \
    src/supplies/DirectSet.cpp \
    src/supplies/SupplySession.cpp \
    src/supplies/ManualSupplyPage.cpp \
    src/remainders/RemainderTicketsPage.cpp \
    src/sales/TicketNumbers.cpp \
    src/sales/TicketLegacy.cpp \
    src/printing/TicketRenderer.cpp \
    src/sales/TicketSale.cpp \
    src/sales/TicketSalePage.cpp \
    src/core/Translations.cpp \
    src/sync/AutoUpload.cpp \
    src/input/HangulInput.cpp \
    src/input/EnterChain.cpp \
    src/review/UploadSummary.cpp \
    src/sync/Uploader.cpp

HEADERS += \
    src/sync/ApiClient.h \
    src/sync/ApiReply.h \
    src/core/AppConfig.h \
    src/core/Barcode.h \
    src/dispensers/BoardBus.h \
    src/dispensers/BoardClock.h \
    src/dispensers/BoardProtocol.h \
    src/cards/Cards.h \
    src/cards/CardReader.h \
    src/cards/CardPage.h \
    src/sales/Checkout.h \
    src/core/Clock.h \
    src/core/Database.h \
    src/core/Decimal.h \
    src/printing/EscPos.h \
    src/login/FirstSyncPage.h \
    src/core/Fonts.h \
    src/ticket_debt/LoanSettlePage.h \
    src/ticket_debt/LoanSettlement.h \
    src/login/LoginPage.h \
    src/settings/MachineConfig.h \
    src/settings/AppReset.h \
    src/settings/MachineConfigPage.h \
    src/shell/MainWindow.h \
    src/core/Migrator.h \
    src/dispensers/NozzleConfigSync.h \
    src/cards/CardLedger.h \
    src/cards/CardSupplyDialog.h \
    src/cards/CardHistoryDialog.h \
    src/ticket_debt/LoanSettleDialog.h \
    src/dispensers/NozzleDialog.h \
    src/review/ReviewData.h \
    src/review/ReviewPage.h \
    src/sales/SaleDeletion.h \
    src/core/Session.h \
    src/core/Settings.h \
    src/settings/SettingsPage.h \
    src/settings/ServerForm.h \
    src/login/SetupFlow.h \
    src/login/SetupPage.h \
    src/sales/SpotSale.h \
    src/sales/SpotSalePage.h \
    src/sync/SyncClient.h \
    src/sync/SyncStore.h \
    src/core/TicketStatus.h \
    src/settings/PinPage.h \
    src/core/PosHash.h \
    src/settings/PrintOptions.h \
    src/printing/PrintService.h \
    src/printing/PrinterConnection.h \
    src/sync/Purger.h \
    src/input/ScanDecoder.h \
    src/input/ScannerReader.h \
    src/input/InputDevices.h \
    src/supplies/SupplierScanner.h \
    src/supplies/SupplyPage.h \
    src/shell/AppShell.h \
    src/shell/ConfirmDialog.h \
    src/dispensers/DispensersPage.h \
    src/shell/TabbedPage.h \
    src/core/Theme.h \
    src/supplies/SupplySelection.h \
    src/supplies/SupplyQueue.h \
    src/supplies/SupplyRecord.h \
    src/supplies/DirectSet.h \
    src/supplies/SupplySession.h \
    src/supplies/ManualSupplyPage.h \
    src/remainders/RemainderTicketsPage.h \
    src/sales/TicketNumbers.h \
    src/sales/TicketLegacy.h \
    src/printing/TicketRenderer.h \
    src/sales/TicketSale.h \
    src/sales/TicketSalePage.h \
    src/core/Translations.h \
    src/review/UploadSummary.h \
    src/input/HangulComposer.h \
    src/input/HangulInput.h \
    src/input/EnterChain.h \
    src/input/HangulKeyMap.h \
    src/sync/Uploader.h \
    src/sync/AutoUpload.h

# the screens' layouts, made in Qt Designer: src/<folder>/<Class>.ui is the form of the screen <Class>, next to its code
for(d, SRC_DIRS): FORMS += $$files(src/$$d/*.ui)

RESOURCES += resources/resources.qrc

# The translations are split by screen group: i18n/smart210_<page>_<language>.ts. Run i18n/update_ts.bat (lupdate per page,
# from the file lists i18n/<page>.lst), then i18n/build_qm.bat to make the .qm files.

