# The screen-driving generator of the test plan (TEST_PLAN.md, 0.4 and part 3): it opens the real Smart210 window and works at it like a cashier (types in the
# fields, picks from the boxes, presses the buttons), so what it makes is what the screens make. Built from the same sources as the unit tests:
#   qmake guiday.pro && make && debug\guiday.exe --data=<folder of the terminal> ...      (see guiday.cpp)
include(tests.pro)

TARGET = guiday
SOURCES -= tst_smart210.cpp
SOURCES += guiday.cpp
