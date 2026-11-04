#!/bin/sh
# Builds and runs the firmware tests on the PC (the logic of the board without any STM32 code).
#   sh Tests/run_tests.sh        from the firmware folder; needs gcc (for example the MinGW of Qt: D:/Qt/Tools/mingw1310_64/bin)
set -e
cd "$(dirname "$0")"
OUT="${TMPDIR:-/tmp}/fw_tests"
mkdir -p "$OUT"
CFLAGS="-std=c99 -Wall -Wextra -pedantic -Werror -I../Core/Inc"
gcc $CFLAGS -o "$OUT/test_core" test_core.c ../Core/Src/board.c ../Core/Src/checksum.c
gcc $CFLAGS -o "$OUT/test_store" test_store.c ../Core/Src/store.c ../Core/Src/checksum.c
gcc $CFLAGS -o "$OUT/test_ui" test_ui.c ../Core/Src/ui.c ../Core/Src/board.c ../Core/Src/checksum.c
gcc $CFLAGS -o "$OUT/test_pinfilter" test_pinfilter.c ../Core/Src/pinfilter.c
gcc $CFLAGS -o "$OUT/test_display" test_display.c -lm
gcc $CFLAGS -o "$OUT/test_keyboard" test_keyboard.c
"$OUT/test_core"
"$OUT/test_store"
"$OUT/test_ui"
"$OUT/test_pinfilter"
"$OUT/test_display"
"$OUT/test_keyboard"
