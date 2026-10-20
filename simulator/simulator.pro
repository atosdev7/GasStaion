# Nozzle board simulator: a PC program that plays the RS485-to-Ethernet converter and the six nozzle boards (protocol v0.2, see
# PROTOCOL.md), so the Smart210 side can be tried without an STM32 board. It connects to the port the Smart210 listens on.
# Build with the desktop Qt: qmake, make.
TEMPLATE = app
TARGET = nozzle-simulator

QT += core gui widgets network

INCLUDEPATH += src ../smart210/src
include(../smart210/src/folders.pri)

SOURCES += \
    src/main.cpp \
    src/BoardSimulator.cpp \
    src/ConverterClient.cpp \
    src/MainWindow.cpp \
    src/NozzlePanel.cpp \
    ../smart210/src/dispensers/BoardProtocol.cpp

HEADERS += \
    src/BoardSimulator.h \
    src/ConverterClient.h \
    src/MainWindow.h \
    src/NozzlePanel.h \
    ../smart210/src/dispensers/BoardProtocol.h
