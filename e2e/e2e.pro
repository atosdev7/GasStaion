# The Smart210 side of the end-to-end tests: a console program that works like a terminal (sales, supplies on simulated boards, checkout, upload,
# download) against a running web app. Built like the unit tests (qmake, make); run.ps1 does everything.
TEMPLATE = app
TARGET = e2e
QT += core sql network
CONFIG += console c++11
DEFINES += APP_VERSION=\\\"e2e\\\"

SMART = ../smart210/src
SIM = ../simulator/src
INCLUDEPATH += $$SMART $$SIM ../smart210/tests
include($$SMART/folders.pri)

SOURCES += e2e.cpp \
    $$SIM/BoardSimulator.cpp \
    $$SIM/ConverterClient.cpp \
    $$SMART/sync/ApiClient.cpp \
    $$SMART/sync/ApiReply.cpp \
    $$SMART/core/Barcode.cpp \
    $$SMART/dispensers/BoardBus.cpp \
    $$SMART/dispensers/BoardProtocol.cpp \
    $$SMART/cards/CardLedger.cpp \
    $$SMART/cards/Cards.cpp \
    $$SMART/sales/Checkout.cpp \
    $$SMART/core/Clock.cpp \
    $$SMART/core/Database.cpp \
    $$SMART/core/Decimal.cpp \
    $$SMART/ticket_debt/LoanSettlement.cpp \
    $$SMART/settings/MachineConfig.cpp \
    $$SMART/core/Migrator.cpp \
    $$SMART/core/PosHash.cpp \
    $$SMART/sync/Purger.cpp \
    $$SMART/review/ReviewData.cpp \
    $$SMART/core/Session.cpp \
    $$SMART/core/Settings.cpp \
    $$SMART/sales/SpotSale.cpp \
    $$SMART/supplies/SupplyQueue.cpp \
    $$SMART/supplies/SupplyRecord.cpp \
    $$SMART/supplies/SupplySelection.cpp \
    $$SMART/supplies/SupplySession.cpp \
    $$SMART/sync/SyncClient.cpp \
    $$SMART/sync/SyncStore.cpp \
    $$SMART/sales/TicketNumbers.cpp \
    $$SMART/sales/TicketSale.cpp \
    $$SMART/sync/Uploader.cpp

HEADERS += \
    Scenarios.inc \
    Offline.inc \
    Cards.inc \
    Proxy.inc \
    Day.inc \
    Dev.inc \
    $$SIM/BoardSimulator.h \
    $$SIM/ConverterClient.h \
    ../smart210/tests/FakeBoards.h \
    $$SMART/sync/ApiClient.h \
    $$SMART/sync/ApiReply.h \
    $$SMART/settings/MachineConfig.h \
    $$SMART/dispensers/BoardBus.h \
    $$SMART/supplies/SupplySelection.h \
    $$SMART/supplies/SupplySession.h \
    $$SMART/sync/SyncClient.h \
    $$SMART/sync/Uploader.h

RESOURCES += ../smart210/resources/resources.qrc
