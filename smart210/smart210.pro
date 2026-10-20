# Cashier terminal app for the Smart210 board (Qt 5.3.2, GCC 4.5.1: write C++03 style, no nullptr, auto, range-for,
# override or final). Builds with a newer desktop Qt too, for trying screens on a PC.
TEMPLATE = app
TARGET = smart210

QT += core gui widgets sql network

VERSION = 0.1.0
DEFINES += APP_VERSION=\\\"$$VERSION\\\"

INCLUDEPATH += src

SOURCES += \
    src/main.cpp \
    src/ApiClient.cpp \
    src/ApiReply.cpp \
    src/AppConfig.cpp \
    src/Barcode.cpp \
    src/BoardBus.cpp \
    src/BoardClock.cpp \
    src/BoardProtocol.cpp \
    src/Cards.cpp \
    src/CardReader.cpp \
    src/CardPage.cpp \
    src/Checkout.cpp \
    src/Clock.cpp \
    src/Database.cpp \
    src/Decimal.cpp \
    src/EscPos.cpp \
    src/FirstSyncPage.cpp \
    src/Fonts.cpp \
    src/LoanSettlePage.cpp \
    src/LoanSettlement.cpp \
    src/LoginPage.cpp \
    src/MachineConfig.cpp \
    src/MachineConfigPage.cpp \
    src/MainWindow.cpp \
    src/Migrator.cpp \
    src/NozzleConfigSync.cpp \
    src/CardLedger.cpp \
    src/CardHistoryDialog.cpp \
    src/LoanSettleDialog.cpp \
    src/NozzleDialog.cpp \
    src/ReviewData.cpp \
    src/ReviewPage.cpp \
    src/SaleDeletion.cpp \
    src/Session.cpp \
    src/Settings.cpp \
    src/SettingsPage.cpp \
    src/ServerForm.cpp \
    src/SetupFlow.cpp \
    src/SetupPage.cpp \
    src/SpotSale.cpp \
    src/SpotSalePage.cpp \
    src/SyncClient.cpp \
    src/SyncStore.cpp \
    src/PinPage.cpp \
    src/PosHash.cpp \
    src/PrintService.cpp \
    src/PrinterConnection.cpp \
    src/Purger.cpp \
    src/ScanDecoder.cpp \
    src/ScannerReader.cpp \
    src/InputDevices.cpp \
    src/SupplierScanner.cpp \
    src/SupplyPage.cpp \
    src/AppShell.cpp \
    src/DispensersPage.cpp \
    src/TabbedPage.cpp \
    src/Theme.cpp \
    src/SupplySelection.cpp \
    src/SupplyQueue.cpp \
    src/SupplyRecord.cpp \
    src/SupplySession.cpp \
    src/ManualSupplyPage.cpp \
    src/RemainderTicketsPage.cpp \
    src/TicketNumbers.cpp \
    src/TicketRenderer.cpp \
    src/TicketSale.cpp \
    src/TicketSalePage.cpp \
    src/Translations.cpp \
    src/AutoUpload.cpp \
    src/HangulInput.cpp \
    src/UploadSummary.cpp \
    src/Uploader.cpp

HEADERS += \
    src/ApiClient.h \
    src/ApiReply.h \
    src/AppConfig.h \
    src/Barcode.h \
    src/BoardBus.h \
    src/BoardClock.h \
    src/BoardProtocol.h \
    src/Cards.h \
    src/CardReader.h \
    src/CardPage.h \
    src/Checkout.h \
    src/Clock.h \
    src/Database.h \
    src/Decimal.h \
    src/EscPos.h \
    src/FirstSyncPage.h \
    src/Fonts.h \
    src/LoanSettlePage.h \
    src/LoanSettlement.h \
    src/LoginPage.h \
    src/MachineConfig.h \
    src/MachineConfigPage.h \
    src/MainWindow.h \
    src/Migrator.h \
    src/NozzleConfigSync.h \
    src/CardLedger.h \
    src/CardHistoryDialog.h \
    src/LoanSettleDialog.h \
    src/NozzleDialog.h \
    src/ReviewData.h \
    src/ReviewPage.h \
    src/SaleDeletion.h \
    src/Session.h \
    src/Settings.h \
    src/SettingsPage.h \
    src/ServerForm.h \
    src/SetupFlow.h \
    src/SetupPage.h \
    src/SpotSale.h \
    src/SpotSalePage.h \
    src/SyncClient.h \
    src/SyncStore.h \
    src/TicketStatus.h \
    src/PinPage.h \
    src/PosHash.h \
    src/PrintOptions.h \
    src/PrintService.h \
    src/PrinterConnection.h \
    src/Purger.h \
    src/ScanDecoder.h \
    src/ScannerReader.h \
    src/InputDevices.h \
    src/SupplierScanner.h \
    src/SupplyPage.h \
    src/AppShell.h \
    src/DispensersPage.h \
    src/TabbedPage.h \
    src/Theme.h \
    src/SupplySelection.h \
    src/SupplyQueue.h \
    src/SupplyRecord.h \
    src/SupplySession.h \
    src/ManualSupplyPage.h \
    src/RemainderTicketsPage.h \
    src/TicketNumbers.h \
    src/TicketRenderer.h \
    src/TicketSale.h \
    src/TicketSalePage.h \
    src/Translations.h \
    src/UploadSummary.h \
    src/HangulComposer.h \
    src/HangulInput.h \
    src/HangulKeyMap.h \
    src/Uploader.h \
    src/AutoUpload.h

# the screens' layouts, made in Qt Designer: ui/<Class>.ui is the form of the screen <Class>
FORMS += $$files(ui/*.ui)

RESOURCES += resources/resources.qrc

# The translations are split by screen group: i18n/smart210_<page>_<language>.ts. Run i18n/update_ts.bat (lupdate per page,
# from the file lists i18n/<page>.lst), then i18n/build_qm.bat to make the .qm files.

