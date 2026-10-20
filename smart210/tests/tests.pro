# Unit tests, run on a PC: qmake, make, then run tst_smart210 (use -platform offscreen when there is no display).
TEMPLATE = app
TARGET = tst_smart210

QT += core gui widgets sql network testlib

DEFINES += APP_VERSION=\\\"test\\\"

INCLUDEPATH += ../src ../../simulator/src

SOURCES += \
    tst_smart210.cpp \
    ../../simulator/src/BoardSimulator.cpp \
    ../../simulator/src/ConverterClient.cpp \
    ../src/ApiClient.cpp \
    ../src/ApiReply.cpp \
    ../src/AppConfig.cpp \
    ../src/Barcode.cpp \
    ../src/BoardBus.cpp \
    ../src/BoardClock.cpp \
    ../src/BoardProtocol.cpp \
    ../src/Cards.cpp \
    ../src/CardReader.cpp \
    ../src/CardPage.cpp \
    ../src/Checkout.cpp \
    ../src/Clock.cpp \
    ../src/Database.cpp \
    ../src/Decimal.cpp \
    ../src/EscPos.cpp \
    ../src/FirstSyncPage.cpp \
    ../src/Fonts.cpp \
    ../src/LoanSettlePage.cpp \
    ../src/LoanSettlement.cpp \
    ../src/LoginPage.cpp \
    ../src/MachineConfig.cpp \
    ../src/MachineConfigPage.cpp \
    ../src/MainWindow.cpp \
    ../src/Migrator.cpp \
    ../src/NozzleConfigSync.cpp \
    ../src/PinPage.cpp \
    ../src/PosHash.cpp \
    ../src/PrintService.cpp \
    ../src/PrinterConnection.cpp \
    ../src/Purger.cpp \
    ../src/ScanDecoder.cpp \
    ../src/ScannerReader.cpp \
    ../src/InputDevices.cpp \
    ../src/SupplierScanner.cpp \
    ../src/SupplyPage.cpp \
    ../src/AppShell.cpp \
    ../src/DispensersPage.cpp \
    ../src/TabbedPage.cpp \
    ../src/Theme.cpp \
    ../src/SupplySelection.cpp \
    ../src/SupplyQueue.cpp \
    ../src/SupplyRecord.cpp \
    ../src/SupplySession.cpp \
    ../src/ManualSupplyPage.cpp \
    ../src/RemainderTicketsPage.cpp \
    ../src/CardLedger.cpp \
    ../src/CardHistoryDialog.cpp \
    ../src/LoanSettleDialog.cpp \
    ../src/NozzleDialog.cpp \
    ../src/ReviewData.cpp \
    ../src/ReviewPage.cpp \
    ../src/SaleDeletion.cpp \
    ../src/Session.cpp \
    ../src/Settings.cpp \
    ../src/SettingsPage.cpp \
    ../src/ServerForm.cpp \
    ../src/SetupFlow.cpp \
    ../src/SetupPage.cpp \
    ../src/SpotSale.cpp \
    ../src/SpotSalePage.cpp \
    ../src/SyncClient.cpp \
    ../src/SyncStore.cpp \
    ../src/TicketNumbers.cpp \
    ../src/TicketRenderer.cpp \
    ../src/TicketSale.cpp \
    ../src/TicketSalePage.cpp \
    ../src/Translations.cpp \
    ../src/AutoUpload.cpp \
    ../src/HangulInput.cpp \
    ../src/UploadSummary.cpp \
    ../src/Uploader.cpp

HEADERS += \
    FakeBoards.h \
    ../../simulator/src/BoardSimulator.h \
    ../../simulator/src/ConverterClient.h \
    FakePrinter.h \
    FakeServer.h \
    ../src/ApiClient.h \
    ../src/ApiReply.h \
    ../src/AppConfig.h \
    ../src/Barcode.h \
    ../src/BoardBus.h \
    ../src/BoardClock.h \
    ../src/BoardProtocol.h \
    ../src/Cards.h \
    ../src/CardReader.h \
    ../src/CardPage.h \
    ../src/Checkout.h \
    ../src/Clock.h \
    ../src/Database.h \
    ../src/Decimal.h \
    ../src/EscPos.h \
    ../src/FirstSyncPage.h \
    ../src/Fonts.h \
    ../src/LoanSettlePage.h \
    ../src/LoanSettlement.h \
    ../src/LoginPage.h \
    ../src/MachineConfig.h \
    ../src/MachineConfigPage.h \
    ../src/MainWindow.h \
    ../src/Migrator.h \
    ../src/NozzleConfigSync.h \
    ../src/PinPage.h \
    ../src/PosHash.h \
    ../src/PrintOptions.h \
    ../src/PrintService.h \
    ../src/PrinterConnection.h \
    ../src/Purger.h \
    ../src/ScanDecoder.h \
    ../src/ScannerReader.h \
    ../src/InputDevices.h \
    ../src/SupplierScanner.h \
    ../src/SupplyPage.h \
    ../src/AppShell.h \
    ../src/DispensersPage.h \
    ../src/TabbedPage.h \
    ../src/Theme.h \
    ../src/SupplySelection.h \
    ../src/SupplyQueue.h \
    ../src/SupplyRecord.h \
    ../src/SupplySession.h \
    ../src/ManualSupplyPage.h \
    ../src/RemainderTicketsPage.h \
    ../src/CardLedger.h \
    ../src/CardHistoryDialog.h \
    ../src/LoanSettleDialog.h \
    ../src/NozzleDialog.h \
    ../src/ReviewData.h \
    ../src/ReviewPage.h \
    ../src/SaleDeletion.h \
    ../src/Session.h \
    ../src/Settings.h \
    ../src/SettingsPage.h \
    ../src/ServerForm.h \
    ../src/SetupFlow.h \
    ../src/SetupPage.h \
    ../src/SpotSale.h \
    ../src/SpotSalePage.h \
    ../src/SyncClient.h \
    ../src/SyncStore.h \
    ../src/TicketStatus.h \
    ../src/TicketNumbers.h \
    ../src/TicketRenderer.h \
    ../src/TicketSale.h \
    ../src/TicketSalePage.h \
    ../src/Translations.h \
    ../src/UploadSummary.h \
    ../src/HangulComposer.h \
    ../src/HangulInput.h \
    ../src/HangulKeyMap.h \
    ../src/Uploader.h \
    ../src/AutoUpload.h

FORMS += $$files(../ui/*.ui)

RESOURCES += ../resources/resources.qrc
