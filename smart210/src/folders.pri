# The source folders of the Smart210 app: one per sidebar page, plus core, input, printing and sync. The code includes headers by
# plain name (#include "SaleDeletion.h"), so every folder is on the include path. Included by the app, the tests, the
# simulator and the end-to-end program.
SRC_DIRS = core shell login sales supplies remainders cards dispensers ticket_debt review settings sync printing input
for(d, SRC_DIRS): INCLUDEPATH += $$PWD/$$d
