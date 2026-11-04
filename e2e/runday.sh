#!/bin/sh
# One business day of the test week (TEST_PLAN.md 3.1): the terminal's part by smart210/tests/guiday.exe (the real screens), the web staff's part by
# e2e/humanday.php (the real pages), then the reconciliation (e2e/reconcile.php).
#
#   sh e2e/runday.sh <day 1..7> [morning <YYYY-MM-DD>]
#
#   sh e2e/runday.sh 1                      the working day 1
#   sh e2e/runday.sh 1 morning 2026-11-04   the next morning: the accountant confirms the records of that business day
#
# Environment: GUIDAY = path of guiday.exe (default /tmp/gd/debug/guiday.exe, built from smart210/tests/guiday.pro), DATA = the terminal's data folder
# (default .../debug/data-day1), PHP = php.exe (default /d/xampp/php/php.exe), QTPATH = the Qt 5.15 and MinGW bin folders.
# The counts are the week table of the plan: spot sales / card sales / supplies per day; the ticket sales (20 + day) and 8 supplies at the manual nozzle are added.

day=${1:?day number 1..7}
here=$(cd "$(dirname "$0")" && pwd)
root=$(dirname "$here")
PHP=${PHP:-/d/xampp/php/php.exe}
GUIDAY=${GUIDAY:-/tmp/gd/debug/guiday.exe}
DATA=${DATA:-$root/smart210/build/Desktop_Qt_5_15_2_MinGW_64_bit_Debug/debug/data-day1}
export PATH=${QTPATH:-/d/Qt/5.15.2/mingw81_64/bin:/d/Qt/Tools/mingw810_64/bin}:$PATH

if [ "$2" = "morning" ]; then
    date=${3:?business date YYYY-MM-DD}
    "$PHP" "$here/humanday.php" --part=morning --date="$date"
    exit $?
fi

case $day in
    1) spot=104; cards=2; supplies=196 ;;
    2) spot=118; cards=3; supplies=237 ;;
    3) spot=107; cards=1; supplies=188 ;;
    4) spot=112; cards=2; supplies=214 ;;
    5) spot=120; cards=3; supplies=250 ;;
    6) spot=101; cards=1; supplies=181 ;;
    7) spot=100; cards=1; supplies=180 ;;
    *) echo "day 1..7"; exit 2 ;;
esac
tickets=$((20 + day))

echo "=== day $day: terminal part (spot $spot, ticket sales $tickets, supplies $supplies + 8 manual, cards $cards)"
"$GUIDAY" --data="$DATA" --spot=$spot --ticket-sales=$tickets --supplies=$supplies --manual-supplies=8 --cards=$cards --upload --seed=$((100 + day)) || exit 1

echo "=== day $day: web part (assistant, accountant)"
"$PHP" "$here/humanday.php" --part=day --day=$day || exit 1

echo "=== day $day: reconciliation"
"$PHP" "$here/reconcile.php" --terminal="$DATA/smart210.db"
