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

SOURCES += e2e.cpp \
    $$SIM/BoardSimulator.cpp \
    $$SIM/ConverterClient.cpp \
    $$SMART/ApiClient.cpp \
    $$SMART/ApiReply.cpp \
    $$SMART/Barcode.cpp \
    $$SMART/BoardBus.cpp \
    $$SMART/BoardProtocol.cpp \
    $$SMART/CardLedger.cpp \
    $$SMART/Cards.cpp \
    $$SMART/Checkout.cpp \
    $$SMART/Clock.cpp \
    $$SMART/Database.cpp \
    $$SMART/Decimal.cpp \
    $$SMART/LoanSettlement.cpp \
    $$SMART/MachineConfig.cpp \
    $$SMART/Migrator.cpp \
    $$SMART/PosHash.cpp \
    $$SMART/Purger.cpp \
    $$SMART/ReviewData.cpp \
    $$SMART/Session.cpp \
    $$SMART/Settings.cpp \
    $$SMART/SpotSale.cpp \
    $$SMART/SupplyQueue.cpp \
    $$SMART/SupplyRecord.cpp \
    $$SMART/SupplySelection.cpp \
    $$SMART/SupplySession.cpp \
    $$SMART/SyncClient.cpp \
    $$SMART/SyncStore.cpp \
    $$SMART/TicketNumbers.cpp \
    $$SMART/TicketSale.cpp \
    $$SMART/Uploader.cpp

HEADERS += \
    Scenarios.inc \
    Offline.inc \
    Cards.inc \
    Proxy.inc \
    $$SIM/BoardSimulator.h \
    $$SIM/ConverterClient.h \
    ../smart210/tests/FakeBoards.h \
    $$SMART/ApiClient.h \
    $$SMART/ApiReply.h \
    $$SMART/MachineConfig.h \
    $$SMART/BoardBus.h \
    $$SMART/SupplySelection.h \
    $$SMART/SupplySession.h \
    $$SMART/SyncClient.h \
    $$SMART/Uploader.h

RESOURCES += ../smart210/resources/resources.qrc
