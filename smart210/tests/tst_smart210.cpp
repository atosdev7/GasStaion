#include <QtTest>
#include <QCursor>
#include <QGuiApplication>
#include <QScreen>
#include <QApplication>
#include <QDateEdit>
#include <QJsonArray>
#include <QJsonDocument>
#include <QSqlDatabase>
#include <QCheckBox>
#include <QComboBox>
#include <QFormLayout>
#include <QGroupBox>
#include <QLayout>
#include <QFontDatabase>
#include <QTcpServer>
#include <QTemporaryDir>
#include <QCryptographicHash>
#include <QLabel>
#include <QLineEdit>
#include <QListWidget>
#include <QMessageBox>
#include <QPushButton>
#include <QTabWidget>
#include <QSpinBox>
#include <QTableWidget>
#include <QSqlError>
#include <QSqlQuery>
#include <QStackedWidget>
#include <QTemporaryDir>
#include <QTranslator>
#include <QVBoxLayout>

#include "ApiClient.h"
#include "ApiReply.h"
#include "AppConfig.h"
#include "AppShell.h"
#include "AutoUpload.h"
#include "HangulComposer.h"
#include "HangulInput.h"
#include "Barcode.h"
#include "Checkout.h"
#include "Clock.h"
#include "Database.h"
#include "BoardBus.h"
#include "BoardClock.h"
#include "CardLedger.h"
#include "CardPage.h"
#include "ConfirmDialog.h"
#include "CardReader.h"
#include "Cards.h"
#include "TicketLegacy.h"
#include "TicketNumbers.h"
#include "BoardProtocol.h"
#include "Decimal.h"
#include "InputDevices.h"
#include "ScanDecoder.h"
#include "SupplyPage.h"
#include "SupplyQueue.h"
#include "SupplyRecord.h"
#include "SupplySession.h"
#include "TicketStatus.h"
#include "SupplySelection.h"
#include "SupplierScanner.h"
#include "EscPos.h"
#include "FakeBoards.h"
#include "FakePrinter.h"
#include "FakeServer.h"
#include "Fonts.h"
#include "MachineConfig.h"
#include "CardHistoryDialog.h"
#include "LoanSettleDialog.h"
#include "NozzleConfigSync.h"
#include "NozzleDialog.h"
#include "MainWindow.h"
#include "LoanSettlement.h"
#include "Migrator.h"
#include "PosHash.h"
#include "PrintOptions.h"
#include "PrintService.h"
#include "Purger.h"
#include "ReviewData.h"
#include "ReviewPage.h"
#include "SaleDeletion.h"
#include "Session.h"
#include "SpotSale.h"
#include "TicketRenderer.h"
#include "TicketSale.h"
#include "ManualSupplyPage.h"
#include "SpotSalePage.h"
#include "TicketSalePage.h"
#include "AppReset.h"
#include "Settings.h"
#include "SetupFlow.h"
#include "SyncClient.h"
#include "SyncStore.h"
#include "Theme.h"
#include "Translations.h"
#include "UploadSummary.h"
#include "Uploader.h"

class Smart210Test : public QObject
{
    Q_OBJECT

private slots:
    void init();
    void cleanup();

    void languageNamesAreNormalized();
    void onlyEnglishIsSupported();
    void everyLanguageHasAnEmbeddedTranslation_data();
    void everyLanguageHasAnEmbeddedTranslation();
    void unknownLanguageLeavesEnglish();
    void noFontFolderLeavesTheSystemFont();

    void databaseOpensInWalModeWithFullSync();
    void bundledMigrationsCreateTheSchema();
    void migratingAgainChangesNothing();
    void aMigrationAddsAColumnWhereAnOldDatabaseLacksIt();
    void aMigrationLeavesAColumnThatIsThereAndItsData();
    void aMigrationDirectiveForAMissingTableFailsTheScript();
    void aDatabaseNewerThanTheProgramIsRefused();
    void aFailingMigrationRollsBackAndKeepsTheVersion();
    void laterMigrationsRunOnTopOfEarlierOnes();
    void statementsAreSplitOutsideCommentsAndQuotes();
    void bundledMigrationsAvoidSyntaxNewerThanSqlite3_8_4();

    void settingsStoreReplaceAndRemoveValues();
    void settingsFallBackToTheDefault();

    void barcodesBecomeIntegersAndBack();
    void barcodesWithLeadingZerosSurviveTheDatabase();
    void invalidBarcodesAreRejected();

    void pingReturnsTheServerTime();
    void anErrorReplyShowsTheServersMessage();
    void aBusyServerIsRecognized();
    void aTooManyRequestsAnswerIsWaitedOutAndAskedAgain();
    void aServerThatKeepsRefusingWithTooManyRequestsEndsAsBusy();
    void callsLeaveOneIntervalApart();
    void anAnswerThatIsNotJsonIsAnError();
    void aClosedPortIsANetworkError();
    void aServerThatNeverAnswersTimesOut();
    void postsSendTheJsonBody();

    void aRegisteredTerminalIsConfiguredAndTheServerIsSaved();
    void anAddressTheServerDoesNotKnowIsNotConfigured();
    void aBusyServerFailsTheTest();
    void badAddressAndPortAreRefusedBeforeAnyRequest();
    void aServerThatCannotBeReachedFailsTheTest();
    void aSavedConfigurationSurvivesARestart();

    void decimalsAreParsedToIntegers();
    void extraDigitsAreRoundedAndBadTextIsRefused();
    void decimalsAreFormattedBack();
    void jsonNumbersAndStringsAreBothDecimals();

    void configIsReplacedWholeEveryTime();
    void usersAndCustomersAreStoredAndUpdated();
    void ticketsAreStoredWithIntegerKeysAndAmounts();
    void aPulledTicketKeepsWhatTheDeviceAddedAndNeverOverwritesUnsyncedWork();
    void loansKeepTheirLocalFieldsAndUnsyncedLoansAreNotOverwritten();
    void aBadRowRollsBackItsWholePage();

    void theFirstDownloadBeginsStoresEverythingAndOpensTheGate();
    void laterDownloadsSkipBeginAndInitialDoneAndEmptyAcks();
    void pagesAreFollowedUntilThereAreNoMore();
    void unuploadedRowsAreKeptNotAcknowledgedAndDoNotLoopForever();
    void anUnregisteredAddressStopsTheFirstDownloadAtOnce();
    void aBusyServerEndsTheDownloadAndItCanBeRepeated();
    void aFailureHalfWayKeepsWhatWasStoredAndLeavesTheGateClosed();
    void downloadNeedsAServerAndOnlyRunsOnce();

    void pendingCountsOnlyWhatMayBeSent();
    void uploadSendsEveryTableInOrderInTheApiFormat();
    void nothingToUploadSendsNothing();
    void recordsDeletedBeforeTheUploadAreNeverSent();
    void manyRowsGoInBatchesOf200InOrder();
    void aRefusedRowStaysWithItsReasonAndIsNotSentAgainInTheRun();
    void aBusyServerStopsTheUploadAndKeepsWhatWasSent();
    void rowsTheServerDoesNotAnswerAboutDoNotLoopForever();
    void uploadNeedsAServerAndOnlyRunsOnce();

    void purgeRemovesOnlyOldRecordsThatAreSafeToLose();
    void purgeNeverUsesLessThanOneDay();
    void purgeKeepsTheHistoryOfCards();
    void purgeKeepsTheRemainderTicketsThatAreNotUsedYet();
    void theRecordsTheAccountantConfirmedAreRemovedAtTheNextDownload();
    void aServerThatDoesNotKnowTheConfirmedQuestionDoesNotFailTheDownload();

    void theClockOffsetIsTheServersMinusOurs();
    void everyDownloadKeepsTheClockOffset();
    void theTicketSaleScreenOpensQuicklyOnABigStock();
    void theClockOffsetInUtcDoesNotDependOnTheTimeZone();
    void theTimeZoneIsAFixedPosixOffset();
    void aDownloadKeepsAndAppliesTheStationsTimeZoneAndMeasuresTheClockInUtc();
    void theStoredTimeZoneIsAppliedAtTheStartAndShownOnTheSettingsPage();

    void aTerminalWithoutAServerShowsTheSetupPage();
    void theGateHoldsTheAppUntilTheFirstDownloadFinishes();
    void aFailedFirstDownloadStaysAtTheGateUntilRetried();
    void aConfiguredTerminalOpensStraightAndDownloadsAndPurgesByItself();
    void downloadsRepeatOnATimerButNothingIsEverUploadedByItself();

    void aPasswordIsCheckedAgainstSaltedSha256();
    void aLegacySha1HashIsStillAccepted();
    void emptyOrOddHashesNeverMatch();

    void theLoginListHasOnlyActiveTerminalUsersByName();
    void theLoginOffersOnlyTheCashiers();
    void theLoginScreenHasAComboBoxAndASettingsButtonThatOpensMachineConfigWithoutALogin();
    void aCashierWhoHasLoggedInCannotOpenMachineConfig();
    void aRightPasswordStartsASessionThatSurvivesARestart();
    void aWrongPasswordLogsNobodyIn();
    void aUserWhoMayNotUseTheTerminalCannotLogIn();
    void aCashierWithALegacyHashCanLogIn();
    void logOutEndsTheSessionForGood();
    void aSessionEndsWhenTheWebAppDisablesOrDeletesTheCashier();

    void aTerminalWithoutASessionStartsAtTheLogin();
    void aLoggedInCashierOpensStraightIntoTheApp();
    void loggingInOnTheScreenOpensTheApp();
    void logOutIsOnTheSettingsPageAndBringsBackTheLogin();
    void aCashierDisabledOnTheWebIsAskedToLogInAgainAtTheNextStart();
    void afterTheFirstDownloadTheCashiersChooseTheirNames();
    void aCashierAddedOnTheWebAppearsOnTheLoginScreenAfterADownload();


    void machineConfigIsBehindThePinEveryTime();
    void fiveWrongPinsShutTheDoorForAWhile();
    void theMachineConfigurationLivesInTheDeviceFileNotInTheDatabase();
    void aNewDatabaseGetsTheMachineConfigurationBack();
    void withoutAPinMachineConfigStaysClosed();
    void nozzlesAreAlwaysTheBoardsOneToSix();
    void aNozzleIsAddedByHandCheckedAndKeptUpToDateFromItsBoard();
    void aRemovedNozzleGoesAwayAndTheWebAppIsToldInTheOrderTheyWereMade();
    void aBoardAtAnAddressThatWasNotAddedIsNotAskedAndMakesNoNozzle();
    void aNewNozzleHoldsItsFuelTypeTankAndDensityAndItsConfigIsSentLater();
    void nozzlesAreAddedAndRemovedOnTheMachineConfigPage();
    void aNozzleHasANameThatIsAddedSavedLoggedAndUploaded();
    void theMachineConfigPageShowsTypesAndSavesTheNameOfANozzle();
    void anOldDatabaseGetsTheNameOfANozzleAndKeepsItsRows();
    void aBoardsConfigBecomesTheNozzlesConfig();
    void theTankIsKeptOrPickedByFuelType();
    void theNozzleLineForTheLogIsInNoLanguage();
    void whatTheBoardsReportedSurvivesAConfigDownload();
    void nozzlesWithATankAreUploadedFirstAndMarkedUploaded();
    void aNozzleTheWebAppRefusedIsNotSentAgainByItselfAndDoesNotHoldBackTheRecords();
    void nothingIsReadFromABoardUntilItIsAskedFor();
    void aBlankBoardIsNotSavedWhenItIsRead();
    void aConfigChangeIsSentVerifiedAndKept();
    void aBoardThatDoesNotKeepTheConfigFailsTheVerification();
    void aNozzleInManualModeIsOutOfThePollingButItsConfigCanStillBeSentAndRead();
    void aRefusedConfigChangeKeepsTheOldConfig();
    void aReadThatWouldLeaveTheNozzleWithoutATankIsRefused();
    void aNozzleWithAFillOrQueuedTicketsIsNeitherUpdatedNorRemovedAndAnAddNeedsATank();
    void aNozzleWithoutATankIsReportedWhenMachineConfigOpens();
    void aConfigIsNotSentToABoardThatDoesNotAnswerOrWithBadValues();
    void whatABoardChangedByItselfIsPickedUpWhenItIsRead();
    void aBoardThatComesBackIsNotAskedForItsConfig();
    void theNozzleTableShowsTheBoardsAndTheirStatus();
    void aBadConfigIsCheckedBeforeItIsSent();
    void aChangeIsSentToTheBoardAndShownWhenItConfirms();
    void theConverterPortIsSavedAndListenedOn();
    void pulsesPerLitreAreReadAndWrittenAsAConfig();
    void theSimulatorRunsAnAutoFillOverTimeAndHoldsTheResult();
    void inManualModeTheAmountIsTypedAtTheDispenser();
    void aStoppedFillAFaultAndAPowerCutEndWithTheRightReason();
    void theSimulatorKeepsTheConfigOfItsBoardsBetweenRuns();
    void aManualFillIsSeenByTheBusAsItRuns();

    void aScannerTypesABarcodeAndEnterCompletesIt();
    void theDecoderKnowsShiftTheKeypadAndSymbols();
    void theDecoderIgnoresRepeatsStrayKeysAndLongPauses();
    void aScannerIsFoundByItsNameWhateverItsEventNumberIs();
    void theDeviceListIsReadFromAFile();
    void withoutEvdevTheSupplierScannerStaysIdle();

    void aSelectKeyAtTheDispenserChoosesTheNozzleAndPressingAgainLetsItGo();
    void tappingANozzleSelectsItOnItsBoardAndTappingAgainLetsItGo();
    void aNozzleThatCannotBeChosenSaysWhy();
    void theChosenNozzleIsLetGoWhenItsBoardIsFreeOrGone();
    void theSessionsTabListsTheNozzlesAndOnlyLooksAtThem();
    void theServerCanBeChangedOnThePageAndIsLogged();

    void theSummaryAddsUpExactlyWhatWouldBeSent();
    void anEmptyDayHasAnEmptySummary();
    void theFooterCountsWhatWaitsAndTheReviewScreenListsIt();
    void thereIsNoHomePageAndTheDownloadRunsEveryMinute();
    void theHangulComposerBuildsSyllables();
    void theHangulInputTypesHangulInTheFocusedFieldAndTheFooterSaysWhichMode();
    void aNewRecordHasItsDateAtOnce();
    void theUploadModesSayUpToWhenRecordsGoByThemselves();
    void anUploadBeforeAMomentSendsOnlyTheOlderRecordsAndSkipsRefusedOnes();
    void theUploadModeIsPickedAndSavedOnMachineConfig();
    void theTerminalUploadsByItselfInTheAutomaticModes();
    void aNozzleGoesToTheWebAppByItselfWhateverTheUploadMode();
    void theTerminalTellsTheWebAppWhenNothingOfAFinishedDayWaits();
    void unsentBeforeCountsTheRefusedRecordsToo();
    void pressingUploadSendsEverythingAndEmptiesTheScreen();
    void theReviewScreenOffersToPrintTheTicketOfASpotSaleAndTheRemainderTicketOfASupply();
    void aFailedUploadSaysSoAndCanBePressedAgain();
    void refusedRecordsStayListedWithTheirReason();
    void withNothingWaitingTheUploadButtonIsOff();
    void theReviewListsTheRecordsOfTheChosenDateWithTotals();
    void theSalesWithoutAReceiptCanBeLeftOutOfTheReviewScreen();
    void cleanDataErasesTheDatabaseAndIsRefusedWhileRecordsWait();
    void clearConfigErasesTheMachineSettingsAndKeepsTheData();
    void machineConfigIsOutsideTheShellWithRearrangedTabsAndTheTwoResets();
    void aRecordWithoutADateShowsUnderTheBusinessDayOfItsTime();
    void aRecordMadeNearNineIsFlaggedForTheDate();
    void aChangedCommentMakesTheRecordWaitAgainAndIsSentAgain();
    void aChangedDateMovesTheRecordAndItsRemainder();
    void nozzlesAreNotCountedAsWaitingButGoUpWithTheNextUpload();
    void theChangesAreWrittenToATextFile();
    void theReviewScreenChangesCommentsAndDatesOfTheSelectedRecord();
    void theUploadOfAChosenDateSendsOnlyWhatWaitsOnThatDate();
    void theUploaderCanBeLimitedToACheckoutDate();
    void aTicketJoinsANozzlesQueueOnlyWhenItPassesTheChecks();
    void anAutoFillTakesItsTicketsAndTheRestBecomesARemainder();
    void aFillThatReachedItsPresetLeavesNoRemainder();
    void aResultIsRecordedOnce();
    void aSupplyTypedInNeedsTicketsThatCoverItAndAStartedFillUsesItsOwn();
    void theManualInputPageKeepsTheKgAndTheRemainderTogether();
    void theLitresOfAManualSupplyAreWorkedOutAndItsTimeIsLimited();
    void theDaysBackOfAManualSupplyAreSetOnMachineConfig();
    void scannedTicketsFillTheChosenNozzleAndTheResultIsRecorded();
    void aTicketTypedInGoesToTheQueueLikeAScannedOne();
    void aTicketSoldOnPaperBeforeGoLiveIsMadeWhenItIsUsed();
    void theConfigDownloadBringsTheRangesOfTheOldPublishes();
    void theEnterTicketBoxOfTheSessionsTabTypesTicketsIn();
    void aStoppedFillMakesARemainderTicket();
    void aResultSeenAgainAfterALostAcknowledgementIsNotRecordedTwice();
    void aManualModeFillIsNotWorkedWithAtAll();
    void theSupplierWorksAtTheDispenserAndTheSessionsTabFollowsTheFill();
    void theSessionSetsThePresetOnEveryScanStartsWithTheGunAndClearsWithTheClearKey();
    void theManualInputScreenRecordsASupplyWithItsTickets();
    void theReviewScreenDeletesAMistakenSpotSaleAfterAConfirmation();
    void theSelectKeyOnlyTellsAndTheSelectCommandChoosesTheNozzleAndLetsTheOthersGo();
    void aNozzleThatLosesTheChoiceBeforeItsFillGivesItsPresetBack();
    void theGunUpIsReportedAndTheSmart210StartsOnlyWhenTicketsAreQueued();
    void theRemainderTicketsScreenPrintsATicketAgain();
    void aResultIsRecordedWhileNobodyIsLoggedIn();
    void anUploadNotesWhenARecordWasSent();
    void aRestoreOfTheServersDatabaseMakesTheTerminalSendAgainWhatItUploadedAfterTheDump();
    void aRestoreIsActedOnOnceAndTheDownloadSaysSo();
    void theTimeOfABoardIsSevenBytesOfTheStationsLocalTime();
    void theBoardsGetTheTimeOfTheTerminalAndKeepItRight();

    void theAmountAndTheWeightFollowEachOther();
    void aSpotSaleWritesTheSaleItsTicketAndNothingElse();
    void anAmountTypedByTheCashierIsKeptExactly();
    void ownUseAndOtherHaveNoAmountAndALoanMakesATicketLoan();
    void ticketNumbersNeverRepeatEvenAfterAPurgeOrOnANewBoard();
    void aSpotSaleThatIsNotAllowedWritesNothing();
    void withoutTheTicketNumbersFromTheServerNothingIsSold();
    void theCodeOfATicketIsTheFuelTypeAndTheKindAndTheSerialCountsPerCode();
    void theConfigDownloadBringsTheTicketNumbering();
    void aSpotSaleCanBeUploadedAsItIs();
    void theSpotSaleScreenCalculatesBothWaysAndSaves();
    void ownUseGoesToTheSelfCustomerAndHasNoAmount();
    void otherUseIsIndividualTooAndALoanNeedsACustomerWhoIsChosen();
    void theSpotSaleIsFilledWithTheKeyboardAlone();
    void theTicketSaleIsFilledWithTheKeyboardAlone();

    void aScannedTicketIsCheckedAtOnce();
    void aTicketSaleSellsTheTicketsAndWritesTheSale();
    void ticketSalesOfOwnUseAndLoanHaveNoAmountAndALoanMakesAnEntry();
    void aTicketSaleThatIsNotAllowedChangesNothing();
    void aTicketSaleCanBeUploadedAsItIs();
    void theItemsOfASaleTakeTheirSerialsLikeOnTheWeb();
    void aReceiptIsKeptWithTheTicketSale();
    void theTicketSaleScreenBuildsTheSaleFromItemsAndSaveSellsThem();
    void changingTheFuelTypeStartsTheItemsAgain();

    void aMistakenSpotSaleCanBeDeletedAndNeverGoesToTheServer();
    void deletedSalesDoNotReachTheServer();
    void onlyAnUnmovedSpotSaleOfTheLoggedInCashierMayBeDeleted();
    void theDeletedSaleIsPurgedWithItsTicketAndLoanAfterThePurgeDays();

    void onlyLoansWithABalanceAreOpenOldestFirst();
    void aSurrenderedTicketMustBeSoldAndOfTheLoansFuel();
    void surrenderingTicketsSuppliesThemAndRaisesTheSettledTotal();
    void aSettlementThatIsNotAllowedChangesNothing();
    void aSettlementCanBeUploadedAsItIs();
    void aDownloadDoesNotUndoASettlementThatIsNotUploadedYet();
    void theLoanScreenListsTheDebtsAndSettlesTheChosenOneInADialog();

    void theBusinessDayRunsFromNineToNine();
    void theCheckoutStampsOpenRecordsByTheirOwnDay();
    void theAutomaticCheckoutOnlyClosesBusinessDaysThatAreOver();
    void theCheckoutReportAddsUpTheDay();
    void aCheckedOutDayCanBeMovedByOneDayWithinADay();
    void aDayCannotBeMovedAfterADayOrOnceAnyRecordIsUploaded();
    void theUploadChecksOutWhatIsStillOpen();
    void theTerminalClosesOverdueDaysByItselfAtStart();

    void theEscPosCommandsAreTheRightBytes();
    void theBarcodeIsNativeCode128SubsetC();
    void theBarcodeOfATicketIsDrawnAsCode128CWithItsCheckSymbol();
    void aPictureBecomesBlackAndWhiteRasterRows();
    void tallPicturesAreSentInBandsOf128Rows();
    void theStatusAnswerSaysWhatIsWrongWithThePrinter();
    void theTicketHasTheTitleTheDetailsTheBarcodeAndTheLogoInThatOrder();
    void withoutALogoThereIsNothingAfterTheBarcode();
    void theTitleShrinksThenWrapsToFitThePaper();
    void theLogoIsScaledDownToFit();
    void aSpotSalesTicketIsReadFromTheSale();
    void aTicketReachesThePrinterAfterTheStatusCheck();
    void aPrinterThatReportsAProblemGetsNoJob();
    void aPrinterThatDoesNotAnswerTheStatusQuestionIsSentTheJobAnyway();
    void printingSaysWhyItCannotPrint();
    void everyPrintOfATicketIsCounted();
    void anOldDatabaseGetsThePrintCountOfATicket();
    void thePrinterCanBeCheckedWithoutPrinting();
    void theSpotSaleScreenPrintsAtOnceAndPrintsAgainOnRequest();
    void aSaleIsKeptWhenThePrinterFailsAndCanBePrintedLater();
    void thePrinterAddressIsSetAndCheckedOnMachineConfig();

    void theCrcAndTheFramesMatchTheSpecExamples();
    void theParserFindsFramesInPiecesAndGarbage();
    void theDataOfTheCommandsRoundTrips();
    void theBusPollsEveryBoardInTurn();
    void aCommandIsAnsweredAndTheNextPollShowsIt();
    void theSelectKeyIsSeenOnceInThePollFlags();
    void aLostAnswerCostsOneRoundAndTheCommandRunsOnce();
    void aSpoiledPollAnswerIsSkippedAndTheBoardStaysOnline();
    void aKeyEventSurvivesALostPollAnswer();
    void aBoardThatStopsAnsweringGoesOfflineAndTheOthersKeepRunning();
    void aCommandWaitingForADeadBoardFails();
    void commandsAreRefusedWhenTheyCannotBeQueued();
    void aNakIsReportedWithItsError();
    void aFillRunsFromPresetToTheAcknowledgedResult();
    void theLinkIsOpenedAgainAfterItDrops();
    void theBusWaitsForTheConverterToConnect();
    void aNewConverterConnectionReplacesTheOldOne();
    void aBusThatCannotListenSaysWhy();
    void answersCutIntoSingleBytesStillWork();
    void stoppingTheBusStopsThePolling();

    void theCardTablesAreMadeByTheMigration();
    void aCardIsRegisteredWithASaltedPinAndWaitsForTheUpload();
    void aCardCannotBeRegisteredWithBadInput();
    void aWrongPinCountsAndTheFifthLocksTheCard();
    void aRightPinCountsTheWrongOnesFromZeroAgain();
    void aTopUpIsACashCardSaleAndRaisesTheBalance();
    void aTopUpIsRefusedForABlockedCardAndForBadInput();
    void theCardReaderCutsItsBytesIntoIds();
    void theCardReaderEmitsTheIdsItReads();
    void aCardFillTypedInIsRecordedWithTheCard();
    void aSupplyRecordsTheModeAndTheDensity();
    void theUploadSendsCardsCardSalesAndCardSupplements();
    void aDownloadStoresCardsWithTheBalanceAndKeepsUnuploadedOnes();
    void theCardScreenRegistersTopsUpAndChecksThePin();
    void theCardActionsPutTheCursorInTheirFirstFieldAndEnterMovesOnWithThePinLast();
    void theCardActionsAskForAnOkBeforeTheyDoIt();
    void theCardScreenStartsOnTheFillPageWithTheTopUpButtonAboveHistory();
    void theFillPageMakesATicketForTheCardAndPrintsIt();
    void aFillIsATicketMadeForTheCardAndItIsCancelledWhileItWaitsForTheUpload();
    void aTicketMadeForACardIsUploadedWithItsCardAndTheDownloadedBalanceKeepsIt();
    void theHistoryOfACardShowsTheFillsOfItsTickets();
    void theUnusedPartOfACardsTicketGoesBackToTheCard();
    void anOldDatabaseGetsTheCardOfATicketAndKeepsItsTickets();
    void theConfirmDialogHasOkAsItsDefaultAndCancel();
    void theMachineConfigSavesTheCardReader();
    void salesHasOnlyTheSpotSaleAndTheTicketSale();
    void aCardOnTheReaderOpensTheCardsScreenWithTheCardShown();
    void aCardReadBeforeTheLoginOrOnTheCardsScreenKeepsTheScreen();
    void theCardHistoryDialogListsTheLedgerOfTheCardWithItsBalances();
    void theLedgerOfACardIsDownloadedAndKeptAsTheWebAppHasIt();
    void aRemainderGetsACommentOnTheRemaindersScreenAndItGoesUpWithTheNextUpload();
    void theDispensersScreenShowsTheNozzlesInDetail();
    void aSpotSaleSetDirectlySendsTheWeightToTheChosenNozzleWithoutATicket();
    void aSpotSaleSetDirectlyThatIsCancelledKeepsTheSaleAndItsTicket();
    void theSettingsPageChoosesWhatASpotSaleAndACardFillGive();
    void aCardFillSetDirectlyAsksForTheNozzleAndPrintsNothing();
    void theNozzleQuestionIsAnsweredWithTheKeyboardAlone();
    void grabAllScreens();
    void theThemeComesFromTheResourcesOrFromAFileThatIsReadAgainWhenSaved();
    void theSchemaIsTheInitialMigrationAndTheCardLedgerWithTheColumnsInTheOrderOfTheWebTables();

public slots:
    void answerTheSettleDialog();     // helper of the tests: the dialog of a ticket debt is open (modal): scan the tickets, then Settle or Close
    void answerTheMessageBox();       // helper of the tests: a question box is open (modal), note its text and press the button of boxAnswer_
    void answerTheNozzleQuestion();   // helper of the tests: the question of a spot sale set directly is open (modal), choose and press OK or Cancel
    void answerTheNozzleQuestionWithTheKeyboard(); // the same with the keys only: note where the cursor is, move with the arrows, Enter or Escape

private:
    QMessageBox::StandardButton boxAnswer_;
    QString boxText_;
    int nozzleToChoose_;
    QStringList settleTickets_;
    QStringList settleSeen_;   // what the dialog said after each ticket
    QString settleTotals_;
    bool settleNow_;
    int nozzleRows_;
    int nozzleRowsEnabled_;
    int nozzleFirstChosen_;     // the nozzle chosen when the question opened
    bool nozzleListHasFocus_;
    QSqlDatabase openDatabase(bool migrate = true);
    static void removeTranslators();
    void serverKnowsThisTerminal();
    bool testedOk(SetupFlow &flow, quint16 port);
    static QByteArray json(const char *text) { return QByteArray(text); }

    // JSON written with single quotes, which is far easier to read inside a C++ string.
    static QByteArray q(const char *text)
    {
        QByteArray bytes(text);
        bytes.replace('\'', '"');

        return bytes;
    }
    static QJsonArray rowsOf(const QString &text)
    {
        QString json = text;
        json.replace('\'', '"');

        return QJsonDocument::fromJson(json.toUtf8()).array();
    }
    QJsonObject configObject() const;
    int countRows(const QString &table) const;
    void configureServer();
    void serveEverything();
    bool downloadOk(SyncClient &client);
    void run(const QString &sql);
    void recordADay();
    void serveUploads();
    bool uploadOk(Uploader &uploader, QString *message);
    void addUser(int id, const QString &name, const QString &password, bool active = true, bool pos = true, bool deleted = false, bool legacy = false);
    QWidget *pageOf(MainWindow &window, const char *objectName);
    void setUpDoneTerminal();
    void setUpLoggedInTerminalWithAPin();
    void setUpSelling();
    void setUpSellingTerminal();
    void addStockTickets();
    void addLoansAndSoldTickets();
    void addRecordsOfSeveralDays();
    void addNozzles();
    void addNozzleRows(const QList<int> &numbers);
    void setUpSupplying();
    QVariant one(const QString &sql);

    QTemporaryDir *dir_;
    QString connection_;
    QString changeLog() const { return dir_->path() + "/changes.log"; } // the text file the Machine Config changes go to
    MachineConfig changes() const
    {
        MachineConfig config(QSqlDatabase::database(connection_));
        config.setLogFile(changeLog());

        return config;
    }
    FakeServer *server_;
};

// What the footer says about the records that wait for the upload (the home page is gone).
static QString footerWaiting(MainWindow &window)
{
    return window.findChild<QLabel *>("footerWaiting")->text();
}

void Smart210Test::init()
{
    ConfirmDialog::setAutoAnswer(ConfirmDialog::Yes); // a confirmation is answered OK at once: no window waits for a click in a test
    ApiClient::setMinIntervalMs(0); // the tests do not wait a second between calls
    dir_ = new QTemporaryDir;
    Settings::setDeviceFile(dir_->path() + "/device.ini"); // the machine configuration of each test is its own
    static int counter = 0;
    connection_ = "test_" + QString::number(++counter);
    server_ = new FakeServer;
    QVERIFY(server_->listen());
}

void Smart210Test::cleanup()
{
    Database::close(connection_);
    delete server_;
    delete dir_;
}

QSqlDatabase Smart210Test::openDatabase(bool migrate)
{
    if (QSqlDatabase::contains(connection_)) {
        return QSqlDatabase::database(connection_);
    }

    QString error;
    if (!Database::open(dir_->path() + "/data/smart210.db", connection_, &error)) {
        qFatal("Cannot open: %s", qPrintable(error));
    }
    if (migrate && !Migrator::apply(QSqlDatabase::database(connection_), Migrator::bundled(), &error)) {
        qFatal("Cannot migrate: %s", qPrintable(error));
    }

    return QSqlDatabase::database(connection_);
}

void Smart210Test::removeTranslators()
{
    foreach (QTranslator *translator, qApp->findChildren<QTranslator *>()) {
        qApp->removeTranslator(translator);
        delete translator;
    }
}

// ---- settings, language and font ----

void Smart210Test::languageNamesAreNormalized()
{
    QCOMPARE(AppConfig::normalizeLanguage("en-us"), QString("en_US"));
    QCOMPARE(AppConfig::normalizeLanguage(" EN_us "), QString("en_US"));
    QCOMPARE(AppConfig::normalizeLanguage("EN"), QString("en"));
}

void Smart210Test::onlyEnglishIsSupported()
{
    QVERIFY(AppConfig::isSupportedLanguage("en"));
    QVERIFY(!AppConfig::isSupportedLanguage("zh_CN"));
    QVERIFY(!AppConfig::isSupportedLanguage("zh_TW"));
    QVERIFY(!AppConfig::isSupportedLanguage("fr"));
    QVERIFY(!AppConfig::isSupportedLanguage(""));
}

void Smart210Test::everyLanguageHasAnEmbeddedTranslation_data()
{
    QTest::addColumn<QString>("language");
    QTest::addColumn<QString>("expected");

    QTest::newRow("en") << "en" << QString("Gas Station Cashier");
}

void Smart210Test::everyLanguageHasAnEmbeddedTranslation()
{
    QFETCH(QString, language);
    QFETCH(QString, expected);

    removeTranslators();
    QVERIFY(Translations::install(qApp, language));
    QCOMPARE(QCoreApplication::translate("MainWindow", "Gas Station Cashier"), expected);

    // A string with a placeholder keeps it, so .arg() still works after translation.
    QVERIFY(QCoreApplication::translate("MainWindow", "Version %1").contains("%1"));
    removeTranslators();
}

void Smart210Test::unknownLanguageLeavesEnglish()
{
    removeTranslators();
    QVERIFY(!Translations::install(qApp, "fr"));
    QVERIFY(!Translations::install(qApp, "zh_CN")); // Chinese is gone
    QCOMPARE(QCoreApplication::translate("MainWindow", "Gas Station Cashier"), QString("Gas Station Cashier"));
}

void Smart210Test::noFontFolderLeavesTheSystemFont()
{
    const QFont before = QApplication::font();

    QVERIFY(Fonts::install("this/folder/does/not/exist", 14).isEmpty());
    QCOMPARE(QApplication::font(), before);
}

// ---- database and migrations ----

void Smart210Test::databaseOpensInWalModeWithFullSync()
{
    QSqlDatabase db = openDatabase(false);

    QCOMPARE(Database::pragma(db, "journal_mode"), QString("wal"));
    QCOMPARE(Database::pragma(db, "synchronous"), QString("2")); // FULL
    QCOMPARE(Database::pragma(db, "foreign_keys"), QString("1"));
    QCOMPARE(Database::pragma(db, "busy_timeout"), QString("5000"));
    QVERIFY(QFileInfo(dir_->path() + "/data/smart210.db").exists()); // the folder was created too
}

void Smart210Test::bundledMigrationsCreateTheSchema()
{
    QSqlDatabase db = openDatabase();
    const QStringList scripts = Migrator::bundled();

    QVERIFY(!scripts.isEmpty());
    QCOMPARE(Migrator::version(db), scripts.size());

    QStringList tables;
    QSqlQuery query(db);
    QVERIFY(query.exec("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name"));
    while (query.next()) {
        tables << query.value(0).toString();
    }

    QStringList expected;
    expected << "customers" << "fuel_types" << "nozzles" << "remainders" << "sales" << "settings" << "supplements"
             << "tanks" << "ticket_loan_settlements" << "ticket_loans" << "tickets" << "users";
    foreach (const QString &table, expected) {
        QVERIFY2(tables.contains(table), qPrintable("missing table " + table));
    }
}

void Smart210Test::migratingAgainChangesNothing()
{
    QSqlDatabase db = openDatabase();
    Settings(db).setValue("keep", "me");
    const int version = Migrator::version(db);

    QString error;
    QVERIFY(Migrator::apply(db, Migrator::bundled(), &error));

    QCOMPARE(Migrator::version(db), version);
    QCOMPARE(Settings(db).value("keep"), QString("me"));
}

// the names of the columns of a table, in order (for the migration tests below)
static QStringList migratedColumnsOf(QSqlDatabase db, const QString &table)
{
    QStringList names;
    QSqlQuery query(db);
    if (query.exec("PRAGMA table_info(" + table + ")")) {
        while (query.next()) {
            names << query.value(1).toString();
        }
    }

    return names;
}

// 006: an old terminal database (made before cards.holder_phone was in 001_initial.sql) gets the column and keeps its rows
void Smart210Test::aMigrationAddsAColumnWhereAnOldDatabaseLacksIt()
{
    const QString phone = Migrator::bundled().at(5);
    QVERIFY(phone.contains("add-column-if-missing: cards holder_phone TEXT"));

    QSqlDatabase db = openDatabase(false);
    QStringList scripts;
    scripts << "CREATE TABLE cards (id TEXT, uid TEXT); INSERT INTO cards VALUES ('a', 'AA01');";
    QString error;
    QVERIFY2(Migrator::apply(db, scripts, &error), qPrintable(error));
    QVERIFY(!migratedColumnsOf(db, "cards").contains("holder_phone"));              // the old database

    scripts << phone;
    QVERIFY2(Migrator::apply(db, scripts, &error), qPrintable(error));
    QCOMPARE(Migrator::version(db), 2);
    QCOMPARE(migratedColumnsOf(db, "cards").join(" "), QString("id uid holder_phone"));
    QSqlQuery query(db);
    QVERIFY(query.exec("SELECT uid, holder_phone FROM cards"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toString(), QString("AA01"));                   // the row is still there
    QVERIFY(query.value(1).isNull());                                       // with no phone number
}

// a directive for a table that does not exist fails the script and changes nothing
void Smart210Test::aMigrationDirectiveForAMissingTableFailsTheScript()
{
    QSqlDatabase db = openDatabase(false);
    QStringList bad;
    bad << "CREATE TABLE a (x INTEGER);" << "-- add-column-if-missing: nowhere y TEXT\nCREATE TABLE b (x INTEGER);";
    QString error;
    QVERIFY(!Migrator::apply(db, bad, &error));
    QVERIFY(error.contains("Migration 2"));
    QCOMPARE(Migrator::version(db), 1);
    QSqlQuery query(db);
    QVERIFY(query.exec("SELECT name FROM sqlite_master WHERE name = 'b'"));
    QVERIFY(!query.next());                                                 // b was rolled back with the failed script
}

// 006 on a database that has the column already (made from the current 001_initial.sql): nothing is added twice and the phone numbers stay
void Smart210Test::aMigrationLeavesAColumnThatIsThereAndItsData()
{
    QSqlDatabase db = openDatabase(false);
    QStringList scripts;
    scripts << "CREATE TABLE cards (id TEXT, uid TEXT, holder_phone TEXT); INSERT INTO cards VALUES ('a', 'AA01', '138 0000 1111');" << Migrator::bundled().at(5);
    QString error;
    QVERIFY2(Migrator::apply(db, scripts, &error), qPrintable(error));

    QCOMPARE(Migrator::version(db), 2);
    QCOMPARE(migratedColumnsOf(db, "cards").count("holder_phone"), 1);
    QSqlQuery query(db);
    QVERIFY(query.exec("SELECT holder_phone FROM cards"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toString(), QString("138 0000 1111"));

    // and running it again changes nothing
    QVERIFY2(Migrator::apply(db, scripts, &error), qPrintable(error));
    QCOMPARE(migratedColumnsOf(db, "cards").count("holder_phone"), 1);
}

void Smart210Test::aDatabaseNewerThanTheProgramIsRefused()
{
    QSqlDatabase db = openDatabase(false);
    QSqlQuery(db).exec("PRAGMA user_version = 99");

    QString error;
    QVERIFY(!Migrator::apply(db, Migrator::bundled(), &error));
    QVERIFY(error.contains("99"));
}

void Smart210Test::aFailingMigrationRollsBackAndKeepsTheVersion()
{
    QSqlDatabase db = openDatabase(false);
    QStringList scripts;
    scripts << "CREATE TABLE a (x INTEGER);"
            << "CREATE TABLE b (x INTEGER); INSERT INTO missing_table VALUES (1);";

    QString error;
    QVERIFY(!Migrator::apply(db, scripts, &error));
    QVERIFY(error.contains("Migration 2"));

    QCOMPARE(Migrator::version(db), 1); // the first one stays done
    QSqlQuery query(db);
    QVERIFY(query.exec("SELECT name FROM sqlite_master WHERE name IN ('a', 'b') ORDER BY name"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toString(), QString("a"));
    QVERIFY(!query.next()); // b was rolled back with the failed script
}

void Smart210Test::laterMigrationsRunOnTopOfEarlierOnes()
{
    QSqlDatabase db = openDatabase(false);
    QStringList scripts;
    scripts << "CREATE TABLE a (x INTEGER); INSERT INTO a VALUES (7);";

    QString error;
    QVERIFY(Migrator::apply(db, scripts, &error));
    scripts << "ALTER TABLE a ADD COLUMN y INTEGER DEFAULT 5;";
    QVERIFY(Migrator::apply(db, scripts, &error));

    QCOMPARE(Migrator::version(db), 2);
    QSqlQuery query(db);
    QVERIFY(query.exec("SELECT x, y FROM a"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toInt(), 7); // the data from the first script is still there
    QCOMPARE(query.value(1).toInt(), 5);
}

void Smart210Test::statementsAreSplitOutsideCommentsAndQuotes()
{
    const QStringList statements = Migrator::splitStatements(
        "-- a comment; with a semicolon and an apostrophe: it's\n"
        "CREATE TABLE t (a TEXT DEFAULT 'x;y', b TEXT DEFAULT 'it''s'); -- trailing; comment\n"
        "INSERT INTO t (a) VALUES (\"q;r\");\n"
        "\n"
        "SELECT 1");

    QCOMPARE(statements.size(), 3);
    QVERIFY(statements.at(0).startsWith("CREATE TABLE t"));
    QVERIFY(statements.at(0).contains("'x;y'"));
    QVERIFY(statements.at(0).contains("'it''s'"));
    QVERIFY(statements.at(1).contains("\"q;r\""));
    QCOMPARE(statements.at(2), QString("SELECT 1"));
}

// The desktop SQLite used by these tests is newer than the 3.8.4 inside Qt 5.3.2, so it would accept syntax the board
// rejects. This catches the usual ones.
void Smart210Test::bundledMigrationsAvoidSyntaxNewerThanSqlite3_8_4()
{
    QStringList forbidden;
    forbidden << "DO UPDATE" << "DO NOTHING" << "RETURNING" << "RENAME COLUMN" << "DROP COLUMN" << "OVER (" << "OVER(";

    foreach (const QString &script, Migrator::bundled()) {
        QString code;
        foreach (const QString &statement, Migrator::splitStatements(script)) {
            code += statement.toUpper() + ";";
        }
        foreach (const QString &word, forbidden) {
            QVERIFY2(!code.contains(word), qPrintable("not available in SQLite 3.8.4: " + word));
        }
    }
}

// ---- settings ----

void Smart210Test::settingsStoreReplaceAndRemoveValues()
{
    Settings settings(openDatabase());

    QVERIFY(settings.setValue("server_host", "192.168.1.10"));
    QVERIFY(settings.setInt("server_port", 8123));
    QVERIFY(settings.setBool("initial_done", true));
    QCOMPARE(settings.value("server_host"), QString("192.168.1.10"));
    QCOMPARE(settings.intValue("server_port"), 8123);
    QVERIFY(settings.boolValue("initial_done"));
    QVERIFY(settings.contains("server_host"));

    QVERIFY(settings.setValue("server_host", "10.0.0.5")); // replaces, no second row
    QCOMPARE(settings.value("server_host"), QString("10.0.0.5"));
    QSqlQuery count(QSqlDatabase::database(connection_));
    QVERIFY(count.exec("SELECT COUNT(*) FROM settings"));
    QVERIFY(count.next());
    QCOMPARE(count.value(0).toInt(), 1); // only initial_done: the server address and port are machine configuration, kept in the device file

    QVERIFY(settings.setBool("initial_done", false));
    QVERIFY(!settings.boolValue("initial_done", true));

    QVERIFY(settings.remove("server_host"));
    QVERIFY(!settings.contains("server_host"));
}

void Smart210Test::settingsFallBackToTheDefault()
{
    Settings settings(openDatabase());

    QCOMPARE(settings.value("nothing", "fallback"), QString("fallback"));
    QCOMPARE(settings.intValue("nothing", 42), 42);
    QVERIFY(settings.boolValue("nothing", true));

    settings.setValue("port", "not a number");
    QCOMPARE(settings.intValue("port", 7), 7);
}

// ---- barcodes ----

void Smart210Test::barcodesBecomeIntegersAndBack()
{
    QCOMPARE(Barcode::toKey("0000003000000011"), qlonglong(3000000011LL));
    QCOMPARE(Barcode::fromKey(3000000011LL), QString("0000003000000011"));
    QCOMPARE(Barcode::fromKey(Barcode::toKey("9999999999999999")), QString("9999999999999999")); // the largest
    QCOMPARE(Barcode::fromKey(Barcode::toKey("0000000000000000")), QString("0000000000000000"));
}

void Smart210Test::barcodesWithLeadingZerosSurviveTheDatabase()
{
    QSqlDatabase db = openDatabase();
    const QString barcode = "0000003000000011";

    QSqlQuery insert(db);
    insert.prepare("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status) VALUES (?, 'D', 0, 500, 0)");
    insert.addBindValue(Barcode::toKey(barcode));
    QVERIFY(insert.exec());

    QSqlQuery select(db);
    select.prepare("SELECT barcode, typeof(barcode) FROM tickets WHERE barcode = ?");
    select.addBindValue(Barcode::toKey(barcode));
    QVERIFY(select.exec());
    QVERIFY(select.next());
    QCOMPARE(Barcode::fromKey(select.value(0).toLongLong()), barcode);
    QCOMPARE(select.value(1).toString(), QString("integer"));

    // The same barcode twice is refused: the key is unique.
    QVERIFY(!insert.exec());
}

void Smart210Test::invalidBarcodesAreRejected()
{
    QCOMPARE(Barcode::toKey("123"), qlonglong(-1));
    QCOMPARE(Barcode::toKey("00000030000000112"), qlonglong(-1));
    QCOMPARE(Barcode::toKey("000000300000001a"), qlonglong(-1));
    QCOMPARE(Barcode::toKey(""), qlonglong(-1));
    QVERIFY(!Barcode::isValid("-000000300000011"));
    QVERIFY(Barcode::isValid("0000003000000011"));
}

// ---- web API client ----

void Smart210Test::pingReturnsTheServerTime()
{
    server_->reply("/api/ping", 200, json("{\"status\":\"ok\",\"server_time\":\"2026-10-07 09:00:00\"}"));
    ApiClient client;
    client.setServer("127.0.0.1", server_->port());

    ApiReply *reply = client.ping();
    QSignalSpy spy(reply, SIGNAL(finished()));
    QVERIFY(spy.wait(3000));

    QVERIFY(reply->ok());
    QCOMPARE(reply->data().value("server_time").toString(), QString("2026-10-07 09:00:00"));
    QCOMPARE(server_->requests().size(), 1);
    QCOMPARE(server_->requests().at(0).method, QString("GET"));
    QCOMPARE(server_->requests().at(0).headers.value("accept"), QString("application/json"));
}

void Smart210Test::anErrorReplyShowsTheServersMessage()
{
    server_->reply("/api/device", 403, json("{\"status\":\"error\",\"message\":\"this IP address is not registered\"}"));
    ApiClient client;
    client.setServer("127.0.0.1", server_->port());

    ApiReply *reply = client.get("device");
    QSignalSpy spy(reply, SIGNAL(finished()));
    QVERIFY(spy.wait(3000));

    QVERIFY(!reply->ok());
    QVERIFY(!reply->isNetworkError());
    QCOMPARE(reply->httpStatus(), 403);
    QCOMPARE(reply->message(), QString("this IP address is not registered"));
}

void Smart210Test::callsLeaveOneIntervalApart()
{
    server_->reply("/api/ping", 200, json("{\"status\":\"ok\"}"));
    ApiClient client;
    client.setServer("127.0.0.1", server_->port());
    ApiClient other; // the spacing is the program's, not the client's
    other.setServer("127.0.0.1", server_->port());
    ApiClient::setMinIntervalMs(300);

    QTime time;
    time.start();
    ApiReply *first = client.ping();
    ApiReply *second = other.ping();
    ApiReply *third = client.ping();
    QSignalSpy spy(third, SIGNAL(finished()));
    QVERIFY(spy.wait(3000));
    ApiClient::setMinIntervalMs(0);

    QVERIFY(first->ok() && second->ok() && third->ok());
    QVERIFY2(time.elapsed() >= 590, qPrintable(QString::number(time.elapsed())));
}

void Smart210Test::aBusyServerIsRecognized()
{
    server_->reply("/api/ping", 503, json("{\"status\":\"busy\"}"));
    ApiClient client;
    client.setServer("127.0.0.1", server_->port());

    ApiReply *reply = client.ping();
    QSignalSpy spy(reply, SIGNAL(finished()));
    QVERIFY(spy.wait(3000));

    QVERIFY(!reply->ok());
    QVERIFY(reply->isBusy());
    QVERIFY(reply->message().contains("busy"));
}

void Smart210Test::aTooManyRequestsAnswerIsWaitedOutAndAskedAgain()
{
    server_->setRetryAfter("1");
    server_->reply("/api/ping", 429, json("{\"message\":\"Too Many Attempts.\"}"));
    server_->replyNext("/api/ping", 200, json("{\"status\":\"ok\"}"));
    ApiClient client;
    client.setServer("127.0.0.1", server_->port());
    ApiClient other; // the program holds back, not only the client that was refused
    other.setServer("127.0.0.1", server_->port());

    QTime time;
    time.start();
    ApiReply *reply = client.ping();
    QSignalSpy throttled(reply, SIGNAL(throttled(int)));
    QSignalSpy finished(reply, SIGNAL(finished()));
    QVERIFY(throttled.wait(3000));
    QCOMPARE(throttled.at(0).at(0).toInt(), 1000);                       // what Retry-After said
    QCOMPARE(finished.size(), 0);                                        // nothing failed

    ApiReply *later = other.ping();                                      // asked while the pause runs: it waits as well
    QSignalSpy laterDone(later, SIGNAL(finished()));
    QVERIFY(finished.wait(5000));
    QVERIFY(laterDone.size() > 0 || laterDone.wait(5000));

    QVERIFY(reply->ok());
    QCOMPARE(reply->retries(), 1);
    QCOMPARE(server_->log(), QStringList() << "GET /api/ping" << "GET /api/ping" << "GET /api/ping"); // refused, asked again, the other call
    QVERIFY2(time.elapsed() >= 950, qPrintable(QString::number(time.elapsed())));
    QVERIFY(later->ok());
}

void Smart210Test::aServerThatKeepsRefusingWithTooManyRequestsEndsAsBusy()
{
    server_->setRetryAfter("0");                                         // asked again at once, to see the end
    server_->reply("/api/ping", 429, json("{\"message\":\"Too Many Attempts.\"}"));
    ApiClient client;
    client.setServer("127.0.0.1", server_->port());

    ApiReply *reply = client.ping();
    QSignalSpy finished(reply, SIGNAL(finished()));
    QVERIFY(finished.wait(10000));

    QVERIFY(!reply->ok());
    QVERIFY(reply->isBusy());                                            // kept data, tried again by the next beat
    QVERIFY2(reply->message().contains("too many requests"), qPrintable(reply->message()));
    QCOMPARE(reply->retries(), ApiReply::MaxRetries);
    QCOMPARE(server_->log().size(), 1 + ApiReply::MaxRetries);

    // without a Retry-After the wait is five seconds, and a wait that would pass the total is not made
    QCOMPARE(int(ApiReply::DefaultWaitMs), 5000);
    QVERIFY(ApiReply::MaxRetries * ApiReply::DefaultWaitMs <= ApiReply::MaxWaitMs);
}

void Smart210Test::anAnswerThatIsNotJsonIsAnError()
{
    server_->reply("/api/ping", 200, json("<html>login page</html>"));
    ApiClient client;
    client.setServer("127.0.0.1", server_->port());

    ApiReply *reply = client.ping();
    QSignalSpy spy(reply, SIGNAL(finished()));
    QVERIFY(spy.wait(3000));

    QVERIFY(!reply->ok());
    QVERIFY(reply->isNetworkError());
    QVERIFY(reply->message().contains("unexpected"));
}

void Smart210Test::aClosedPortIsANetworkError()
{
    const quint16 port = server_->port();
    server_->close(); // nothing listens any more

    ApiClient client;
    client.setServer("127.0.0.1", port);
    ApiReply *reply = client.ping();
    QSignalSpy spy(reply, SIGNAL(finished()));
    QVERIFY(spy.wait(10000));

    QVERIFY(!reply->ok());
    QVERIFY(reply->isNetworkError());
    QVERIFY(reply->message().startsWith("Cannot reach the server"));
}

void Smart210Test::aServerThatNeverAnswersTimesOut()
{
    server_->hang("/api/ping");
    ApiClient client;
    client.setServer("127.0.0.1", server_->port());
    client.setTimeoutMs(300);

    ApiReply *reply = client.ping();
    QSignalSpy spy(reply, SIGNAL(finished()));
    QVERIFY(spy.wait(5000));

    QVERIFY(reply->isNetworkError());
    QVERIFY(reply->message().contains("in time"));
}

void Smart210Test::postsSendTheJsonBody()
{
    server_->reply("/api/push/sales", 200, json("{\"status\":\"ok\"}"));
    ApiClient client;
    client.setServer("127.0.0.1", server_->port());

    QJsonObject body;
    body.insert("rows", QJsonArray());
    ApiReply *reply = client.post("push/sales", body);
    QSignalSpy spy(reply, SIGNAL(finished()));
    QVERIFY(spy.wait(3000));

    QVERIFY(reply->ok());
    const FakeServer::Request request = server_->requests().at(0);
    QCOMPARE(request.method, QString("POST"));
    QVERIFY(!request.headers.contains("authorization")); // the address is the identity, nothing to send
    QCOMPARE(request.headers.value("content-type"), QString("application/json"));
    QCOMPARE(QJsonDocument::fromJson(request.body).object().value("rows").toArray().size(), 0);
}

// ---- first-run setup ----

// The web app answers anyone's ping, and says whether the caller's address is a registered device.
void Smart210Test::serverKnowsThisTerminal()
{
    server_->reply("/api/ping", 200, json("{\"status\":\"ok\",\"server_time\":\"2026-10-07 09:00:00\",\"registered\":true,"
                                          "\"device_id\":3,\"device_name\":\"POS1\",\"ip\":\"192.168.1.50\"}"));
}

bool Smart210Test::testedOk(SetupFlow &flow, quint16 port)
{
    QSignalSpy spy(&flow, SIGNAL(connectionTested(bool,QString)));
    flow.testConnection("127.0.0.1", port);
    if (spy.isEmpty() && !spy.wait(10000)) {
        return false;
    }

    return spy.at(0).at(0).toBool();
}

void Smart210Test::aRegisteredTerminalIsConfiguredAndTheServerIsSaved()
{
    serverKnowsThisTerminal();
    Settings settings(openDatabase());
    SetupFlow flow(&settings);
    QVERIFY(!flow.isConfigured());

    QSignalSpy spy(&flow, SIGNAL(connectionTested(bool,QString)));
    flow.testConnection(" 127.0.0.1 ", server_->port());
    QVERIFY(spy.wait(3000));

    QVERIFY2(spy.at(0).at(0).toBool(), qPrintable(spy.at(0).at(1).toString()));
    QVERIFY(spy.at(0).at(1).toString().contains("POS1"));
    QVERIFY(flow.isConfigured());
    QCOMPARE(flow.serverTime(), QString("2026-10-07 09:00:00"));
    QCOMPARE(settings.value(SettingKey::ServerHost), QString("127.0.0.1"));
    QCOMPARE(settings.intValue(SettingKey::ServerPort), int(server_->port()));
    QCOMPARE(settings.intValue(SettingKey::DeviceId), 3);
    QCOMPARE(settings.value(SettingKey::DeviceName), QString("POS1"));
    QCOMPARE(flow.savedHost(), QString("127.0.0.1"));

    // Nothing but a ping went to the server: no sign-in, no cashier.
    QCOMPARE(server_->requests().size(), 1);
    QCOMPARE(server_->requests().at(0).path, QString("/api/ping"));
}

void Smart210Test::anAddressTheServerDoesNotKnowIsNotConfigured()
{
    server_->reply("/api/ping", 200, json("{\"status\":\"ok\",\"server_time\":\"2026-10-07 09:00:00\",\"registered\":false,"
                                          "\"device_id\":null,\"device_name\":null,\"ip\":\"192.168.1.77\"}"));
    Settings settings(openDatabase());
    SetupFlow flow(&settings);

    QSignalSpy spy(&flow, SIGNAL(connectionTested(bool,QString)));
    flow.testConnection("127.0.0.1", server_->port());
    QVERIFY(spy.wait(3000));

    QVERIFY(!spy.at(0).at(0).toBool());
    QVERIFY(spy.at(0).at(1).toString().contains("192.168.1.77")); // tells the admin which address to add
    QVERIFY(spy.at(0).at(1).toString().contains("not registered"));
    QVERIFY(!flow.isConfigured());
    QVERIFY(!settings.contains(SettingKey::ServerHost));
}

void Smart210Test::aBusyServerFailsTheTest()
{
    server_->reply("/api/ping", 503, json("{\"status\":\"busy\"}"));
    Settings settings(openDatabase());
    SetupFlow flow(&settings);

    QSignalSpy spy(&flow, SIGNAL(connectionTested(bool,QString)));
    flow.testConnection("127.0.0.1", server_->port());
    QVERIFY(spy.wait(3000));

    QVERIFY(!spy.at(0).at(0).toBool());
    QVERIFY(!flow.isConfigured());
}

void Smart210Test::badAddressAndPortAreRefusedBeforeAnyRequest()
{
    Settings settings(openDatabase());
    SetupFlow flow(&settings);
    QSignalSpy spy(&flow, SIGNAL(connectionTested(bool,QString)));

    flow.testConnection("  ", 80);
    flow.testConnection("127.0.0.1", 0);
    flow.testConnection("127.0.0.1", 70000);

    QCOMPARE(spy.size(), 3); // answered at once, no waiting
    for (int i = 0; i < 3; ++i) {
        QVERIFY(!spy.at(i).at(0).toBool());
    }
    QCOMPARE(server_->requests().size(), 0);
}

void Smart210Test::aServerThatCannotBeReachedFailsTheTest()
{
    serverKnowsThisTerminal();
    Settings settings(openDatabase());
    SetupFlow flow(&settings);
    const quint16 port = server_->port();
    server_->close();

    QVERIFY(!testedOk(flow, port));
    QVERIFY(!flow.isConfigured());
}

void Smart210Test::aSavedConfigurationSurvivesARestart()
{
    serverKnowsThisTerminal();
    QSqlDatabase db = openDatabase();
    {
        Settings settings(db);
        SetupFlow flow(&settings);
        QVERIFY(testedOk(flow, server_->port()));
    }

    Settings again(db); // what the next start sees
    SetupFlow restarted(&again);
    QVERIFY(restarted.isConfigured());
    QCOMPARE(restarted.savedHost(), QString("127.0.0.1"));
    QCOMPARE(restarted.savedPort(), int(server_->port()));
}

// ---- decimals ----

void Smart210Test::decimalsAreParsedToIntegers()
{
    qlonglong v = 0;

    QVERIFY(Decimal::parse("12.50", 2, &v));    QCOMPARE(v, qlonglong(1250));
    QVERIFY(Decimal::parse("12.5", 2, &v));     QCOMPARE(v, qlonglong(1250));
    QVERIFY(Decimal::parse("12", 2, &v));       QCOMPARE(v, qlonglong(1200));
    QVERIFY(Decimal::parse(".5", 2, &v));       QCOMPARE(v, qlonglong(50));
    QVERIFY(Decimal::parse("0.05", 2, &v));     QCOMPARE(v, qlonglong(5));
    QVERIFY(Decimal::parse("-0.05", 2, &v));    QCOMPARE(v, qlonglong(-5));
    QVERIFY(Decimal::parse(" +7.10 ", 2, &v));  QCOMPARE(v, qlonglong(710));
    QVERIFY(Decimal::parse("0.5400", 4, &v));   QCOMPARE(v, qlonglong(5400));
    QVERIFY(Decimal::parse("99999999.99", 2, &v)); QCOMPARE(v, qlonglong(9999999999LL));
    QVERIFY(Decimal::parse("3", 0, &v));        QCOMPARE(v, qlonglong(3));
}

void Smart210Test::extraDigitsAreRoundedAndBadTextIsRefused()
{
    qlonglong v = 0;

    QVERIFY(Decimal::parse("1.005", 2, &v));  QCOMPARE(v, qlonglong(101));
    QVERIFY(Decimal::parse("1.004", 2, &v));  QCOMPARE(v, qlonglong(100));
    QVERIFY(Decimal::parse("-1.005", 2, &v)); QCOMPARE(v, qlonglong(-101));
    QVERIFY(Decimal::parse("2.999", 2, &v));  QCOMPARE(v, qlonglong(300));

    const char *bad[] = {"", " ", "abc", "1.2.3", "1e5", "--1", "1,5", ".", "-", "123456789012345.00"};
    for (unsigned i = 0; i < sizeof(bad) / sizeof(bad[0]); ++i) {
        QVERIFY2(!Decimal::parse(bad[i], 2, &v), bad[i]);
    }
    QCOMPARE(Decimal::parseOr("x", 2, 77), qlonglong(77));
}

void Smart210Test::decimalsAreFormattedBack()
{
    QCOMPARE(Decimal::format(1250, 2), QString("12.50"));
    QCOMPARE(Decimal::format(5, 2), QString("0.05"));
    QCOMPARE(Decimal::format(-5, 2), QString("-0.05"));
    QCOMPARE(Decimal::format(0, 2), QString("0.00"));
    QCOMPARE(Decimal::format(5400, 4), QString("0.5400"));
    QCOMPARE(Decimal::format(7, 0), QString("7"));
    QCOMPARE(Decimal::format(-123456, 2), QString("-1234.56"));

    // what goes out is what came in
    foreach (const QString &text, QStringList() << "0.00" << "12.50" << "-7.01" << "99999999.99") {
        QCOMPARE(Decimal::format(Decimal::parseOr(text, 2), 2), text);
    }
}

void Smart210Test::jsonNumbersAndStringsAreBothDecimals()
{
    bool ok = false;

    QCOMPARE(Decimal::fromJson(QJsonValue(QString("12.50")), 2, &ok), qlonglong(1250));
    QVERIFY(ok);
    QCOMPARE(Decimal::fromJson(QJsonValue(12.5), 2, &ok), qlonglong(1250));
    QVERIFY(ok);
    QCOMPARE(Decimal::fromJson(QJsonValue(0.07), 2, &ok), qlonglong(7)); // not 6 from a binary fraction
    QVERIFY(ok);
    Decimal::fromJson(QJsonValue(), 2, &ok);
    QVERIFY(!ok);
    Decimal::fromJson(QJsonValue(QJsonValue::Null), 2, &ok);
    QVERIFY(!ok);
}

// ---- storing what the web app sent ----

void Smart210Test::configIsReplacedWholeEveryTime()
{
    SyncStore store(openDatabase());
    QString error;

    QVERIFY2(store.applyConfig(configObject(), &error), qPrintable(error));
    QCOMPARE(countRows("fuel_types"), 2);
    QCOMPARE(countRows("tanks"), 1);
    QCOMPARE(countRows("nozzles"), 0); // the nozzles are set up on the terminal: a config that lists them (an older server) changes nothing

    QSqlQuery query(QSqlDatabase::database(connection_));
    QVERIFY(query.exec("SELECT price, density FROM fuel_types ORDER BY fuel_type"));
    QVERIFY(query.next()); // A: no density
    QCOMPARE(query.value(0).toLongLong(), qlonglong(800));
    QVERIFY(query.value(1).isNull());
    QVERIFY(query.next()); // D
    QCOMPARE(query.value(0).toLongLong(), qlonglong(750));
    QCOMPARE(query.value(1).toLongLong(), qlonglong(8300));
    QVERIFY(query.exec("SELECT capacity_kg, stock_kg FROM tanks"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toLongLong(), qlonglong(5000000));
    QCOMPARE(query.value(1).toLongLong(), qlonglong(123450));

    Settings settings(QSqlDatabase::database(connection_));
    QCOMPARE(settings.intValue(SettingKey::PurgeDays), 1500);
    QCOMPARE(settings.intValue(SettingKey::TicketSerialBase), 500000000);
    QCOMPARE(settings.value(SettingKey::PinSalt), QString("ps"));
    QCOMPARE(settings.value(SettingKey::PinHash), QString("ph"));

    // The next config has fewer things and no PIN: what disappeared on the web disappears here.
    QJsonObject smaller;
    smaller.insert("ticket_serial_base", 500000000);
    smaller.insert("fuel_types", QJsonDocument::fromJson(q("[{'fuel_type':'D','name':'Diesel','price':'7.60','density':'0.8300'}]")).array());
    QJsonObject device;
    device.insert("purge_days", 3);
    device.insert("pin_salt", QJsonValue(QJsonValue::Null));
    device.insert("pin_hash", QJsonValue(QJsonValue::Null));
    smaller.insert("device", device);
    QVERIFY(store.applyConfig(smaller, &error));

    QCOMPARE(countRows("fuel_types"), 1);
    QCOMPARE(countRows("tanks"), 0);
    QCOMPARE(countRows("nozzles"), 0);
    QCOMPARE(settings.intValue(SettingKey::PurgeDays), 3);
    QVERIFY(!settings.contains(SettingKey::PinHash));
}

void Smart210Test::usersAndCustomersAreStoredAndUpdated()
{
    SyncStore store(openDatabase());

    SyncStore::PageResult users = store.applyPage("users", rowsOf(
        "[{'id':5,'username':'cash','name':'Cash One','role':'cashier','pos':true,'active':true,'pos_salt':'aa11','pos_hash':'hh','deleted':false},"
        "{'id':6,'username':'old','name':'Old','role':'cashier','pos':true,'active':false,'pos_salt':null,'pos_hash':null,'deleted':true}]"));
    QVERIFY(users.ok);
    QCOMPARE(users.stored, 2);
    QCOMPARE(users.keys, QStringList() << "5" << "6");

    QSqlQuery query(QSqlDatabase::database(connection_));
    QVERIFY(query.exec("SELECT name, pos, active, deleted, pos_hash FROM users ORDER BY id"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toString(), QString("Cash One"));
    QCOMPARE(query.value(1).toInt(), 1);
    QCOMPARE(query.value(4).toString(), QString("hh"));
    QVERIFY(query.next());
    QCOMPARE(query.value(2).toInt(), 0);
    QCOMPARE(query.value(3).toInt(), 1); // kept, marked deleted: sales still point at it
    QVERIFY(query.value(4).isNull());

    // the same user again with a new name: updated, not duplicated
    store.applyPage("users", rowsOf("[{'id':5,'username':'cash','name':'Cash Renamed','role':'cashier','pos':true,'active':true,'pos_salt':'aa11','pos_hash':'hh2','deleted':false}]"));
    QCOMPARE(countRows("users"), 2);
    QVERIFY(query.exec("SELECT name, pos_hash FROM users WHERE id = 5"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toString(), QString("Cash Renamed"));
    QCOMPARE(query.value(1).toString(), QString("hh2"));

    SyncStore::PageResult customers = store.applyPage("customers", rowsOf(
        "[{'id':1,'name':'Self','phone':null,'is_self':true,'deleted':false},{'id':2,'name':'ACME','phone':'123','is_self':false,'deleted':false}]"));
    QCOMPARE(customers.stored, 2);
    QVERIFY(query.exec("SELECT is_self, phone FROM customers ORDER BY id"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toInt(), 1);
    QVERIFY(query.value(1).isNull());

    // the web app names the flag is_default (and still sends is_self, the same value): the new name counts when it is there
    customers = store.applyPage("customers", rowsOf(
        "[{'id':4,'name':'Individual','phone':null,'is_default':true,'is_self':false,'deleted':false},{'id':5,'name':'Other','phone':null,'is_default':false,'is_self':true,'deleted':false}]"));
    QCOMPARE(customers.stored, 2);
    QVERIFY(query.exec("SELECT is_self FROM customers WHERE id IN (4, 5) ORDER BY id"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toInt(), 1);
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toInt(), 0);
}

void Smart210Test::ticketsAreStoredWithIntegerKeysAndAmounts()
{
    SyncStore store(openDatabase());

    SyncStore::PageResult page = store.applyPage("tickets", rowsOf(
        "[{'barcode':'0000003000000011','fuel_type':'D','spec':0,'amount_kg':'5.00','status':0,'sale_id':null,'sale_date':null},"
        "{'barcode':'0000021500000001','fuel_type':'D','spec':null,'amount_kg':'7.55','status':1,'sale_id':'s-1','sale_date':'2026-10-06 09:00:00'}]"));

    QVERIFY(page.ok);
    QCOMPARE(page.keys, QStringList() << "0000003000000011" << "0000021500000001"); // acknowledged as the server wrote them
    QSqlQuery query(QSqlDatabase::database(connection_));
    QVERIFY(query.exec("SELECT barcode, spec, amount_kg, status, sale_id, synced FROM tickets ORDER BY barcode"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toLongLong(), qlonglong(3000000011LL));
    QCOMPARE(query.value(1).toInt(), 0);
    QCOMPARE(query.value(2).toLongLong(), qlonglong(500));
    QCOMPARE(query.value(5).toInt(), 1);
    QVERIFY(query.next());
    QVERIFY(query.value(1).isNull()); // a direct ticket has no spec
    QCOMPARE(query.value(2).toLongLong(), qlonglong(755));
    QCOMPARE(query.value(4).toString(), QString("s-1"));
}

void Smart210Test::aPulledTicketKeepsWhatTheDeviceAddedAndNeverOverwritesUnsyncedWork()
{
    QSqlDatabase db = openDatabase();
    SyncStore store(db);
    QSqlQuery query(db);

    store.applyPage("tickets", rowsOf("[{'barcode':'0000003000000011','fuel_type':'D','spec':0,'amount_kg':'5.00','status':1,'sale_id':'s-1','sale_date':'2026-10-06 09:00:00'},"
                                      "{'barcode':'0000003000000012','fuel_type':'D','spec':0,'amount_kg':'5.00','status':0,'sale_id':null,'sale_date':null}]"));
    // The cashier supplies the first one and has not uploaded yet; the second is untouched.
    QVERIFY(query.exec("UPDATE tickets SET status = 2, supply_date = '2026-10-07 08:00:00', synced = 0 WHERE barcode = 3000000011"));

    // The web app still thinks both are in their old state (and the second one was restricted meanwhile).
    SyncStore::PageResult page = store.applyPage("tickets", rowsOf(
        "[{'barcode':'0000003000000011','fuel_type':'D','spec':0,'amount_kg':'5.00','status':1,'sale_id':'s-1','sale_date':'2026-10-06 09:00:00'},"
        "{'barcode':'0000003000000012','fuel_type':'D','spec':0,'amount_kg':'5.00','status':3,'sale_id':null,'sale_date':null}]"));

    QCOMPARE(page.stored, 1);
    QCOMPARE(page.skipped, 1);
    QCOMPARE(page.keys, QStringList() << "0000003000000012"); // the skipped one is NOT acknowledged: it comes again later
    QVERIFY(query.exec("SELECT status, supply_date, synced FROM tickets WHERE barcode = 3000000011"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toInt(), 2); // the local supply stands
    QCOMPARE(query.value(1).toString(), QString("2026-10-07 08:00:00"));
    QCOMPARE(query.value(2).toInt(), 0);
    QVERIFY(query.exec("SELECT status FROM tickets WHERE barcode = 3000000012"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toInt(), 3);

    // After the upload (synced = 1) the web app's version is taken, and what the web does not send stays.
    QVERIFY(query.exec("UPDATE tickets SET synced = 1 WHERE barcode = 3000000011"));
    store.applyPage("tickets", rowsOf("[{'barcode':'0000003000000011','fuel_type':'D','spec':0,'amount_kg':'5.00','status':2,'sale_id':'s-1','sale_date':'2026-10-06 09:00:00'}]"));
    QVERIFY(query.exec("SELECT status, supply_date FROM tickets WHERE barcode = 3000000011"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toInt(), 2);
    QCOMPARE(query.value(1).toString(), QString("2026-10-07 08:00:00"));
}

void Smart210Test::loansKeepTheirLocalFieldsAndUnsyncedLoansAreNotOverwritten()
{
    QSqlDatabase db = openDatabase();
    SyncStore store(db);
    QSqlQuery query(db);
    const QString loan = "11111111-1111-1111-1111-111111111111";

    store.applyPage("ticket_loans", rowsOf("[{'id':'" + loan + "','customer_id':1,'fuel_type':'D','amount_kg':'20.00','settled_amount_kg':'0.00',"
                                           "'loaned_at':'2026-10-06 10:00:00','sale_id':null,'deleted':false}]"));
    // made on the device: it has a date and cashier, and a settlement raised the settled amount
    QVERIFY(query.exec("UPDATE ticket_loans SET date = '2026-10-07', user_id = 5, settled_amount_kg = 500, synced = 0"));

    SyncStore::PageResult page = store.applyPage("ticket_loans", rowsOf(
        "[{'id':'" + loan + "','customer_id':1,'fuel_type':'D','amount_kg':'20.00','settled_amount_kg':'0.00','loaned_at':'2026-10-06 10:00:00','sale_id':null,'deleted':false}]"));
    QCOMPARE(page.skipped, 1);
    QCOMPARE(page.keys.size(), 0);
    QVERIFY(query.exec("SELECT settled_amount_kg FROM ticket_loans"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toLongLong(), qlonglong(500));

    // once uploaded, the server's total (which now includes the settlement) replaces it, and the local fields stay
    QVERIFY(query.exec("UPDATE ticket_loans SET synced = 1"));
    store.applyPage("ticket_loans", rowsOf("[{'id':'" + loan + "','customer_id':1,'fuel_type':'D','amount_kg':'20.00','settled_amount_kg':'5.00',"
                                           "'loaned_at':'2026-10-06 10:00:00','sale_id':null,'deleted':true}]"));
    QVERIFY(query.exec("SELECT settled_amount_kg, deleted, date, user_id FROM ticket_loans"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toLongLong(), qlonglong(500));
    QCOMPARE(query.value(1).toInt(), 1);
    QCOMPARE(query.value(2).toString(), QString("2026-10-07"));
    QCOMPARE(query.value(3).toInt(), 5);
}

void Smart210Test::aBadRowRollsBackItsWholePage()
{
    SyncStore store(openDatabase());

    SyncStore::PageResult page = store.applyPage("tickets", rowsOf(
        "[{'barcode':'0000003000000011','fuel_type':'D','spec':0,'amount_kg':'5.00','status':0,'sale_id':null,'sale_date':null},"
        "{'barcode':'not a barcode','fuel_type':'D','spec':0,'amount_kg':'5.00','status':0,'sale_id':null,'sale_date':null}]"));

    QVERIFY(!page.ok);
    QVERIFY(page.error.contains("not a barcode"));
    QCOMPARE(page.keys.size(), 0);
    QCOMPARE(countRows("tickets"), 0); // not even the good row stays: the page is sent again as a whole
    QVERIFY(!store.applyPage("nonsense", QJsonArray()).ok);
}

// ---- downloading ----

void Smart210Test::theFirstDownloadBeginsStoresEverythingAndOpensTheGate()
{
    configureServer();
    serveEverything();
    SyncClient client(QSqlDatabase::database(connection_));

    QVERIFY(downloadOk(client));

    QStringList expected;
    expected << "POST /api/sync/begin" << "GET /api/pull/config"
             << "GET /api/pull/users?limit=200" << "POST /api/pull/users/ack"
             << "GET /api/pull/customers?limit=200" << "POST /api/pull/customers/ack"
             << "GET /api/pull/tickets?limit=200" << "POST /api/pull/tickets/ack"
             << "GET /api/pull/ticket_loans?limit=200" << "POST /api/pull/ticket_loans/ack"
             << "GET /api/pull/cards?limit=200" << "POST /api/pull/cards/ack"
             << "GET /api/pull/card_transactions?limit=200" << "POST /api/pull/card_transactions/ack"
             << "POST /api/sync/initial-done";
    QCOMPARE(server_->log(), expected);

    QCOMPARE(countRows("users"), 1);
    QCOMPARE(countRows("customers"), 1);
    QCOMPARE(countRows("tickets"), 1);
    QCOMPARE(countRows("ticket_loans"), 1);
    QCOMPARE(countRows("cards"), 1);
    QCOMPARE(countRows("fuel_types"), 2);

    Settings settings(QSqlDatabase::database(connection_));
    QVERIFY(settings.boolValue(SettingKey::InitialDone));
    QVERIFY(settings.boolValue(SettingKey::SyncBegun));
    QVERIFY(!settings.value(SettingKey::LastDownloadAt).isEmpty());

    // the acknowledgement names the rows stored and the page's server time
    const QJsonObject ack = QJsonDocument::fromJson(server_->requests().at(3).body).object();
    QCOMPARE(ack.value("keys").toArray().at(0).toString(), QString("5"));
    QCOMPARE(ack.value("pulled_at").toString(), QString("2026-10-07 09:00:00"));
}

void Smart210Test::laterDownloadsSkipBeginAndInitialDoneAndEmptyAcks()
{
    configureServer();
    serveEverything();
    SyncClient client(QSqlDatabase::database(connection_));
    QVERIFY(downloadOk(client));

    // Nothing changed on the web: every page is empty.
    const QByteArray empty = q("{'status':'ok','rows':[],'more':false,'server_time':'2026-10-07 10:00:00'}");
    foreach (const QString &table, QStringList() << "users" << "customers" << "tickets" << "ticket_loans" << "cards" << "card_transactions") {
        server_->reply("/api/pull/" + table + "?limit=200", 200, empty);
    }
    const int before = server_->requests().size();

    QVERIFY(downloadOk(client));

    QStringList expected;
    expected << "GET /api/pull/config" << "GET /api/pull/users?limit=200" << "GET /api/pull/customers?limit=200"
             << "GET /api/pull/tickets?limit=200" << "GET /api/pull/ticket_loans?limit=200" << "GET /api/pull/cards?limit=200"
             << "GET /api/pull/card_transactions?limit=200";
    QCOMPARE(QStringList(server_->log().mid(before)), expected); // Qt 5.3 compares a QList<QString> with a QStringList only after the conversion
}

void Smart210Test::pagesAreFollowedUntilThereAreNoMore()
{
    configureServer();
    serveEverything();
    server_->reply("/api/pull/customers?limit=200", 200, q("{'status':'ok','rows':[{'id':1,'name':'A','phone':null,'is_self':false,'deleted':false},"
                                                           "{'id':2,'name':'B','phone':null,'is_self':false,'deleted':false}],'more':true,'server_time':'2026-10-07 09:00:00'}"));
    server_->replyNext("/api/pull/customers?limit=200", 200, q("{'status':'ok','rows':[{'id':3,'name':'C','phone':null,'is_self':false,'deleted':false}],"
                                                               "'more':false,'server_time':'2026-10-07 09:00:05'}"));
    SyncClient client(QSqlDatabase::database(connection_));

    QVERIFY(downloadOk(client));

    QCOMPARE(countRows("customers"), 3);
    int pages = 0;
    int acks = 0;
    foreach (const QString &line, server_->log()) {
        pages += line.startsWith("GET /api/pull/customers");
        acks += (line == "POST /api/pull/customers/ack");
    }
    QCOMPARE(pages, 2);
    QCOMPARE(acks, 2);
}

void Smart210Test::unuploadedRowsAreKeptNotAcknowledgedAndDoNotLoopForever()
{
    configureServer();
    serveEverything();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    QSqlQuery query(db);
    SyncStore(db).applyPage("tickets", rowsOf("[{'barcode':'0000003000000011','fuel_type':'D','spec':0,'amount_kg':'5.00','status':1,'sale_id':'s','sale_date':'2026-10-06 09:00:00'}]"));
    QVERIFY(query.exec("UPDATE tickets SET status = 2, synced = 0"));

    // The web app keeps offering that ticket, and says there is more: a page of nothing but kept rows must end the table.
    server_->reply("/api/pull/tickets?limit=200", 200, q("{'status':'ok','rows':[{'barcode':'0000003000000011','fuel_type':'D','spec':0,'amount_kg':'5.00',"
                                                         "'status':1,'sale_id':'s','sale_date':'2026-10-06 09:00:00'}],'more':true,'server_time':'2026-10-07 09:00:00'}"));
    SyncClient client(db);
    QSignalSpy spy(&client, SIGNAL(downloadFinished(bool,QString)));

    client.download();
    QVERIFY(spy.wait(10000));

    QVERIFY(spy.at(0).at(0).toBool());
    QVERIFY(spy.at(0).at(1).toString().contains("kept until they are uploaded"));
    QVERIFY(!server_->log().contains("POST /api/pull/tickets/ack")); // nothing was stored there, so nothing is acknowledged
    int pages = 0;
    foreach (const QString &line, server_->log()) {
        pages += line.startsWith("GET /api/pull/tickets");
    }
    QCOMPARE(pages, 1);
    QVERIFY(query.exec("SELECT status FROM tickets"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toInt(), 2);
}

void Smart210Test::anUnregisteredAddressStopsTheFirstDownloadAtOnce()
{
    configureServer();
    server_->reply("/api/sync/begin", 403, q("{'status':'error','message':'this IP address is not registered'}"));
    SyncClient client(QSqlDatabase::database(connection_));
    QSignalSpy spy(&client, SIGNAL(downloadFinished(bool,QString)));

    client.download();
    QVERIFY(spy.wait(5000));

    QVERIFY(!spy.at(0).at(0).toBool());
    QCOMPARE(spy.at(0).at(1).toString(), QString("this IP address is not registered"));
    QCOMPARE(server_->log(), QStringList() << "POST /api/sync/begin");
    QVERIFY(!client.isRunning());
    Settings settings(QSqlDatabase::database(connection_));
    QVERIFY(!settings.boolValue(SettingKey::SyncBegun)); // so the next try begins again
    QVERIFY(!settings.boolValue(SettingKey::InitialDone));
}

void Smart210Test::aBusyServerEndsTheDownloadAndItCanBeRepeated()
{
    configureServer();
    serveEverything();
    server_->reply("/api/pull/config", 503, q("{'status':'busy'}"));
    SyncClient client(QSqlDatabase::database(connection_));
    QSignalSpy spy(&client, SIGNAL(downloadFinished(bool,QString)));

    client.download();
    QVERIFY(spy.wait(5000));
    QVERIFY(!spy.at(0).at(0).toBool());
    QVERIFY(spy.at(0).at(1).toString().contains("busy"));

    serveEverything(); // the backup is over
    QVERIFY(downloadOk(client));

    int begins = 0;
    foreach (const QString &line, server_->log()) {
        begins += (line == "POST /api/sync/begin");
    }
    QCOMPARE(begins, 1); // begun once, not again on the retry
}

void Smart210Test::aFailureHalfWayKeepsWhatWasStoredAndLeavesTheGateClosed()
{
    configureServer();
    serveEverything();
    server_->reply("/api/pull/tickets?limit=200", 500, q("<html>oops</html>"));
    SyncClient client(QSqlDatabase::database(connection_));
    QSignalSpy spy(&client, SIGNAL(downloadFinished(bool,QString)));

    client.download();
    QVERIFY(spy.wait(5000));

    QVERIFY(!spy.at(0).at(0).toBool());
    QCOMPARE(countRows("users"), 1); // the tables before the failure are kept
    QCOMPARE(countRows("customers"), 1);
    QCOMPARE(countRows("tickets"), 0);
    QVERIFY(!Settings(QSqlDatabase::database(connection_)).boolValue(SettingKey::InitialDone));
    QVERIFY(!server_->log().contains("POST /api/sync/initial-done"));
}

void Smart210Test::downloadNeedsAServerAndOnlyRunsOnce()
{
    SyncClient none(openDatabase());
    QSignalSpy spyNone(&none, SIGNAL(downloadFinished(bool,QString)));
    none.download();
    QCOMPARE(spyNone.size(), 1); // answered at once
    QVERIFY(!spyNone.at(0).at(0).toBool());
    QVERIFY(spyNone.at(0).at(1).toString().contains("No server"));

    configureServer();
    serveEverything();
    SyncClient client(QSqlDatabase::database(connection_));
    QSignalSpy spy(&client, SIGNAL(downloadFinished(bool,QString)));
    client.download();
    QVERIFY(client.isRunning());
    client.download(); // ignored while one is running
    QVERIFY(spy.wait(10000));
    QCOMPARE(spy.size(), 1);
}


// ---- helpers for the download tests ----

QJsonObject Smart210Test::configObject() const
{
    return QJsonDocument::fromJson(q(
        "{'status':'ok','server_time':'2026-10-07 09:00:00','ticket_serial_base':500000000,"
        "'fuel_types':[{'fuel_type':'D','name':'Diesel','price':'7.50','density':'0.8300'},"
        "{'fuel_type':'A','name':'Gasoline','price':'8.00','density':null}],"
        "'tanks':[{'id':1,'name':'T1','fuel_type':'D','capacity_kg':'50000.00','stock_kg':'1234.50'}],"
        "'nozzles':[{'id':2,'number':1,'tank_id':1,'fuel_type':'D','board_host':'192.168.1.20','board_port':502,'board_address':1}],"
        "'device':{'id':3,'name':'POS1','purge_days':1500,'pin_salt':'ps','pin_hash':'ph','initial_sync_done':false}}")).object();
}

int Smart210Test::countRows(const QString &table) const
{
    QSqlQuery query(QSqlDatabase::database(connection_));
    query.exec("SELECT COUNT(*) FROM " + table);
    query.next();

    return query.value(0).toInt();
}

void Smart210Test::configureServer()
{
    Settings settings(openDatabase());
    settings.setValue(SettingKey::ServerHost, "127.0.0.1");
    settings.setInt(SettingKey::ServerPort, server_->port());
    settings.setInt(SettingKey::DeviceId, 3);
}

// A web app with one row in every table.
void Smart210Test::serveEverything()
{
    const QByteArray ok = q("{'status':'ok'}");
    server_->reply("/api/sync/begin", 200, ok);
    server_->reply("/api/sync/initial-done", 200, ok);
    server_->reply("/api/pull/config", 200, QJsonDocument(configObject()).toJson(QJsonDocument::Compact));

    const char *pages[][2] = {
        {"users", "{'id':5,'username':'cash','name':'Cash One','role':'cashier','pos':true,'active':true,'pos_salt':'aa11','pos_hash':'hh','deleted':false}"},
        {"customers", "{'id':1,'name':'Self','phone':null,'is_self':true,'deleted':false}"},
        {"tickets", "{'barcode':'0000003000000011','fuel_type':'D','spec':0,'amount_kg':'5.00','status':0,'sale_id':null,'sale_date':null}"},
        {"ticket_loans", "{'id':'11111111-1111-1111-1111-111111111111','customer_id':1,'fuel_type':'D','amount_kg':'20.00','settled_amount_kg':'5.00',"
                         "'loaned_at':'2026-10-06 10:00:00','sale_id':null,'deleted':false}"},
        {"cards", "{'id':'22222222-2222-2222-2222-222222222222','uid':'AA01','holder_name':'Li Wei','holder_phone':'138 0000 1111','fuel_type':'D','pin_salt':'ab12','pin_hash':'hh',"
                  "'failed_attempts':0,'status':'active','balance_kg':'40.00','deleted':false}"},
        {"card_transactions", "{'id':'44444444-4444-4444-4444-444444444444','card_id':'22222222-2222-2222-2222-222222222222','type':'topup','kg':'40.00','balance_kg':'40.00',"
                              "'price':'7.50','total_amount':'300.00','source_id':'55555555-5555-5555-5555-555555555555','occurred_at':'2026-10-06 09:00:00','seq':7,'comment':null}"},
    };
    for (int i = 0; i < 6; ++i) {
        const QString table = pages[i][0];
        server_->reply("/api/pull/" + table + "?limit=200", 200,
                       q("{'status':'ok','more':false,'server_time':'2026-10-07 09:00:00','rows':[") + q(pages[i][1]) + "]}");
        server_->reply("/api/pull/" + table + "/ack", 200, q("{'status':'ok','acked':1}"));
    }
}

bool Smart210Test::downloadOk(SyncClient &client)
{
    QSignalSpy spy(&client, SIGNAL(downloadFinished(bool,QString)));
    client.download();
    if (spy.isEmpty() && !spy.wait(10000)) {
        return false;
    }

    if (!spy.at(0).at(0).toBool()) {
        qWarning("download failed: %s", qPrintable(spy.at(0).at(1).toString()));
    }

    return spy.at(0).at(0).toBool();
}

// ---- uploading ----

void Smart210Test::run(const QString &sql)
{
    QSqlQuery query(QSqlDatabase::database(connection_));
    if (!query.exec(sql)) {
        qFatal("%s: %s", qPrintable(sql), qPrintable(query.lastError().text()));
    }
}

// One row of each table that has not been uploaded.
void Smart210Test::recordADay()
{
    run("INSERT OR IGNORE INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (1, 'T1', 'D', 5000000, 100000)");
    run("INSERT OR IGNORE INTO nozzles (id, number, tank_id, fuel_type, board_address, synced, deleted) VALUES (2, 1, 1, 'D', 1, 0, 0)");
    run("INSERT INTO sales (id, created_at, customer_id, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, "
        "unpaid_amount, receipt, usage, comment, sale_date, user_id) VALUES ('sale-1', '2026-10-07 09:00:00', 1, 'spot', 'D', 750, 1050, "
        "'cash', 7875, 7875, 0, NULL, 'sale', NULL, '2026-10-07', 5)");
    run("INSERT INTO supplements (id, fuel_type, nozzle_id, out_amount_lt, out_amount_kg, preset_kg, density, mode, supplied_at, supply_date) "
        "VALUES ('sup-1', 'D', 2, 1265, 1050, 1050, 8300, 'manual', '2026-10-07 09:05:00', '2026-10-07')");
    run("INSERT INTO remainders (id, supplement_id, ticket_barcode, fuel_type, amount_kg, created_at, remainder_date) "
        "VALUES ('rem-1', 'sup-1', 21500000001, 'D', 250, '2026-10-07 09:06:00', '2026-10-07')");
    run("INSERT INTO ticket_loans (id, customer_id, fuel_type, amount_kg, settled_amount_kg, loaned_at, sale_id, date, user_id, deleted, synced) "
        "VALUES ('loan-1', 1, 'D', 2000, 0, '2026-10-07 09:10:00', NULL, '2026-10-07', 5, 0, 0)");
    run("INSERT INTO ticket_loan_settlements (id, loan_id, ticket_barcode, amount_kg, settled_at, date, user_id) "
        "VALUES ('set-1', 'loan-1', 3000000011, 500, '2026-10-07 09:20:00', '2026-10-07', 5)");
    run("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, sale_id, sale_date, supply_date, supplement_id, synced) "
        "VALUES (3000000011, 'D', 0, 500, 2, 's-9', '2026-10-06 09:00:00', '2026-10-07 09:20:00', NULL, 0)");
}

void Smart210Test::serveUploads()
{
    server_->reply("/api/push/nozzles", 200, q("{'status':'ok','saved':['1'],'rejected':[]}"));
    server_->reply("/api/push/sales", 200, q("{'status':'ok','saved':['sale-1'],'rejected':[]}"));
    server_->reply("/api/push/supplements", 200, q("{'status':'ok','saved':['sup-1'],'rejected':[]}"));
    server_->reply("/api/push/remainders", 200, q("{'status':'ok','saved':['rem-1'],'rejected':[]}"));
    server_->reply("/api/push/ticket_loans", 200, q("{'status':'ok','saved':['loan-1'],'rejected':[]}"));
    server_->reply("/api/push/ticket_loan_settlements", 200, q("{'status':'ok','saved':['set-1'],'rejected':[]}"));
    server_->reply("/api/push/tickets", 200, q("{'status':'ok','saved':['0000003000000011'],'rejected':[]}"));
}

bool Smart210Test::uploadOk(Uploader &uploader, QString *message)
{
    QSignalSpy spy(&uploader, SIGNAL(uploadFinished(bool,QString)));
    uploader.upload();
    if (spy.isEmpty() && !spy.wait(10000)) {
        return false;
    }
    *message = spy.at(0).at(1).toString();

    return spy.at(0).at(0).toBool();
}

void Smart210Test::pendingCountsOnlyWhatMayBeSent()
{
    QSqlDatabase db = openDatabase();
    recordADay();
    run("INSERT INTO sales (id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id, synced) "
        "VALUES ('sale-done', '2026-10-06 09:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5, 1)"); // already uploaded
    run("INSERT INTO sales (id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id, deleted_at) "
        "VALUES ('sale-deleted', '2026-10-07 10:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5, '2026-10-07 10:01:00')"); // deleted before the upload
    run("INSERT INTO ticket_loans (id, customer_id, fuel_type, amount_kg, loaned_at, deleted, synced) VALUES ('loan-deleted', 1, 'D', 100, '2026-10-07 10:00:00', 1, 0)");
    run("INSERT INTO tickets (barcode, fuel_type, amount_kg, status, synced) VALUES (3000000012, 'D', 500, 3, 0)"); // restricted: the deleted spot sale's ticket
    run("INSERT INTO tickets (barcode, fuel_type, amount_kg, status, synced) VALUES (3000000013, 'D', 500, 0, 0)"); // back in stock
    run("INSERT INTO tickets (barcode, fuel_type, amount_kg, status, synced) VALUES (3000000014, 'D', 500, 1, 1)"); // sold, already uploaded

    const QMap<QString, int> pending = Uploader::pending(db);

    QCOMPARE(pending.value("sales"), 1);
    QCOMPARE(pending.value("supplements"), 1);
    QCOMPARE(pending.value("remainders"), 1);
    QCOMPARE(pending.value("ticket_loans"), 1);
    QCOMPARE(pending.value("ticket_loan_settlements"), 1);
    QCOMPARE(pending.value("tickets"), 1);
    QCOMPARE(pending.value("nozzles"), 1);
    QCOMPARE(Uploader::pendingTotal(db), 6);
}

void Smart210Test::uploadSendsEveryTableInOrderInTheApiFormat()
{
    configureServer();
    recordADay();
    serveUploads();
    Uploader uploader(QSqlDatabase::database(connection_));
    QString message;

    QVERIFY(uploadOk(uploader, &message));

    QCOMPARE(message, QString("Uploaded 6 records."));
    QStringList expected;
    expected << "POST /api/push/nozzles" << "POST /api/push/sales" << "POST /api/push/supplements" << "POST /api/push/remainders" << "POST /api/push/ticket_loans"
             << "POST /api/push/ticket_loan_settlements" << "POST /api/push/tickets";
    QCOMPARE(server_->log(), expected);
    QCOMPARE(Uploader::pendingTotal(QSqlDatabase::database(connection_)), 0);
    QCOMPARE(uploader.savedCount(), 6);

    const QJsonObject sale = QJsonDocument::fromJson(server_->requests().at(1).body).object().value("rows").toArray().at(0).toObject();
    QCOMPARE(sale.value("id").toString(), QString("sale-1"));
    QCOMPARE(sale.value("price").toString(), QString("7.50"));          // integers back to the API's decimals
    QCOMPARE(sale.value("amount_kg").toString(), QString("10.50"));
    QCOMPARE(sale.value("total_amount").toString(), QString("78.75"));
    QCOMPARE(sale.value("unpaid_amount").toString(), QString("0.00"));
    QVERIFY(sale.value("receipt").isNull());
    QCOMPARE(sale.value("customer_id").toInt(), 1);
    QCOMPARE(sale.value("user_id").toInt(), 5);
    QVERIFY(!sale.contains("deleted_at"));
    QVERIFY(!sale.contains("synced"));

    const QJsonObject supplement = QJsonDocument::fromJson(server_->requests().at(2).body).object().value("rows").toArray().at(0).toObject();
    QCOMPARE(supplement.value("out_amount_lt").toString(), QString("12.65"));
    QCOMPARE(supplement.value("out_amount_kg").toString(), QString("10.50"));
    QCOMPARE(supplement.value("mode").toString(), QString("manual"));
    QCOMPARE(supplement.value("density").toString(), QString("0.8300"));        // what the litres and the kg give (10.50 kg / 12.65 L)
    QVERIFY(!supplement.contains("manual_reading"));
    QVERIFY(!supplement.contains("liters_dispensed"));
    QVERIFY(!supplement.contains("amount_kg"));
    QVERIFY(!supplement.contains("date"));
    QCOMPARE(supplement.value("nozzle_number").toInt(), 1); // the web app names the nozzle by number
    QVERIFY(!supplement.contains("nozzle_id"));

    const QJsonObject remainder = QJsonDocument::fromJson(server_->requests().at(3).body).object().value("rows").toArray().at(0).toObject();
    QCOMPARE(remainder.value("ticket_barcode").toString(), QString("0000021500000001")); // 16 digits again

    const QJsonObject ticket = QJsonDocument::fromJson(server_->requests().at(6).body).object().value("rows").toArray().at(0).toObject();
    QCOMPARE(ticket.value("barcode").toString(), QString("0000003000000011"));
    QCOMPARE(ticket.value("status").toInt(), 2);
    QCOMPARE(ticket.value("supply_date").toString(), QString("2026-10-07 09:20:00"));
    QVERIFY(ticket.value("supplement_id").isNull());
}

void Smart210Test::nothingToUploadSendsNothing()
{
    configureServer();
    Uploader uploader(QSqlDatabase::database(connection_));
    QString message;

    QVERIFY(uploadOk(uploader, &message));

    QCOMPARE(message, QString("Uploaded 0 records."));
    QCOMPARE(server_->requests().size(), 0);
}

void Smart210Test::recordsDeletedBeforeTheUploadAreNeverSent()
{
    configureServer();
    run("INSERT INTO sales (id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id, deleted_at) "
        "VALUES ('sale-deleted', '2026-10-07 10:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5, '2026-10-07 10:01:00')");
    run("INSERT INTO ticket_loans (id, customer_id, fuel_type, amount_kg, loaned_at, deleted, synced) VALUES ('loan-deleted', 1, 'D', 100, '2026-10-07 10:00:00', 1, 0)");
    run("INSERT INTO tickets (barcode, fuel_type, amount_kg, status, synced) VALUES (21500000009, 'D', 100, 3, 0)");
    Uploader uploader(QSqlDatabase::database(connection_));
    QString message;

    QVERIFY(uploadOk(uploader, &message));

    QCOMPARE(server_->requests().size(), 0); // the web app never learns that these existed
}

void Smart210Test::manyRowsGoInBatchesOf200InOrder()
{
    configureServer();
    run("BEGIN");
    for (int i = 0; i < 450; ++i) {
        run(QString("INSERT INTO sales (id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id) "
                    "VALUES ('s%1', '2026-10-07 09:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5)").arg(i, 3, 10, QChar('0')));
    }
    run("COMMIT");
    for (int batch = 0; batch < 3; ++batch) {
        QStringList keys;
        for (int i = batch * 200; i < qMin(450, batch * 200 + 200); ++i) {
            keys << QString("\"s%1\"").arg(i, 3, 10, QChar('0'));
        }
        const QByteArray reply = "{\"status\":\"ok\",\"saved\":[" + keys.join(",").toUtf8() + "],\"rejected\":[]}";
        if (batch == 0) {
            server_->reply("/api/push/sales", 200, reply);
        } else {
            server_->replyNext("/api/push/sales", 200, reply);
        }
    }
    Uploader uploader(QSqlDatabase::database(connection_));
    QString message;

    QVERIFY(uploadOk(uploader, &message));

    QCOMPARE(message, QString("Uploaded 450 records."));
    QCOMPARE(server_->requests().size(), 3);
    const QJsonArray first = QJsonDocument::fromJson(server_->requests().at(0).body).object().value("rows").toArray();
    const QJsonArray last = QJsonDocument::fromJson(server_->requests().at(2).body).object().value("rows").toArray();
    QCOMPARE(first.size(), 200);
    QCOMPARE(last.size(), 50);
    QCOMPARE(first.at(0).toObject().value("id").toString(), QString("s000"));
    QCOMPARE(last.at(49).toObject().value("id").toString(), QString("s449"));
    QCOMPARE(Uploader::pendingTotal(QSqlDatabase::database(connection_)), 0);
}

void Smart210Test::aRefusedRowStaysWithItsReasonAndIsNotSentAgainInTheRun()
{
    configureServer();
    recordADay();
    serveUploads();
    server_->reply("/api/push/sales", 200, q("{'status':'ok','saved':[],'rejected':[{'key':'sale-1','error':'customer does not exist'}]}"));
    // the supplement is still accepted: one refused row does not stop the others
    Uploader uploader(QSqlDatabase::database(connection_));
    QString message;

    QVERIFY(uploadOk(uploader, &message));

    QVERIFY(message.contains("refused 1"));
    QCOMPARE(uploader.rejectedCount(), 1);
    QCOMPARE(uploader.savedCount(), 5);
    int salePosts = 0;
    foreach (const QString &line, server_->log()) {
        salePosts += (line == "POST /api/push/sales");
    }
    QCOMPARE(salePosts, 1); // not retried over and over in the same run
    QCOMPARE(Uploader::pending(QSqlDatabase::database(connection_)).value("sales"), 1);

    QSqlQuery query(QSqlDatabase::database(connection_));
    QVERIFY(query.exec("SELECT table_name, row_key, message FROM upload_errors"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toString(), QString("sales"));
    QCOMPARE(query.value(1).toString(), QString("sale-1"));
    QCOMPARE(query.value(2).toString(), QString("customer does not exist"));

    // Fixed on the web (the customer now exists): the next upload sends it again, and its error disappears.
    server_->reply("/api/push/sales", 200, q("{'status':'ok','saved':['sale-1'],'rejected':[]}"));
    QVERIFY(uploadOk(uploader, &message));
    QCOMPARE(Uploader::pendingTotal(QSqlDatabase::database(connection_)), 0);
    QVERIFY(query.exec("SELECT COUNT(*) FROM upload_errors"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toInt(), 0);
}

void Smart210Test::aBusyServerStopsTheUploadAndKeepsWhatWasSent()
{
    configureServer();
    recordADay();
    serveUploads();
    server_->reply("/api/push/remainders", 503, q("{'status':'busy'}"));
    Uploader uploader(QSqlDatabase::database(connection_));
    QString message;

    QVERIFY(!uploadOk(uploader, &message));

    QVERIFY(message.contains("busy"));
    QVERIFY(!uploader.isRunning());
    const QMap<QString, int> pending = Uploader::pending(QSqlDatabase::database(connection_));
    QCOMPARE(pending.value("sales"), 0);       // sent before the failure
    QCOMPARE(pending.value("supplements"), 0);
    QCOMPARE(pending.value("remainders"), 1);  // the rest waits for the next upload
    QCOMPARE(pending.value("tickets"), 1);
    QVERIFY(!server_->log().contains("POST /api/push/tickets"));

    serveUploads(); // the backup is over
    QVERIFY(uploadOk(uploader, &message));
    QCOMPARE(Uploader::pendingTotal(QSqlDatabase::database(connection_)), 0);
}

void Smart210Test::rowsTheServerDoesNotAnswerAboutDoNotLoopForever()
{
    configureServer();
    recordADay();
    serveUploads();
    server_->reply("/api/push/sales", 200, q("{'status':'ok','saved':[],'rejected':[]}")); // says nothing about sale-1
    Uploader uploader(QSqlDatabase::database(connection_));
    QString message;

    QVERIFY(uploadOk(uploader, &message));

    int salePosts = 0;
    foreach (const QString &line, server_->log()) {
        salePosts += (line == "POST /api/push/sales");
    }
    QCOMPARE(salePosts, 1);
    QCOMPARE(Uploader::pending(QSqlDatabase::database(connection_)).value("sales"), 1);
}

void Smart210Test::uploadNeedsAServerAndOnlyRunsOnce()
{
    Uploader none(openDatabase());
    QSignalSpy spyNone(&none, SIGNAL(uploadFinished(bool,QString)));
    none.upload();
    QCOMPARE(spyNone.size(), 1);
    QVERIFY(!spyNone.at(0).at(0).toBool());

    configureServer();
    recordADay();
    serveUploads();
    Uploader uploader(QSqlDatabase::database(connection_));
    QSignalSpy spy(&uploader, SIGNAL(uploadFinished(bool,QString)));
    uploader.upload();
    QVERIFY(uploader.isRunning());
    uploader.upload();
    QVERIFY(spy.wait(10000));
    QCOMPARE(spy.size(), 1);
}

// ---- purging old records ----

void Smart210Test::purgeRemovesOnlyOldRecordsThatAreSafeToLose()
{
    QSqlDatabase db = openDatabase();
    const QDateTime now(QDate(2026, 10, 7), QTime(12, 0, 0)); // with 3 days the limit is 2026-10-04 12:00:00
    const QString sale = "INSERT INTO sales (id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id, synced, deleted_at) VALUES ";

    run(sale + "('old-synced', '2026-10-03 09:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5, 1, NULL)");     // goes
    run(sale + "('old-unsynced', '2026-10-03 09:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5, 0, NULL)");   // not uploaded: stays
    run(sale + "('new-synced', '2026-10-06 09:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5, 1, NULL)");     // recent: stays
    run(sale + "('old-deleted', '2026-10-02 09:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5, 0, '2026-10-03 10:00:00')"); // goes, with its ticket and loan
    run(sale + "('new-deleted', '2026-10-06 09:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5, 0, '2026-10-06 10:00:00')"); // still listed as deleted
    run("INSERT INTO tickets (barcode, fuel_type, amount_kg, status, sale_id, synced) VALUES (21500000001, 'D', 100, 3, 'old-deleted', 0)");
    run("INSERT INTO ticket_loans (id, customer_id, fuel_type, amount_kg, loaned_at, sale_id, deleted, synced) VALUES ('loan-of-deleted', 1, 'D', 100, '2026-10-02 09:00:00', 'old-deleted', 1, 0)");
    run("INSERT INTO ticket_loans (id, customer_id, fuel_type, amount_kg, loaned_at, deleted, synced) VALUES ('old-loan', 1, 'D', 100, '2026-09-01 09:00:00', 0, 1)"); // loans stay
    run("INSERT INTO supplements (id, fuel_type, nozzle_id, out_amount_lt, out_amount_kg, preset_kg, supplied_at, synced) VALUES ('sup-old', 'D', 2, 100, 100, 100, '2026-10-01 09:00:00', 1)");
    run("INSERT INTO supplements (id, fuel_type, nozzle_id, out_amount_lt, out_amount_kg, preset_kg, supplied_at, synced) VALUES ('sup-old-unsynced', 'D', 2, 100, 100, 100, '2026-10-01 09:00:00', 0)");
    run("INSERT INTO remainders (id, supplement_id, ticket_barcode, fuel_type, amount_kg, created_at, synced) VALUES ('rem-old', 'sup-old', 21500000002, 'D', 10, '2026-10-01 09:00:00', 1)");
    run("INSERT INTO ticket_loan_settlements (id, loan_id, ticket_barcode, amount_kg, settled_at, user_id, synced) VALUES ('set-old', 'old-loan', 3000000011, 10, '2026-10-01 09:00:00', 5, 1)");
    // tickets: only finished ones whose sale or supply is old
    run("INSERT INTO tickets (barcode, fuel_type, amount_kg, status, sale_date, supply_date, synced) VALUES (3000000021, 'D', 500, 2, '2026-10-01 09:00:00', '2026-10-01 10:00:00', 1)"); // goes
    run("INSERT INTO tickets (barcode, fuel_type, amount_kg, status, sale_date, supply_date, synced) VALUES (3000000022, 'D', 500, 2, '2026-10-06 09:00:00', '2026-10-06 10:00:00', 1)"); // recent
    run("INSERT INTO tickets (barcode, fuel_type, amount_kg, status, synced) VALUES (3000000023, 'D', 500, 3, 1)");                                                               // no dates: cannot tell its age
    run("INSERT INTO tickets (barcode, fuel_type, amount_kg, status, sale_date, synced) VALUES (3000000024, 'D', 500, 1, '2026-10-01 09:00:00', 1)");                              // sold, not supplied: still needed
    run("INSERT INTO tickets (barcode, fuel_type, amount_kg, status, synced) VALUES (3000000025, 'D', 500, 0, 1)");                                                               // in stock
    run("INSERT INTO tickets (barcode, fuel_type, amount_kg, status, sale_date, supply_date, synced) VALUES (3000000026, 'D', 500, 2, '2026-10-01 09:00:00', '2026-10-01 10:00:00', 0)"); // not uploaded

    const int removed = Purger::purge(db, 3, now);

    QCOMPARE(removed, 8); // old-synced, old-deleted + its ticket and loan, sup-old, rem-old, set-old, ticket 21
    QSqlQuery query(db);
    QVERIFY(query.exec("SELECT id FROM sales ORDER BY id"));
    QStringList sales;
    while (query.next()) {
        sales << query.value(0).toString();
    }
    QCOMPARE(sales, QStringList() << "new-deleted" << "new-synced" << "old-unsynced");
    QCOMPARE(countRows("supplements"), 1);
    QCOMPARE(countRows("remainders"), 0);
    QCOMPARE(countRows("ticket_loan_settlements"), 0);
    QCOMPARE(countRows("ticket_loans"), 1); // only the old loan, which is kept
    QVERIFY(query.exec("SELECT barcode FROM tickets ORDER BY barcode"));
    QList<qlonglong> tickets;
    while (query.next()) {
        tickets << query.value(0).toLongLong();
    }
    QCOMPARE(tickets, QList<qlonglong>() << 3000000022LL << 3000000023LL << 3000000024LL << 3000000025LL << 3000000026LL);
}

void Smart210Test::purgeKeepsTheRemainderTicketsThatAreNotUsedYet()
{
    QSqlDatabase db = openDatabase();
    const QDateTime now(QDate(2026, 10, 7), QTime(12, 0, 0)); // with 3 days the limit is 2026-10-04 12:00:00
    run("INSERT INTO supplements (id, fuel_type, nozzle_id, out_amount_lt, out_amount_kg, preset_kg, supplied_at, synced) VALUES ('sup-a', 'D', 2, 100, 100, 110, '2026-10-01 09:00:00', 1)");
    const QString remainder = "INSERT INTO remainders (id, supplement_id, ticket_barcode, fuel_type, amount_kg, created_at, printed_at, synced) VALUES ";
    run(remainder + "('rem-unused', 'sup-a', 21500000011, 'D', 1000, '2026-10-01 09:00:00', NULL, 1)");                      // not printed, not used: stays
    run(remainder + "('rem-printed', 'sup-a', 21500000012, 'D', 1000, '2026-10-01 09:00:00', '2026-10-01 09:01:00', 1)");  // printed, not used: stays
    run(remainder + "('rem-used', 'sup-a', 21500000013, 'D', 1000, '2026-10-01 09:00:00', '2026-10-01 09:01:00', 1)");     // used: goes
    run("INSERT INTO tickets (barcode, fuel_type, amount_kg, status, synced) VALUES (21500000011, 'D', 1000, 1, 1)");
    run("INSERT INTO tickets (barcode, fuel_type, amount_kg, status, synced) VALUES (21500000012, 'D', 1000, 1, 1)");
    run("INSERT INTO tickets (barcode, fuel_type, amount_kg, status, supply_date, synced) VALUES (21500000013, 'D', 1000, 2, '2026-10-02 09:00:00', 1)");

    Purger::purge(db, 3, now);

    QCOMPARE(one("SELECT COUNT(*) FROM remainders WHERE id IN ('rem-unused', 'rem-printed')").toInt(), 2);
    QCOMPARE(one("SELECT COUNT(*) FROM remainders WHERE id = 'rem-used'").toInt(), 0);

    // the confirmed supply goes too, its unused remainder tickets stay in the list
    Purger::removeConfirmed(db, QStringList(), QStringList() << "sup-a");
    QCOMPARE(one("SELECT COUNT(*) FROM remainders").toInt(), 2);
    QCOMPARE(SupplyRecord::remainders(db).size(), 2);

    // the list shows every unused one, however many newer used ones there are
    run("UPDATE tickets SET status = 2 WHERE barcode = 21500000012");
    run(remainder + "('rem-new', 'sup-a', 21500000014, 'D', 1000, '2026-10-06 09:00:00', NULL, 1)");
    run("INSERT INTO tickets (barcode, fuel_type, amount_kg, status, synced) VALUES (21500000014, 'D', 1000, 2, 1)");
    const QList<SupplyRecord::RemainderRow> rows = SupplyRecord::remainders(db, 1);
    QCOMPARE(rows.size(), 2);                                // the newest (used) and the old one that is still unused
    QCOMPARE(rows.at(1).ticketStatus, int(TicketStatus::Sale));
}

void Smart210Test::purgeKeepsTheHistoryOfCards()
{
    QSqlDatabase db = openDatabase();
    const QDateTime now(QDate(2026, 10, 7), QTime(12, 0, 0));
    const QString sale = "INSERT INTO sales (id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id, synced, card_id) VALUES ";
    run(sale + "('old-plain', '2026-10-01 09:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5, 1, NULL)");
    run(sale + "('old-topup', '2026-10-01 09:00:00', 'card', 'D', 750, 4000, 'cash', 30000, 30000, 'sale', 5, 1, 'card-1')");
    run("INSERT INTO supplements (id, fuel_type, nozzle_id, out_amount_lt, out_amount_kg, preset_kg, supplied_at, synced, card_id) VALUES ('sup-plain', 'D', 2, 100, 100, 100, '2026-10-01 09:00:00', 1, NULL)");
    run("INSERT INTO supplements (id, fuel_type, nozzle_id, out_amount_lt, out_amount_kg, preset_kg, supplied_at, synced, card_id) VALUES ('sup-card', 'D', 2, 100, 100, 100, '2026-10-01 09:00:00', 1, 'card-1')");

    QCOMPARE(Purger::purge(db, 3, now), 2);                                  // only the plain sale and the plain supply
    QCOMPARE(one("SELECT COUNT(*) FROM sales WHERE id = 'old-topup'").toInt(), 1);          // the history of a card stays
    QCOMPARE(one("SELECT COUNT(*) FROM supplements WHERE id = 'sup-card'").toInt(), 1);
}

void Smart210Test::theRecordsTheAccountantConfirmedAreRemovedAtTheNextDownload()
{
    configureServer();
    serveEverything();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    Settings(db).setBool(SettingKey::InitialDone, true);
    const QString sale = "INSERT INTO sales (id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id, synced, card_id) VALUES ";
    run(sale + "('done', '2026-10-07 09:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5, 1, NULL)");        // uploaded and confirmed: goes
    run(sale + "('open', '2026-10-07 09:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5, 1, NULL)");        // uploaded, not confirmed: stays
    run(sale + "('unsent', '2026-10-07 09:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5, 0, NULL)");      // not uploaded: never removed
    run(sale + "('topup', '2026-10-07 09:00:00', 'card', 'D', 750, 4000, 'cash', 30000, 30000, 'sale', 5, 1, 'card-1')"); // a card's history stays
    run("INSERT INTO sale_items (id, sale_id, spec, quantity, start_serial, end_serial, amount_kg) VALUES ('item-1', 'done', 0, 2, 1, 2, 1000)");
    const QString sup = "INSERT INTO supplements (id, fuel_type, nozzle_id, out_amount_lt, out_amount_kg, preset_kg, supplied_at, synced, card_id) VALUES ";
    run(sup + "('sup-done', 'D', 2, 100, 100, 100, '2026-10-07 10:00:00', 1, NULL)");
    run(sup + "('sup-done2', 'D', 2, 100, 100, 100, '2026-10-07 10:05:00', 1, NULL)");
    run(sup + "('sup-open', 'D', 2, 100, 100, 100, '2026-10-07 10:10:00', 1, NULL)");
    run(sup + "('sup-card', 'D', 2, 100, 100, 100, '2026-10-07 10:15:00', 1, 'card-1')");
    run("INSERT INTO remainders (id, supplement_id, ticket_barcode, fuel_type, amount_kg, created_at, printed_at, synced) VALUES ('rem-printed', 'sup-done', 21500000001, 'D', 10, '2026-10-07 10:00:00', '2026-10-07 10:01:00', 1)");
    run("INSERT INTO remainders (id, supplement_id, ticket_barcode, fuel_type, amount_kg, created_at, printed_at, synced) VALUES ('rem-unprinted', 'sup-done2', 21500000002, 'D', 10, '2026-10-07 10:05:00', NULL, 1)");
    run("INSERT INTO tickets (barcode, fuel_type, amount_kg, status, sale_id, supplement_id, synced) VALUES (3000000021, 'D', 500, 2, 'x', 'sup-done', 1)");     // supplied by a confirmed supply: goes
    run("INSERT INTO tickets (barcode, fuel_type, amount_kg, status, sale_id, supplement_id, synced) VALUES (3000000022, 'D', 500, 1, 'done', NULL, 1)");        // sold, not supplied: the supplier still needs it
    run("INSERT INTO tickets (barcode, fuel_type, amount_kg, status, sale_id, supplement_id, synced) VALUES (3000000023, 'D', 500, 2, 'x', 'sup-open', 1)");     // its supply is not confirmed: stays

    // the server confirms two sales and two supplements and also names the card's records and an unsent sale, which never go
    server_->reply("/api/confirmed", 200, q("{'status':'ok','sales':['done','topup','unsent'],'supplements':['sup-done','sup-done2','sup-card']}"));
    SyncClient client(db);
    QVERIFY(downloadOk(client));

    QCOMPARE(server_->log().last(), QString("POST /api/confirmed"));
    const QJsonObject asked = QJsonDocument::fromJson(server_->requests().last().body).object();
    QStringList askedSales;
    foreach (const QJsonValue &id, asked.value("sales").toArray()) {
        askedSales << id.toString();
    }
    askedSales.sort();
    QCOMPARE(askedSales, QStringList() << "done" << "open" << "topup");                    // uploaded and shown: not the unsent one; a card's top-up is asked about too
    QCOMPARE(asked.value("supplements").toArray().size(), 4);                              // sup-done, sup-done2, sup-open and the card's

    QStringList sales;
    QSqlQuery query(db);
    QVERIFY(query.exec("SELECT id FROM sales ORDER BY id"));
    while (query.next()) {
        sales << query.value(0).toString();
    }
    QCOMPARE(sales, QStringList() << "open" << "topup" << "unsent");
    QCOMPARE(countRows("sale_items"), 0);                                                  // its items went with it
    QStringList supplies;
    QVERIFY(query.exec("SELECT id FROM supplements ORDER BY id"));
    while (query.next()) {
        supplies << query.value(0).toString();
    }
    QCOMPARE(supplies, QStringList() << "sup-card" << "sup-open");
    // a card's confirmed top-up and supply stay (the balance and the history of the card) and are hidden: the Review screen does not list them
    QCOMPARE(one("SELECT hidden FROM sales WHERE id = 'topup'").toInt(), 1);
    QCOMPARE(one("SELECT hidden FROM supplements WHERE id = 'sup-card'").toInt(), 1);
    QCOMPARE(one("SELECT hidden FROM sales WHERE id = 'open'").toInt(), 0);
    QCOMPARE(ReviewData::sales(db, "2026-10-07", true).size(), 2);                          // open and unsent, not the top-up
    QCOMPARE(ReviewData::supplements(db, "2026-10-07").size(), 1);                          // sup-open, not the card's
    QVERIFY(!Purger::keptSaleIds(db, 100).contains("topup"));                              // and it is not asked about again
    QCOMPARE(one("SELECT COUNT(*) FROM remainders WHERE id = 'rem-printed'").toInt(), 0);
    QCOMPARE(one("SELECT COUNT(*) FROM remainders WHERE id = 'rem-unprinted'").toInt(), 1);   // not printed yet: it may still be printed
    QCOMPARE(one("SELECT COUNT(*) FROM tickets WHERE barcode = 3000000021").toInt(), 0);
    QCOMPARE(one("SELECT COUNT(*) FROM tickets WHERE barcode = 3000000022").toInt(), 1);
    QCOMPARE(one("SELECT COUNT(*) FROM tickets WHERE barcode = 3000000023").toInt(), 1);
}

void Smart210Test::aServerThatDoesNotKnowTheConfirmedQuestionDoesNotFailTheDownload()
{
    configureServer();
    serveEverything();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    Settings(db).setBool(SettingKey::InitialDone, true);
    run("INSERT INTO sales (id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id, synced) "
        "VALUES ('done', '2026-10-07 09:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5, 1)");
    server_->reply("/api/confirmed", 404, q("{'status':'error','message':'No such call.'}"));

    SyncClient client(db);
    QVERIFY(downloadOk(client));                                                           // an older server: no failure
    QCOMPARE(countRows("sales"), 1);                                                       // and nothing removed
}

void Smart210Test::purgeNeverUsesLessThanOneDay()
{
    QSqlDatabase db = openDatabase();
    run("INSERT INTO sales (id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id, synced) "
        "VALUES ('today', '2026-10-07 09:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5, 1)");

    QCOMPARE(Purger::purge(db, 0, QDateTime(QDate(2026, 10, 7), QTime(12, 0, 0))), 0);
    QCOMPARE(Purger::purge(db, -5, QDateTime(QDate(2026, 10, 7), QTime(12, 0, 0))), 0);
    QCOMPARE(countRows("sales"), 1);
}

// ---- the clock ----

void Smart210Test::theClockOffsetIsTheServersMinusOurs()
{
    const QDateTime local(QDate(2026, 10, 7), QTime(10, 0, 0));

    QCOMPARE(Clock::offsetSeconds("2026-10-07 10:00:30", local), qint64(30));
    QCOMPARE(Clock::offsetSeconds("2026-10-07 09:58:00", local), qint64(-120));
    QCOMPARE(Clock::offsetSeconds("2026-10-07 10:00:00", local), qint64(0));
    QCOMPARE(Clock::offsetSeconds("", local), qint64(0));
    QCOMPARE(Clock::offsetSeconds("not a time", local), qint64(0));

    QVERIFY(!Clock::isOff(0));
    QVERIFY(!Clock::isOff(60));
    QVERIFY(!Clock::isOff(-60));
    QVERIFY(Clock::isOff(61));
    QVERIFY(Clock::isOff(-61));
}

void Smart210Test::everyDownloadKeepsTheClockOffset()
{
    configureServer();
    serveEverything(); // the server says it is 2026-10-07 09:00:00, long ago for the machine running the test
    SyncClient client(QSqlDatabase::database(connection_));

    QVERIFY(downloadOk(client));

    Settings settings(QSqlDatabase::database(connection_));
    QVERIFY(settings.contains(SettingKey::ClockOffsetSeconds));
    QVERIFY(Clock::isOff(settings.value(SettingKey::ClockOffsetSeconds).toLongLong()));
}

void Smart210Test::theTicketSaleScreenOpensQuicklyOnABigStock()
{
    QSqlDatabase db = openDatabase();
    run("INSERT INTO fuel_types (fuel_type, name, price) VALUES ('D', 'Diesel', 750), ('A', 'Gasoline', 800), ('G', 'Gas without tickets', 900)");
    run("INSERT INTO customers (id, name, is_self, deleted) VALUES (1, 'Self', 1, 0)");
    QVERIFY(db.transaction());
    QSqlQuery insert(db);
    insert.prepare("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (?, ?, ?, ?, ?, 1)");
    // 60000 tickets of Diesel in four specs, the lowest barcodes of each spec in a run of their own; 3000 already sold; 20000 of Gasoline
    for (int i = 0; i < 60000; ++i) {
        insert.addBindValue(qlonglong(8676106000000000LL) + i);
        insert.addBindValue("D");
        insert.addBindValue(i / 15000);
        insert.addBindValue(500);
        insert.addBindValue(i < 3000 ? 2 : 0);
        insert.exec();
    }
    for (int i = 0; i < 20000; ++i) {
        insert.addBindValue(qlonglong(8676106100000000LL) + i);
        insert.addBindValue("A");
        insert.addBindValue(1);
        insert.addBindValue(1000);
        insert.addBindValue(0);
        insert.exec();
    }
    QVERIFY(db.commit());

    // the stock of a spec, or only its lowest tickets
    QCOMPARE(TicketSale::stock(db, "D", 1).size(), 15000);
    const QList<TicketSale::Stock> first = TicketSale::stock(db, "D", 1, 3);
    QCOMPARE(first.size(), 3);
    QCOMPARE(first.at(0).barcode, qlonglong(8676106000015000LL));
    QCOMPARE(first.at(2).barcode, qlonglong(8676106000015002LL));
    QCOMPARE(TicketSale::stock(db, "D", 0, 1).at(0).barcode, qlonglong(8676106000003000LL));   // the sold ones are not in stock
    QCOMPARE(TicketSale::stock(db, "D", 1, 100000).size(), 15000);                              // more than there is

    // the lookups of the screen use the indexes, not a read of every ticket
    QSqlQuery plan(db);
    QVERIFY(plan.exec("EXPLAIN QUERY PLAN SELECT barcode, amount_kg FROM tickets WHERE fuel_type = 'D' AND spec = 1 AND status = 0 ORDER BY barcode LIMIT 3"));
    QString text;
    while (plan.next()) {
        text += plan.value(3).toString() + "|";
    }
    QVERIFY2(text.contains("tickets_stock"), qPrintable(text));
    QVERIFY(!text.contains("TEMP B-TREE"));                                                    // already in barcode order: no sort

    // the screen: the fuel types with tickets (not the one without), the first serial and the end of the first run
    Session session(db);
    TicketSalePage page(db, &session);
    QElapsedTimer timer;
    timer.start();
    page.reload();
    const qint64 ms = timer.elapsed();
    QComboBox *fuel = page.findChild<QComboBox *>("tsFuelCombo");
    QComboBox *spec = page.findChild<QComboBox *>("tsSpecCombo");
    QCOMPARE(fuel->count(), 2);
    QCOMPARE(fuel->itemText(0), QString("Diesel"));
    QCOMPARE(fuel->itemText(1), QString("Gasoline"));
    QCOMPARE(spec->count(), 4);
    QCOMPARE(spec->itemText(0), QString("5.00 kg (12000)"));                                    // 15000 less the 3000 sold
    QCOMPARE(page.findChild<QLineEdit *>("tsStartField")->text(), QString::number(3000));
    QCOMPARE(page.findChild<QLineEdit *>("tsEndField")->text(), QString::number(3000));       // a count of 1
    page.findChild<QLineEdit *>("tsStartField")->clear();
    page.findChild<QLineEdit *>("tsCountField")->setText("25");
    page.findChild<QLineEdit *>("tsCountField")->textEdited("25");
    QCOMPARE(page.findChild<QLineEdit *>("tsEndField")->text(), QString::number(3024));
    qDebug() << "opening the Ticket sale screen with 80000 tickets in stock took" << ms << "ms";
    QVERIFY2(ms < 1500, qPrintable(QString::number(ms)));                                     // about 20 ms here; the old screen read the whole stock three times
}
void Smart210Test::theClockOffsetInUtcDoesNotDependOnTheTimeZone()
{
    const QDateTime nowUtc(QDate(2026, 10, 7), QTime(10, 0, 0), Qt::UTC);

    QCOMPARE(Clock::offsetFromUtc("2026-10-07 10:00:30", nowUtc), qint64(30));
    QCOMPARE(Clock::offsetFromUtc("2026-10-07 09:58:00", nowUtc), qint64(-120));
    QCOMPARE(Clock::offsetFromUtc("2026-10-07 10:00:00", nowUtc), qint64(0));
    QCOMPARE(Clock::offsetFromUtc("", nowUtc), qint64(0));
    QCOMPARE(Clock::offsetFromUtc("not a time", nowUtc), qint64(0));
    QVERIFY(!Clock::utcOf("not a time").isValid());
    QCOMPARE(Clock::utcOf("2026-10-07 10:00:30").timeSpec(), Qt::UTC);

    // the same whatever time zone the program is set to: the digits are read as UTC on both sides
    const QByteArray zone = qgetenv("TZ");
    foreach (int offset, QList<int>() << 0 << 32400 << -18000 << 19800) {
        Clock::applyTimeZone(offset);
        QCOMPARE(Clock::offsetFromUtc("2026-10-07 10:00:30", nowUtc), qint64(30));
    }
    qputenv("TZ", zone);

    // the clock is set when it is off by more than 5 seconds (and warned about when it is more than a minute)
    QVERIFY(!Clock::needsSetting(0));
    QVERIFY(!Clock::needsSetting(5));
    QVERIFY(!Clock::needsSetting(-5));
    QVERIFY(Clock::needsSetting(6));
    QVERIFY(Clock::needsSetting(-6));
}

void Smart210Test::theTimeZoneIsAFixedPosixOffset()
{
    QCOMPARE(Clock::posixTimeZone(0), QString("UTC0"));
    QCOMPARE(Clock::posixTimeZone(32400), QString("UTC-9"));        // POSIX counts the other way round
    QCOMPARE(Clock::posixTimeZone(-18000), QString("UTC5"));
    QCOMPARE(Clock::posixTimeZone(19800), QString("UTC-5:30"));
    QCOMPARE(Clock::posixTimeZone(-12600), QString("UTC3:30"));
    QCOMPARE(Clock::posixTimeZone(45900), QString("UTC-12:45"));
    QCOMPARE(Clock::posixTimeZone(-3600), QString("UTC1"));

    const QByteArray zone = qgetenv("TZ");
    Clock::applyTimeZone(32400);
    QCOMPARE(qgetenv("TZ"), QByteArray("UTC-9"));
    Clock::applyTimeZone(-14400);
    QCOMPARE(qgetenv("TZ"), QByteArray("UTC4"));
    qputenv("TZ", zone);
}

void Smart210Test::aDownloadKeepsAndAppliesTheStationsTimeZoneAndMeasuresTheClockInUtc()
{
    configureServer();
    serveEverything();
    // the server is in UTC+9 and its clock is two minutes ahead of this machine's
    const QDateTime ahead = QDateTime::currentDateTimeUtc().addSecs(120);
    QJsonObject config = configObject();
    config.insert("server_time_utc", ahead.toString("yyyy-MM-dd HH:mm:ss"));
    config.insert("server_time", ahead.addSecs(32400).toString("yyyy-MM-dd HH:mm:ss"));
    config.insert("timezone", "Asia/Seoul");
    config.insert("utc_offset_seconds", 32400);
    server_->reply("/api/pull/config", 200, QJsonDocument(config).toJson(QJsonDocument::Compact));

    const QByteArray zone = qgetenv("TZ");
    SyncClient client(QSqlDatabase::database(connection_));
    QVERIFY(downloadOk(client));

    Settings settings(QSqlDatabase::database(connection_));
    QCOMPARE(settings.intValue(SettingKey::UtcOffsetSeconds), 32400);
    QCOMPARE(settings.value(SettingKey::TimeZoneName), QString("Asia/Seoul"));
    QCOMPARE(qgetenv("TZ"), QByteArray("UTC-9"));                                   // applied at once
    const qint64 offset = settings.value(SettingKey::ClockOffsetSeconds).toLongLong();
    QVERIFY2(offset >= 118 && offset <= 122, qPrintable(QString::number(offset)));   // measured in UTC: not 120 + 9 hours
    QVERIFY(Clock::isOff(offset));

    // the daylight saving time of the zone changed: the next download brings the new offset
    config.insert("utc_offset_seconds", 28800);
    server_->reply("/api/pull/config", 200, QJsonDocument(config).toJson(QJsonDocument::Compact));
    SyncClient again(QSqlDatabase::database(connection_));
    QVERIFY(downloadOk(again));
    QCOMPARE(settings.intValue(SettingKey::UtcOffsetSeconds), 28800);
    QCOMPARE(qgetenv("TZ"), QByteArray("UTC-8"));
    qputenv("TZ", zone);
}

void Smart210Test::theStoredTimeZoneIsAppliedAtTheStartAndShownOnTheSettingsPage()
{
    QSqlDatabase db = openDatabase();
    Settings settings(db);
    const QByteArray zone = qgetenv("TZ");

    // before the first download there is nothing to apply and nothing to show
    qputenv("TZ", "UTC0");
    Clock::applyStoredTimeZone(settings);
    QCOMPARE(qgetenv("TZ"), QByteArray("UTC0"));
    QVERIFY(Clock::zoneText(settings).isEmpty());

    Clock::keepTimeZone(settings, 32400, "Asia/Seoul");
    QCOMPARE(Clock::zoneText(settings), QString("UTC+09:00 (Asia/Seoul)"));
    qputenv("TZ", "UTC0");                                                          // a new start of the program
    Clock::applyStoredTimeZone(settings);
    QCOMPARE(qgetenv("TZ"), QByteArray("UTC-9"));

    Clock::keepTimeZone(settings, -12600, QString());
    QCOMPARE(Clock::zoneText(settings), QString("UTC-03:30"));
    qputenv("TZ", zone);
}
// ---- the window: first-sync gate and automatic download ----

void Smart210Test::aTerminalWithoutAServerShowsTheSetupPage()
{
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", openDatabase());

    QCOMPARE(window.currentPage(), QString("setup"));
    QCOMPARE(server_->requests().size(), 0);
}

void Smart210Test::theGateHoldsTheAppUntilTheFirstDownloadFinishes()
{
    configureServer();
    serveEverything();
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));

    QCOMPARE(window.currentPage(), QString("gate")); // the first download has not finished
    QTRY_COMPARE_WITH_TIMEOUT(window.currentPage(), QString("login"), 10000); // then the cashier logs in

    QVERIFY(Settings(QSqlDatabase::database(connection_)).boolValue(SettingKey::InitialDone));
    QVERIFY(server_->log().contains("POST /api/sync/initial-done"));
}

void Smart210Test::aFailedFirstDownloadStaysAtTheGateUntilRetried()
{
    configureServer();
    serveEverything();
    server_->reply("/api/pull/config", 503, q("{'status':'busy'}"));
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));

    QTRY_VERIFY_WITH_TIMEOUT(server_->log().contains("GET /api/pull/config"), 5000);
    QTest::qWait(300);
    QCOMPARE(window.currentPage(), QString("gate")); // the app is not usable

    serveEverything(); // the backup is over
    QPushButton *retry = 0;
    foreach (QPushButton *button, window.findChildren<QPushButton *>()) {
        if (button->text() == "Retry") {
            retry = button;
        }
    }
    QVERIFY(retry != 0);
    retry->click();

    QTRY_COMPARE_WITH_TIMEOUT(window.currentPage(), QString("login"), 10000);
}

void Smart210Test::aConfiguredTerminalOpensStraightAndDownloadsAndPurgesByItself()
{
    configureServer();
    serveEverything();
    {
        SyncClient first(QSqlDatabase::database(connection_));
        QVERIFY(downloadOk(first)); // the first sync is done: from now on the app opens straight into Sales
    }
    run("INSERT INTO sales (id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id, synced) "
        "VALUES ('ancient', '2020-01-01 09:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5, 1)");
    addUser(7, "Cash One", "secret1");
    QVERIFY(Session(QSqlDatabase::database(connection_)).login(7, "secret1")); // a cashier logged in before the restart
    const int before = server_->requests().size();
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());

    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));

    QCOMPARE(window.currentPage(), QString("spot"));
    QTRY_VERIFY_WITH_TIMEOUT(server_->requests().size() > before, 5000); // it started a download by itself
    QCOMPARE(countRows("sales"), 0);                                       // and the old uploaded sale is gone
}

void Smart210Test::downloadsRepeatOnATimerButNothingIsEverUploadedByItself()
{
    configureServer();
    serveEverything();
    {
        SyncClient first(QSqlDatabase::database(connection_));
        QVERIFY(downloadOk(first));
    }
    run("DELETE FROM tickets"); // recordADay() brings its own ticket 11
    recordADay(); // records waiting for the cashier's Upload
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    window.setDownloadIntervalMs(200);

    QTRY_VERIFY_WITH_TIMEOUT(server_->log().count("GET /api/pull/config") >= 4, 10000); // first, startup and at least two timer rounds

    foreach (const QString &line, server_->log()) {
        QVERIFY2(!line.contains("/api/push/"), qPrintable(line));
    }
    QCOMPARE(Uploader::pendingTotal(QSqlDatabase::database(connection_)), 6); // all still waiting
}

// ---- passwords ----

void Smart210Test::aPasswordIsCheckedAgainstSaltedSha256()
{
    const QString hash = "91e021ea659c35b11e5fcc7d7a0d55850e656e58c5e2b53320884fedf6978aa0"; // what the web app stores for aa11 + secret1

    QVERIFY(PosHash::matches("aa11", "secret1", hash));
    QVERIFY(PosHash::matches("aa11", "secret1", hash.toUpper()));
    QVERIFY(!PosHash::matches("aa11", "secret2", hash));
    QVERIFY(!PosHash::matches("aa12", "secret1", hash)); // the salt is part of it
    QVERIFY(!PosHash::matches("aa11", "Secret1", hash));
    QVERIFY(PosHash::matches("cc33", QString::fromUtf8("密码123"), "2b31ce1d05c21c86efd698eb6cecdb4d62f864d143464052cd86debdf537dc8a"));
}

void Smart210Test::aLegacySha1HashIsStillAccepted()
{
    QVERIFY(PosHash::matches("bb22", "secret1", "c6a6eaf6f06836b131ea7cfb4ed40b7372bc7ad7"));
    QVERIFY(!PosHash::matches("bb22", "wrong", "c6a6eaf6f06836b131ea7cfb4ed40b7372bc7ad7"));
}

void Smart210Test::emptyOrOddHashesNeverMatch()
{
    QVERIFY(!PosHash::matches("aa11", "", "91e021ea659c35b11e5fcc7d7a0d55850e656e58c5e2b53320884fedf6978aa0"));
    QVERIFY(!PosHash::matches("aa11", "secret1", ""));
    QVERIFY(!PosHash::matches("aa11", "secret1", "abcd"));
    QVERIFY(!PosHash::matches("aa11", "secret1", QString(50, 'a')));
}

// ---- the session ----

void Smart210Test::addUser(int id, const QString &name, const QString &password, bool active, bool pos, bool deleted, bool legacy)
{
    const QString salt = "aa11";
    const QByteArray data = (salt + password).toUtf8();
    const QString hash = password.isEmpty() ? QString()
        : QString::fromLatin1(QCryptographicHash::hash(data, legacy ? QCryptographicHash::Sha1 : QCryptographicHash::Sha256).toHex());

    QSqlQuery query(QSqlDatabase::database(connection_));
    query.prepare("INSERT INTO users (id, username, name, role, pos, active, pos_salt, pos_hash, deleted) VALUES (?, ?, ?, 'cashier', ?, ?, ?, ?, ?)");
    query.addBindValue(id);
    query.addBindValue("user" + QString::number(id));
    query.addBindValue(name);
    query.addBindValue(pos ? 1 : 0);
    query.addBindValue(active ? 1 : 0);
    query.addBindValue(salt);
    query.addBindValue(hash.isEmpty() ? QVariant(QVariant::String) : QVariant(hash));
    query.addBindValue(deleted ? 1 : 0);
    if (!query.exec()) {
        qFatal("addUser: %s", qPrintable(query.lastError().text()));
    }
}

void Smart210Test::theLoginListHasOnlyActiveTerminalUsersByName()
{
    Session session(openDatabase());
    addUser(1, "Zoe", "pw");
    addUser(2, "adam", "pw");
    addUser(3, "Gone", "pw", false);           // disabled on the web
    addUser(4, "Removed", "pw", true, true, true); // deleted on the web
    addUser(5, "Office", "pw", true, false);   // a role that does not use the terminal

    const QList<Session::Cashier> list = session.cashiers();

    QCOMPARE(list.size(), 2);
    QCOMPARE(list.at(0).name, QString("adam")); // by name, ignoring case
    QCOMPARE(list.at(1).name, QString("Zoe"));
}

void Smart210Test::theLoginOffersOnlyTheCashiers()
{
    QSqlDatabase db = openDatabase();
    Session session(db);
    addUser(1, "Cashier", "pw");
    addUser(2, "Boss", "pw");
    addUser(3, "Manager", "pw");
    addUser(4, "Helper", "pw");
    run("UPDATE users SET role = 'admin' WHERE id = 2");
    run("UPDATE users SET role = 'manager' WHERE id = 3");
    run("UPDATE users SET role = 'assistant' WHERE id = 4");

    QStringList names;
    foreach (const Session::Cashier &cashier, session.cashiers()) {
        names << cashier.name;
    }
    QCOMPARE(names, QStringList() << "Cashier");                                  // not the administrator, the manager or the assistant

    // and the others cannot log in even when the id is known; a session of one of them ends
    QVERIFY(!session.login(2, "pw"));
    QVERIFY(!session.login(3, "pw"));
    QVERIFY(!session.login(4, "pw"));
    QVERIFY(session.login(1, "pw"));
    run("UPDATE users SET role = 'assistant' WHERE id = 1");
    QVERIFY(!session.validate());
    QVERIFY(!session.isLoggedIn());
}

void Smart210Test::theLoginScreenHasAComboBoxAndASettingsButtonThatOpensMachineConfigWithoutALogin()
{
    setUpLoggedInTerminalWithAPin();
    Session(QSqlDatabase::database(connection_)).logout();
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    QCOMPARE(window.currentPage(), QString("login"));

    QComboBox *names = window.findChild<QComboBox *>("cashierCombo");
    QVERIFY(names != 0);
    QCOMPARE(names->currentText(), QString("Cash One"));   // the first name is chosen: only the password is left to type
    QPushButton *settings = window.findChild<QPushButton *>("loginSettingsButton");
    QVERIFY(settings != 0);
    QCOMPARE(settings->text(), QString("Settings"));

    // Settings asks for the PIN; the sidebar is out of reach until somebody logs in
    settings->click();
    QCOMPARE(window.currentPage(), QString("pin"));
    QVERIFY(!window.findChild<SideBar *>()->isVisibleTo(&window));           // the PIN page is the whole window: no sidebar, no header
    QVERIFY(window.findChild<QLabel *>("cashierLabel")->text().isEmpty());

    // Cancel goes back to the login, not to a Settings page that needs a cashier
    window.findChild<QPushButton *>("pinCancelButton")->click();
    QCOMPARE(window.currentPage(), QString("login"));

    // the right PIN opens Machine Config, and Back returns to the login
    settings->click();
    window.findChild<QLineEdit *>("pinField")->setText("4821");
    window.findChild<QPushButton *>("pinOkButton")->click();
    QCOMPARE(window.currentPage(), QString("machine"));
    QVERIFY(!window.findChild<SideBar *>()->isVisibleTo(&window)); // the whole window, not part of the cashier's shell
    window.findChild<QPushButton *>("machineBackButton")->click();
    QCOMPARE(window.currentPage(), QString("login"));

    // a login switches the sidebar on again
    window.findChild<QLineEdit *>("password")->setText("secret1");
    window.findChild<QPushButton *>("loginButton")->click();
    QCOMPARE(window.currentPage(), QString("spot"));
    QVERIFY(window.findChild<SideBar *>()->isEnabled());
}

void Smart210Test::aRightPasswordStartsASessionThatSurvivesARestart()
{
    QSqlDatabase db = openDatabase();
    addUser(7, "Cash One", "secret1");
    {
        Session session(db);
        QVERIFY(!session.isLoggedIn());
        QVERIFY(session.login(7, "secret1"));
        QVERIFY(session.isLoggedIn());
    }

    Session afterRestart(db); // a new start of the program
    QVERIFY(afterRestart.isLoggedIn());
    QCOMPARE(afterRestart.userId(), 7);
    QCOMPARE(afterRestart.userName(), QString("Cash One"));
    QVERIFY(afterRestart.validate());
}

void Smart210Test::aWrongPasswordLogsNobodyIn()
{
    QSqlDatabase db = openDatabase();
    addUser(7, "Cash One", "secret1");
    addUser(8, "Cash Two", "other");
    Session session(db);
    QVERIFY(session.login(7, "secret1"));

    QVERIFY(!session.login(8, "secret1")); // someone else's password

    QVERIFY(!session.isLoggedIn()); // and the one who was in is out: no half-switched session
    QVERIFY(!session.login(8, ""));
    QVERIFY(!session.login(99, "secret1")); // no such user
    QVERIFY(!session.isLoggedIn());
}

void Smart210Test::aUserWhoMayNotUseTheTerminalCannotLogIn()
{
    QSqlDatabase db = openDatabase();
    addUser(1, "Disabled", "pw", false);
    addUser(2, "Deleted", "pw", true, true, true);
    addUser(3, "Office", "pw", true, false);
    addUser(4, "NoHash", QString()); // never got a password for the terminal
    Session session(db);

    for (int id = 1; id <= 4; ++id) {
        QVERIFY2(!session.login(id, "pw"), qPrintable(QString::number(id)));
        QVERIFY(!session.isLoggedIn());
    }
}

void Smart210Test::aCashierWithALegacyHashCanLogIn()
{
    QSqlDatabase db = openDatabase();
    addUser(7, "Old", "secret1", true, true, false, true);

    QVERIFY(Session(db).login(7, "secret1"));
}

void Smart210Test::logOutEndsTheSessionForGood()
{
    QSqlDatabase db = openDatabase();
    addUser(7, "Cash One", "secret1");
    Session session(db);
    QVERIFY(session.login(7, "secret1"));

    session.logout();

    QVERIFY(!session.isLoggedIn());
    QVERIFY(!Session(db).isLoggedIn()); // not after a restart either
    QVERIFY(!session.validate());
    QCOMPARE(session.userId(), 0);
}

void Smart210Test::aSessionEndsWhenTheWebAppDisablesOrDeletesTheCashier()
{
    QSqlDatabase db = openDatabase();
    addUser(7, "Cash One", "secret1");
    addUser(8, "Cash Two", "secret1");
    Session session(db);

    QVERIFY(session.login(7, "secret1"));
    run("UPDATE users SET active = 0 WHERE id = 7");
    QVERIFY(!session.validate());
    QVERIFY(!session.isLoggedIn());

    QVERIFY(session.login(8, "secret1"));
    run("UPDATE users SET deleted = 1 WHERE id = 8");
    QVERIFY(!session.validate());

    QVERIFY(session.login(7, "secret1") == false); // still disabled
    run("UPDATE users SET active = 1 WHERE id = 7");
    QVERIFY(session.login(7, "secret1"));
    run("UPDATE users SET pos = 0 WHERE id = 7"); // role changed to one that does not use the terminal
    QVERIFY(!session.validate());
}

// ---- the login screens in the window ----

QWidget *Smart210Test::pageOf(MainWindow &window, const char *objectName)
{
    return window.findChild<QWidget *>(objectName);
}

void Smart210Test::setUpDoneTerminal()
{
    configureServer();
    Settings(QSqlDatabase::database(connection_)).setInt(SettingKey::PurgeDays, 1500); // the fixtures have fixed dates: nothing may be purged as time goes by
    serveEverything();
    Settings settings(QSqlDatabase::database(connection_));
    settings.setBool(SettingKey::InitialDone, true);
    settings.setBool(SettingKey::SyncBegun, true);
}

void Smart210Test::aTerminalWithoutASessionStartsAtTheLogin()
{
    setUpDoneTerminal();
    addUser(7, "Cash One", "secret1");
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());

    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));

    QCOMPARE(window.currentPage(), QString("login"));
    QComboBox *names = window.findChild<QComboBox *>("cashierCombo");
    QVERIFY(names != 0);
    QVERIFY(names->count() >= 1);
}

void Smart210Test::aLoggedInCashierOpensStraightIntoTheApp()
{
    setUpDoneTerminal();
    addUser(7, "Cash One", "secret1");
    QVERIFY(Session(QSqlDatabase::database(connection_)).login(7, "secret1"));
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());

    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));

    QCOMPARE(window.currentPage(), QString("spot")); // no login asked
    QCOMPARE(window.findChild<QLabel *>("cashierLabel")->text(), QString("Cashier: Cash One"));
}

void Smart210Test::loggingInOnTheScreenOpensTheApp()
{
    setUpDoneTerminal();
    addUser(7, "Cash One", "secret1");
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    QComboBox *names = window.findChild<QComboBox *>("cashierCombo");
    QLineEdit *password = window.findChild<QLineEdit *>("password");
    QPushButton *login = window.findChild<QPushButton *>("loginButton");

    // the wrong password first
    names->setCurrentIndex(names->findText("Cash One"));
    password->setText("nope");
    login->click();
    QCOMPARE(window.currentPage(), QString("login"));
    QCOMPARE(password->text(), QString()); // cleared
    QVERIFY(!Session(QSqlDatabase::database(connection_)).isLoggedIn());

    password->setText("secret1");
    login->click();

    QCOMPARE(window.currentPage(), QString("spot"));
    QCOMPARE(window.findChild<QLabel *>("cashierLabel")->text(), QString("Cashier: Cash One"));
    QVERIFY(Session(QSqlDatabase::database(connection_)).isLoggedIn());
    QCOMPARE(password->text(), QString());
}

void Smart210Test::logOutIsOnTheSettingsPageAndBringsBackTheLogin()
{
    setUpDoneTerminal();
    addUser(7, "Cash One", "secret1");
    QVERIFY(Session(QSqlDatabase::database(connection_)).login(7, "secret1"));
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    QCOMPARE(window.currentPage(), QString("spot"));

    // the main screen has no Log out button
    foreach (QPushButton *button, window.findChildren<QPushButton *>()) {
        if (button->isVisibleTo(&window)) {
            QVERIFY(button->text() != "Log out");
        }
    }

    window.showPage("settings");
    QCOMPARE(window.currentPage(), QString("settings"));
    window.showPage("spot");
    QCOMPARE(window.currentPage(), QString("spot"));

    window.showPage("settings");
    window.findChild<QPushButton *>("logoutButton")->click();

    QCOMPARE(window.currentPage(), QString("login"));
    QVERIFY(!Session(QSqlDatabase::database(connection_)).isLoggedIn());

    // the next cashier logs in
    addUser(8, "Cash Two", "other");
    AppConfig again;
    MainWindow second(again, QString(), "db", QSqlDatabase::database(connection_));
    QCOMPARE(second.currentPage(), QString("login")); // a restart after Log out still asks
}

void Smart210Test::aCashierDisabledOnTheWebIsAskedToLogInAgainAtTheNextStart()
{
    setUpDoneTerminal();
    addUser(7, "Cash One", "secret1");
    QVERIFY(Session(QSqlDatabase::database(connection_)).login(7, "secret1"));
    run("UPDATE users SET active = 0 WHERE id = 7");
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());

    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));

    QCOMPARE(window.currentPage(), QString("login"));
    QVERIFY(!Session(QSqlDatabase::database(connection_)).isLoggedIn());
}

void Smart210Test::afterTheFirstDownloadTheCashiersChooseTheirNames()
{
    configureServer();
    serveEverything(); // brings one user, Cash One
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));

    QCOMPARE(window.currentPage(), QString("gate"));
    QTRY_COMPARE_WITH_TIMEOUT(window.currentPage(), QString("login"), 10000); // not straight into the app: nobody is logged in

    QComboBox *names = window.findChild<QComboBox *>("cashierCombo");
    QCOMPARE(names->count(), 1);
    QCOMPARE(names->itemText(0), QString("Cash One"));
}

void Smart210Test::aCashierAddedOnTheWebAppearsOnTheLoginScreenAfterADownload()
{
    setUpDoneTerminal();
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    window.setDownloadIntervalMs(200);
    QComboBox *names = window.findChild<QComboBox *>("cashierCombo");
    QTRY_COMPARE_WITH_TIMEOUT(names->count(), 1, 5000);

    server_->reply("/api/pull/users?limit=200", 200, q("{'status':'ok','more':false,'server_time':'2026-10-07 09:10:00','rows':["
                                                       "{'id':6,'username':'zed','name':'Zed','role':'cashier','pos':true,'active':true,'pos_salt':'aa11','pos_hash':'hh','deleted':false}]}"));

    QTRY_COMPARE_WITH_TIMEOUT(names->count(), 2, 10000);
    QCOMPARE(window.currentPage(), QString("login"));
}

// ---- machine config ----

// Nozzles that were added by hand (no fuel type, no tank: the board reports its config when it answers).
void Smart210Test::addNozzleRows(const QList<int> &numbers)
{
    foreach (int number, numbers) {
        run("INSERT INTO nozzles (number, tank_id, fuel_type, board_address, mode, synced, deleted) VALUES (" + QString::number(number) + ", 0, '', " + QString::number(number) + ", 0, 0, 0)");
    }
}

void Smart210Test::addNozzles()
{
    run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (1, 'T1', 'D', 5000000, 100000)");
    run("INSERT OR IGNORE INTO fuel_types (fuel_type, name, price, density) VALUES ('D', 'Diesel', 750, 8300)");
    run("INSERT OR IGNORE INTO fuel_types (fuel_type, name, price, density) VALUES ('A', 'Gasoline', 800, NULL)");
    run("INSERT INTO nozzles (id, number, tank_id, fuel_type, board_address, synced, deleted, mode, density, seen_at) "
        "VALUES (20, 2, 1, 'D', 2, 1, 0, 0, 8300, '2026-10-08 09:00:00')");
    run("INSERT INTO nozzles (id, number, tank_id, fuel_type, board_address, synced, deleted, mode, density, seen_at) "
        "VALUES (10, 1, 1, 'D', 1, 1, 0, 0, 8300, '2026-10-08 09:00:00')");
    run("INSERT INTO nozzle_config (nozzle_id, pulses_per_liter) VALUES (10, 120000)");
    run("INSERT INTO nozzle_config (nozzle_id, pulses_per_liter) VALUES (20, 123450)");
}

// ---- the PIN and the Machine Config page ----

void Smart210Test::setUpLoggedInTerminalWithAPin()
{
    setUpDoneTerminal();
    addUser(7, "Cash One", "secret1");
    addNozzles();
    QVERIFY(Session(QSqlDatabase::database(connection_)).login(7, "secret1"));
    Settings settings(QSqlDatabase::database(connection_));
    settings.setValue(SettingKey::PinSalt, "ps");
    settings.setValue(SettingKey::PinHash, QString::fromLatin1(QCryptographicHash::hash(QByteArray("ps4821"), QCryptographicHash::Sha256).toHex()));
}

template <class T> static T *visibleChild(QWidget &window, const char *name)
{
    foreach (T *child, window.findChildren<T *>(name)) {
        if (child->isVisibleTo(&window)) {
            return child;
        }
    }

    return 0;
}

void Smart210Test::machineConfigIsBehindThePinEveryTime()
{
    setUpLoggedInTerminalWithAPin();
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    QCOMPARE(window.currentPage(), QString("spot"));

    window.showPage("settings");
    QCOMPARE(window.currentPage(), QString("settings"));
    window.showPage("pin");
    QCOMPARE(window.currentPage(), QString("pin"));

    QLineEdit *pin = window.findChild<QLineEdit *>("pinField");
    QPushButton *ok = window.findChild<QPushButton *>("pinOkButton");
    pin->setText("1234");
    ok->click();
    QCOMPARE(window.currentPage(), QString("pin")); // wrong
    QCOMPARE(pin->text(), QString());               // and not kept

    pin->setText("4821");
    ok->click();
    QCOMPARE(window.currentPage(), QString("machine"));

    window.findChild<QPushButton *>("machineBackButton")->click();
    QCOMPARE(window.currentPage(), QString("settings"));

    window.showPage("pin"); // a second time: asked again, with an empty field
    QCOMPARE(window.currentPage(), QString("pin"));
    QCOMPARE(window.findChild<QLineEdit *>("pinField")->text(), QString());
    window.findChild<QPushButton *>("pinCancelButton")->click();
    QCOMPARE(window.currentPage(), QString("settings"));
}

void Smart210Test::fiveWrongPinsShutTheDoorForAWhile()
{
    setUpLoggedInTerminalWithAPin();
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    Settings settings(QSqlDatabase::database(connection_));
    window.showPage("settings");
    window.showPage("pin");
    QLineEdit *pin = window.findChild<QLineEdit *>("pinField");
    QPushButton *ok = window.findChild<QPushButton *>("pinOkButton");

    for (int i = 0; i < 4; ++i) {
        pin->setText("0000");
        ok->click();
    }
    QCOMPARE(settings.intValue(SettingKey::PinFailures), 4);
    pin->setText("4821"); // a right PIN after four wrong ones opens it and counts afresh
    ok->click();
    QCOMPARE(window.currentPage(), QString("machine"));
    QCOMPARE(settings.intValue(SettingKey::PinFailures), 0);

    window.findChild<QPushButton *>("machineBackButton")->click();
    window.showPage("pin");
    pin = window.findChild<QLineEdit *>("pinField");
    for (int i = 0; i < 5; ++i) {
        pin->setText("0000");
        ok->click();
    }
    QVERIFY(QDateTime::fromString(settings.value(SettingKey::PinLockedUntil), "yyyy-MM-dd HH:mm:ss") > QDateTime::currentDateTime());

    pin->setText("4821"); // locked: even the right PIN does not open it
    ok->click();
    QCOMPARE(window.currentPage(), QString("pin"));

    // a restart does not undo the lock: it is in the settings, and ends by the clock
    settings.setValue(SettingKey::PinLockedUntil, QDateTime::currentDateTime().addSecs(-1).toString("yyyy-MM-dd HH:mm:ss"));
    pin->setText("4821");
    ok->click();
    QCOMPARE(window.currentPage(), QString("machine"));
}

void Smart210Test::withoutAPinMachineConfigStaysClosed()
{
    setUpLoggedInTerminalWithAPin();
    run("DELETE FROM settings WHERE key = 'pin_hash'"); // the web app has no PIN for this device
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));

    window.showPage("settings");
    window.showPage("pin");
    window.findChild<QLineEdit *>("pinField")->setText("4821");
    window.findChild<QPushButton *>("pinOkButton")->click();

    QCOMPARE(window.currentPage(), QString("pin"));
}

void Smart210Test::theServerCanBeChangedOnThePageAndIsLogged()
{
    setUpLoggedInTerminalWithAPin();
    serverKnowsThisTerminal();
    Settings(QSqlDatabase::database(connection_)).setInt(SettingKey::ServerPort, 1); // a wrong port from the past
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    window.showPage("settings");
    window.showPage("pin");
    window.findChild<QLineEdit *>("pinField")->setText("4821");
    window.findChild<QPushButton *>("pinOkButton")->click();
    QCOMPARE(window.currentPage(), QString("machine"));

    QLineEdit *port = visibleChild<QLineEdit>(window, "serverPort");
    QVERIFY(port != 0);
    QCOMPARE(port->text(), QString("1")); // shows what is saved
    port->setText(QString::number(server_->port()));
    visibleChild<QPushButton>(window, "testConnectionButton")->click();

    QTRY_COMPARE_WITH_TIMEOUT(Settings(QSqlDatabase::database(connection_)).intValue(SettingKey::ServerPort), int(server_->port()), 10000);
    QTRY_COMPARE_WITH_TIMEOUT(changes().recentChanges(100).size(), 1, 5000);
    QCOMPARE(window.currentPage(), QString("machine")); // the setup page's form did not react: no jump to the first download
    const MachineConfig::Change change = changes().recentChanges(1).at(0);
    QCOMPARE(change.what, QString("server"));
    QCOMPARE(change.oldValue, QString("127.0.0.1:1"));
    QCOMPARE(change.newValue, "127.0.0.1:" + QString::number(server_->port()));
    QCOMPARE(change.userName, QString("Cash One"));
}

// ---- the Upload data screen ----

void Smart210Test::theSummaryAddsUpExactlyWhatWouldBeSent()
{
    QSqlDatabase db = openDatabase();
    recordADay(); // 1 sale (10.50 kg, 78.75 paid in full), 1 supply (10.50 kg), 1 remainder (2.50), 1 loan (20.00), 1 settlement (5.00), 1 ticket
    run("INSERT INTO sales (id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id, deleted_at) "
        "VALUES ('sale-deleted', '2026-10-07 10:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5, '2026-10-07 10:01:00')");
    run("INSERT INTO sales (id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id, synced) "
        "VALUES ('sale-done', '2026-10-06 10:00:00', 'spot', 'D', 750, 999, 'cash', 750, 750, 'sale', 5, 1)");
    run("INSERT INTO upload_errors (table_name, row_key, message, created_at) VALUES ('sales', 'sale-1', 'customer does not exist', '2026-10-07 11:00:00')");

    const UploadSummary s = UploadSummary::compute(db);

    QCOMPARE(s.sales, 1);                       // not the deleted one, not the uploaded one
    QCOMPARE(s.salesKg, qlonglong(1050));
    QCOMPARE(s.salesAmount, qlonglong(7875));
    QCOMPARE(s.salesPaid, qlonglong(7875));
    QCOMPARE(s.supplements, 1);
    QCOMPARE(s.supplementKg, qlonglong(1050));
    QCOMPARE(s.remainders, 1);
    QCOMPARE(s.remainderKg, qlonglong(250));
    QCOMPARE(s.loans, 1);
    QCOMPARE(s.loanKg, qlonglong(2000));
    QCOMPARE(s.settlements, 1);
    QCOMPARE(s.settlementKg, qlonglong(500));
    QCOMPARE(s.tickets, 1);
    QCOMPARE(s.deletedSales, 1);
    QCOMPARE(s.refused, 1);
    QCOMPARE(s.total(), 6);
    QCOMPARE(s.total(), Uploader::pendingTotal(db)); // the screen and the upload agree
}

void Smart210Test::anEmptyDayHasAnEmptySummary()
{
    const UploadSummary s = UploadSummary::compute(openDatabase());

    QCOMPARE(s.total(), 0);
    QCOMPARE(s.salesAmount, qlonglong(0));
    QCOMPARE(s.refused, 0);
}

void Smart210Test::theFooterCountsWhatWaitsAndTheReviewScreenListsIt()
{
    setUpLoggedInTerminalWithAPin();
    recordADay();
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));

    QCOMPARE(window.currentPage(), QString("spot")); // there is no home page: the program opens on Sales
    QCOMPARE(footerWaiting(window), QString("Waiting for upload: 6"));
    window.findChild<QPushButton *>("navReview")->click();

    QCOMPARE(window.currentPage(), QString("review"));
    QVERIFY(window.findChild<QPushButton *>("navReview")->isChecked());
    window.findChild<QDateEdit *>("reviewDate")->setDate(QDate(2026, 10, 7)); // the day of recordADay()
    QCOMPARE(window.findChild<QPushButton *>("uploadButton")->text(), QString("Upload data (6 waiting)"));
    QVERIFY(window.findChild<QPushButton *>("uploadButton")->isEnabled());

    QLabel *salesTotals = window.findChild<QLabel *>("reviewSalesTotals");
    QVERIFY(salesTotals->text().contains("Spot sales: 1 records, 10.50 kg, amount 78.75"));
    QVERIFY(salesTotals->text().contains("Total paid: 78.75, unpaid: 0.00"));
    QTableWidget *sales = window.findChild<QTableWidget *>("reviewSalesTable");
    QCOMPARE(sales->rowCount(), 1);
    QCOMPARE(sales->item(0, 1)->text(), QString("Spot / Sale"));
    QCOMPARE(sales->item(0, 4)->text(), QString("10.50")); // not 1050
    QCOMPARE(sales->item(0, 5)->text(), QString("78.75"));
    QCOMPARE(sales->item(0, 7)->text(), QString("2026-10-07"));
    QCOMPARE(sales->item(0, 8)->text(), QString("Waiting"));
    QTableWidget *supplies = window.findChild<QTableWidget *>("reviewSupplyTable");
    QCOMPARE(supplies->item(0, 3)->text(), QString("12.65"));
    QCOMPARE(supplies->item(0, 6)->text(), QString("Yes"));
    QVERIFY(window.findChild<QLabel *>("reviewSupplyTotals")->text().contains("Remainders: 1 records, 2.50 kg"));
    QTableWidget *debts = window.findChild<QTableWidget *>("reviewDebtTable");
    QCOMPARE(debts->rowCount(), 2); // the loan and its settlement
    QVERIFY(window.findChild<QLabel *>("reviewDebtTotals")->text().contains("Settlements: 1 records, 5.00 kg"));

    window.showPage("spot");
    QCOMPARE(window.currentPage(), QString("spot"));
}

void Smart210Test::thereIsNoHomePageAndTheDownloadRunsEveryMinute()
{
    QVERIFY(!SideBar::keys().contains("home"));
    QCOMPARE(SideBar::keys().first(), QString("sales"));
    QCOMPARE(AppConfig().downloadMinutes(), 1);

    setUpLoggedInTerminalWithAPin();
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    QVERIFY(window.findChild<QPushButton *>("navHome") == 0);
    QCOMPARE(window.currentPage(), QString("spot"));      // the program opens on Sales
    window.showPage("ticket");
    window.showPage("review");
    window.findChild<QPushButton *>("navSales")->click();
    QCOMPARE(window.currentPage(), QString("ticket"));    // and the sidebar returns to the tab that was open
}

// Feeds the jamo to the composer and returns what is committed, with the syllable that is still being composed at the end.
static QString composeAll(const QString &jamo)
{
    HangulComposer composer;
    QString out;
    for (int i = 0; i < jamo.size(); ++i) {
        out += composer.input(jamo[i]);
    }

    return out + composer.flush();
}

void Smart210Test::theHangulComposerBuildsSyllables()
{
    QCOMPARE(composeAll(QString::fromUtf8("ㅇㅏㄴㄴㅕㅇ")), QString::fromUtf8("안녕"));              // dkssud
    QCOMPARE(composeAll(QString::fromUtf8("ㄱㅏㄴㅏㄷㅏ")), QString::fromUtf8("가나다"));            // rkskek
    QCOMPARE(composeAll(QString::fromUtf8("ㄱㅣㄹㅗㄱ")), QString::fromUtf8("기록"));                  // the final moves to the next syllable
    QCOMPARE(composeAll(QString::fromUtf8("ㅇㅗㅏ")), QString::fromUtf8("와"));                         // a compound vowel
    QCOMPARE(composeAll(QString::fromUtf8("ㅎㅏㄹㄱ")), QString::fromUtf8("핡"));                       // a compound final
    QCOMPARE(composeAll(QString::fromUtf8("ㅎㅏㄹㄱㅏ")), QString::fromUtf8("할가"));                   // ...which splits when a vowel follows
    QCOMPARE(composeAll(QString::fromUtf8("ㄲㅏ")), QString::fromUtf8("까"));                           // a double initial (Shift+r)
    QCOMPARE(composeAll(QString::fromUtf8("ㅏ")), QString::fromUtf8("ㅏ"));                             // a vowel on its own
    QCOMPARE(composeAll(QString::fromUtf8("ㅂㅣㄸㅏ")), QString::fromUtf8("비따")); // ㄸ cannot be final

    HangulComposer composer;
    composer.input(QString::fromUtf8("ㄷ")[0]);
    composer.input(QString::fromUtf8("ㅏ")[0]);
    composer.input(QString::fromUtf8("ㄹ")[0]);
    composer.input(QString::fromUtf8("ㄱ")[0]);
    QCOMPARE(composer.preedit(), QString::fromUtf8("닭"));
    QVERIFY(composer.backspace());
    QCOMPARE(composer.preedit(), QString::fromUtf8("달"));                                              // the compound final loses its last part
    QVERIFY(composer.backspace());
    QCOMPARE(composer.preedit(), QString::fromUtf8("다"));
    QVERIFY(composer.backspace());
    QCOMPARE(composer.preedit(), QString::fromUtf8("ㄷ"));
    QVERIFY(composer.backspace());
    QVERIFY(!composer.composing());
    QVERIFY(!composer.backspace());                                                                      // nothing left: the field's own Backspace

    composer.input(QString::fromUtf8("ㄱ")[0]);
    QCOMPARE(composer.input(QChar('1')), QString::fromUtf8("ㄱ") + "1");                                // a character that is no jamo commits and goes on
}

void Smart210Test::theHangulInputTypesHangulInTheFocusedFieldAndTheFooterSaysWhichMode()
{
    HangulInput input;
    QVERIFY(!input.hangulMode());                                                                        // English at first

    QWidget host;
    QVBoxLayout *layout = new QVBoxLayout(&host);
    QLineEdit &edit = *new QLineEdit(&host);       // children of the host: it deletes them
    QLineEdit &secret = *new QLineEdit(&host);
    secret.setEchoMode(QLineEdit::Password);
    layout->addWidget(&edit);
    layout->addWidget(&secret);
    host.show();
    QApplication::setActiveWindow(&host);
    QVERIFY(QTest::qWaitForWindowActive(&host));
    edit.setFocus();
    QTRY_VERIFY(edit.hasFocus());

    QSignalSpy modes(&input, SIGNAL(hangulModeChanged(bool)));
    QTest::keyClicks(&edit, "abc");
    QCOMPARE(edit.text(), QString("abc"));                                                               // English: the keys pass through
    edit.clear();

    QTest::keyClick(&edit, Qt::Key_Space, Qt::ControlModifier);                                          // Ctrl+Space: Hangul on
    QVERIFY(input.hangulMode());
    QCOMPARE(modes.size(), 1);
    QTest::keyClicks(&edit, "dkssud");
    QTest::keyClick(&edit, Qt::Key_Space);                                                               // Space commits the syllable and is typed
    QCOMPARE(edit.text(), QString::fromUtf8("안녕 "));
    QTest::keyClicks(&edit, "rkskek");
    QTest::keyClick(&edit, Qt::Key_Backspace);                                                           // Backspace takes the last jamo off the syllable
    QTest::keyClick(&edit, Qt::Key_Backspace);
    QTest::keyClick(&edit, Qt::Key_Backspace);
    QTest::keyClicks(&edit, "1");                                                                        // digits go on as typed
    QCOMPARE(edit.text(), QString::fromUtf8("안녕 가1"));
    QTest::keyClick(&edit, 'R', Qt::ShiftModifier);                                                      // Shift+r is ㄲ
    QTest::keyClicks(&edit, "k");
    QTest::keyClick(&edit, Qt::Key_Space);
    QCOMPARE(edit.text(), QString::fromUtf8("안녕 가1까 "));

    secret.setFocus();                                                                                    // a field that hides what is typed is left alone
    QTRY_VERIFY(secret.hasFocus());
    QTest::keyClicks(&secret, "abc");
    QCOMPARE(secret.text(), QString("abc"));

    edit.setFocus();
    QTRY_VERIFY(edit.hasFocus());
    QTest::keyClick(&edit, Qt::Key_Space, Qt::ControlModifier);                                          // back to English
    QVERIFY(!input.hangulMode());
    QCOMPARE(modes.size(), 2);

    // the footer of the window shows the mode
    setUpLoggedInTerminalWithAPin();
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    QLabel *footer = window.findChild<QLabel *>("footerInput");
    QVERIFY(footer != 0);
    QVERIFY(!footer->isHidden());
    QCOMPARE(footer->text(), QString("A"));
    window.findChild<HangulInput *>()->setHangulMode(true);
    QCOMPARE(footer->text(), QString::fromUtf8("한"));

    AppConfig off;
    off.setBoardLink(false);
    off.setChangeLogFile(changeLog());
    off.setHangulInput(false);
    MainWindow plain(off, QString(), "db", QSqlDatabase::database(connection_));
    QVERIFY(plain.findChild<HangulInput *>() == 0);                                                      // hangul=false: no input method at all
    QVERIFY(plain.findChild<QLabel *>("footerInput")->isHidden());
}

void Smart210Test::aNewRecordHasItsDateAtOnce()
{
    // the date is the business day (9 AM to 9 AM) of the moment of the record
    setUpSellingTerminal();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    SpotSale::Input input;
    input.usage = "ticket_loan"; input.fuelType = "D"; input.customerId = 2; input.priceX100 = 750; input.kgX100 = 1050;

    const QDateTime night(QDate(2026, 10, 8), QTime(2, 30));   // 02:30 belongs to the day before
    QVERIFY(SpotSale::create(db, input, 7, night).ok);
    QCOMPARE(one("SELECT sale_date FROM sales").toString(), QString("2026-10-07"));
    QCOMPARE(one("SELECT date FROM ticket_loans").toString(), QString("2026-10-07"));

    run("DELETE FROM sales");
    const QDateTime noon(QDate(2026, 10, 8), QTime(12, 0));
    QVERIFY(SpotSale::create(db, input, 7, noon).ok);
    QCOMPARE(one("SELECT sale_date FROM sales").toString(), QString("2026-10-08"));
    QCOMPARE(Checkout::openRecords(db), 0);                    // nothing waits for the checkout any more
}

void Smart210Test::theUploadModesSayUpToWhenRecordsGoByThemselves()
{
    const QDateTime afternoon(QDate(2026, 10, 7), QTime(15, 0));
    const QDateTime earlyMorning(QDate(2026, 10, 7), QTime(7, 0));

    QCOMPARE(AutoUpload::modeOf("close"), AutoUpload::CloseOfDay);
    QCOMPARE(AutoUpload::modeOf("continuous"), AutoUpload::Continuous);
    QCOMPARE(AutoUpload::modeOf("manual"), AutoUpload::Manual);
    QCOMPARE(AutoUpload::modeOf("whatever"), AutoUpload::Manual);   // anything else is manual
    QCOMPARE(AutoUpload::textOf(AutoUpload::CloseOfDay), QString("close"));

    QCOMPARE(AutoUpload::cutoff(AutoUpload::Manual, afternoon), QString());
    QCOMPARE(AutoUpload::cutoff(AutoUpload::CloseOfDay, afternoon), QString("2026-10-07 09:00:00"));      // the running day began at 9
    QCOMPARE(AutoUpload::cutoff(AutoUpload::CloseOfDay, earlyMorning), QString("2026-10-06 09:00:00"));   // still the day before
    QCOMPARE(AutoUpload::cutoff(AutoUpload::Continuous, afternoon), QString("2026-10-07 14:30:00"));       // 30 minutes of grace
}

void Smart210Test::anUploadBeforeAMomentSendsOnlyTheOlderRecordsAndSkipsRefusedOnes()
{
    configureServer();
    setUpSelling();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const QString columns = "(id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id, sale_date, synced) ";
    run("INSERT INTO sales " + columns + "VALUES ('old', '2026-10-06 10:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5, '2026-10-06', 0)");
    run("INSERT INTO sales " + columns + "VALUES ('refused', '2026-10-06 11:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5, '2026-10-06', 0)");
    run("INSERT INTO sales " + columns + "VALUES ('new', '2026-10-07 10:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5, '2026-10-07', 0)");
    run("INSERT INTO upload_errors (table_name, row_key, message, created_at) VALUES ('sales', 'refused', 'No such customer.', '2026-10-06 12:00:00')");

    QCOMPARE(Uploader::pending(db).value("sales"), 3);                                          // Upload data tries the refused one again
    QCOMPARE(Uploader::pending(db, QString(), "2026-10-07 09:00:00").value("sales"), 1);        // the automatic upload: only the old one
    QCOMPARE(Uploader::pending(db, QString(), "2026-10-06 09:00:00").value("sales"), 0);

    server_->reply("/api/push/sales", 200, q("{'status':'ok','saved':['old'],'rejected':[]}"));
    Uploader uploader(db);
    QSignalSpy spy(&uploader, SIGNAL(uploadFinished(bool,QString)));
    uploader.upload(QString(), "2026-10-07 09:00:00");
    QVERIFY(!spy.isEmpty() || spy.wait(10000));
    QVERIFY(spy.at(0).at(0).toBool());

    QCOMPARE(one("SELECT synced FROM sales WHERE id = 'old'").toInt(), 1);
    QCOMPARE(one("SELECT synced FROM sales WHERE id = 'new'").toInt(), 0);       // made after the moment: stays
    QCOMPARE(one("SELECT synced FROM sales WHERE id = 'refused'").toInt(), 0);    // refused before: not sent again by itself
    const QJsonArray rows = QJsonDocument::fromJson(server_->requests().last().body).object().value("rows").toArray();
    QCOMPARE(rows.size(), 1);
}

void Smart210Test::cleanDataErasesTheDatabaseAndIsRefusedWhileRecordsWait()
{
    setUpLoggedInTerminalWithAPin();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    run("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (8676106000000001, 'D', 0, 500, 0, 1)");
    run("INSERT INTO sales (id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id, sale_date, synced) "
        "VALUES ('s1', '2026-10-09 10:00:00', 'spot', 'D', 750, 1000, 'cash', 7500, 7500, 'sale', 7, '2026-10-09', 0)");

    // a sale waits for the upload: refused, nothing is erased
    QString why;
    QCOMPARE(AppReset::waiting(db), 1);
    QVERIFY(!AppReset::cleanData(db, &why));
    QVERIFY2(why.contains("1 records wait for the upload"), qPrintable(why));
    QCOMPARE(countRows("sales"), 1);
    QCOMPARE(countRows("tickets"), 1);

    // uploaded: every table is emptied, the schema stays
    run("UPDATE sales SET synced = 1");
    QVERIFY2(AppReset::cleanData(db, &why), qPrintable(why));
    QSqlQuery tables(db);
    QVERIFY(tables.exec("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"));
    int checked = 0;
    while (tables.next()) {
        QCOMPARE(countRows(tables.value(0).toString()), 0);
        ++checked;
    }
    QVERIFY(checked > 10);
    QCOMPARE(Migrator::version(db), 12);                                  // the program still opens it
    QVERIFY(!Session(db).isLoggedIn());                                    // the session is gone with the data
    QVERIFY(!Settings(db).contains(SettingKey::PinHash));                  // the PIN comes down again with the first download
}

void Smart210Test::clearConfigErasesTheMachineSettingsAndKeepsTheData()
{
    setUpLoggedInTerminalWithAPin();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    run("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (8676106000000001, 'D', 0, 500, 0, 1)");
    Settings settings(db);
    settings.setValue(SettingKey::ServerHost, "192.168.1.5");
    settings.setInt(SettingKey::ServerPort, 8080);
    settings.setInt(SettingKey::DeviceId, 3);
    settings.setValue(SettingKey::PrinterHost, "192.168.1.20");
    settings.setValue(SettingKey::UploadMode, "close");
    settings.setBool(SettingKey::ShowSalesWithoutReceipt, false);
    Settings::setDeviceValue("nozzle_tank_1", "T1");
    QVERIFY(QFileInfo(Settings::deviceFile()).exists());

    QString why;
    QVERIFY2(AppReset::clearConfig(db, &why), qPrintable(why));

    QVERIFY(!QFileInfo(Settings::deviceFile()).exists());                 // the device file is gone
    QVERIFY(!settings.contains(SettingKey::ServerHost));
    QVERIFY(!settings.contains(SettingKey::DeviceId));                    // so the setup page asks for the server again
    QVERIFY(!settings.contains(SettingKey::PrinterHost));
    QVERIFY(!settings.contains(SettingKey::UploadMode));
    QVERIFY(!settings.contains(SettingKey::ShowSalesWithoutReceipt));
    QCOMPARE(Settings::deviceValue("nozzle_tank_1"), QString());
    QCOMPARE(countRows("tickets"), 1);                                    // the data stays
    QVERIFY(settings.contains(SettingKey::PinHash));                      // and what is not machine setting
    QVERIFY(Session(db).isLoggedIn());
}

void Smart210Test::machineConfigIsOutsideTheShellWithRearrangedTabsAndTheTwoResets()
{
    setUpLoggedInTerminalWithAPin();
    serverKnowsThisTerminal();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    run("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (8676106000000001, 'D', 0, 500, 0, 1)");
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", db);
    window.showPage("settings");
    window.showPage("pin");
    window.findChild<QLineEdit *>("pinField")->setText("4821");
    window.findChild<QPushButton *>("pinOkButton")->click();
    QCOMPARE(window.currentPage(), QString("machine"));

    // the page has the whole window: the sidebar, the header and the footer of the cashier's program are not in it
    QVERIFY(!window.findChild<SideBar *>()->isVisibleTo(&window));
    QVERIFY(!window.findChild<ShellFooter *>()->isVisibleTo(&window));
    QVERIFY(window.findChild<QWidget *>("MachineConfigPage")->isVisibleTo(&window));

    // the tabs by what they are about
    QTabWidget *tabs = window.findChild<QTabWidget *>("machineTabs");
    QStringList names;
    for (int i = 0; i < tabs->count(); ++i) {
        names << tabs->tabText(i);
    }
    QCOMPARE(names, QStringList() << "Server" << "Dispensers" << "Printer" << "Card reader" << "Options" << "Maintenance");
    QVERIFY(tabs->widget(0)->findChild<QComboBox *>("uploadModeCombo") != 0);                  // Server: the address and the upload
    QVERIFY(tabs->widget(0)->findChild<QCheckBox *>("showNoReceiptCheck") == 0);
    QVERIFY(tabs->widget(1)->findChild<QLineEdit *>("converterPort") != 0);                    // Dispensers: the converter port and the nozzles
    QVERIFY(tabs->widget(1)->findChild<QTableWidget *>("nozzleTable") != 0);
    QVERIFY(tabs->widget(2)->findChild<QLineEdit *>("printerHost") != 0);
    QVERIFY(tabs->widget(3)->findChild<QLineEdit *>("cardReaderPort") != 0);
    QVERIFY(tabs->widget(4)->findChild<QCheckBox *>("showNoReceiptCheck") != 0);               // Options
    QVERIFY(tabs->widget(5)->findChild<QListWidget *>("changeLog") != 0);                      // Maintenance: the log and the resets
    QVERIFY(tabs->widget(5)->findChild<QPushButton *>("cleanDataButton") != 0);
    QVERIFY(tabs->widget(5)->findChild<QPushButton *>("clearConfigButton") != 0);

    QPushButton *cleanData = window.findChild<QPushButton *>("cleanDataButton");
    QPushButton *clearConfig = window.findChild<QPushButton *>("clearConfigButton");
    QSignalSpy restart(&window, SIGNAL(restartRequested()));

    // the first press only arms the button
    cleanData->click();
    QCOMPARE(cleanData->text(), QString("Press again to confirm"));
    QCOMPARE(countRows("tickets"), 1);
    // pressing the other one first disarms it
    clearConfig->click();
    QCOMPARE(cleanData->text(), QString("Clean Data"));
    QCOMPARE(clearConfig->text(), QString("Press again to confirm"));
    QVERIFY(QFileInfo(Settings::deviceFile()).exists());

    // Clear Config: the second press erases the machine settings and asks for a restart
    clearConfig->click();
    QVERIFY(!QFileInfo(Settings::deviceFile()).exists());
    QCOMPARE(countRows("tickets"), 1);
    QVERIFY(!clearConfig->isEnabled());
    QVERIFY(window.findChild<QLabel *>("machineStatus")->text().contains("machine settings were erased"));
    QVERIFY(window.findChild<QListWidget *>("changeLog")->item(0)->text().contains("Reset: - -> clear config"));
    QVERIFY(restart.wait(4000));
    QVERIFY(window.restartWanted());
}
void Smart210Test::theSalesWithoutAReceiptCanBeLeftOutOfTheReviewScreen()
{
    setUpLoggedInTerminalWithAPin();
    serverKnowsThisTerminal();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const QString columns = "(id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id, sale_date, receipt) ";
    run("INSERT INTO sales " + columns + "VALUES ('with', '2026-10-09 10:00:00', 'spot', 'D', 750, 1000, 'cash', 7500, 7500, 'sale', 7, '2026-10-09', '1')");
    run("INSERT INTO sales " + columns + "VALUES ('none', '2026-10-09 11:00:00', 'spot', 'D', 750, 1000, 'cash', 7500, 7500, 'sale', 7, '2026-10-09', NULL)");
    run("INSERT INTO sales " + columns + "VALUES ('zero', '2026-10-09 12:00:00', 'spot', 'D', 750, 1000, 'cash', 7500, 7500, 'sale', 7, '2026-10-09', '0')");

    // the data layer: all of them, or only the ones with a receipt
    QCOMPARE(ReviewData::sales(db, "2026-10-09").size(), 3);
    QCOMPARE(ReviewData::sales(db, "2026-10-09", true).size(), 3);
    QCOMPARE(ReviewData::sales(db, "2026-10-09", false).size(), 1);
    QCOMPARE(ReviewData::sales(db, "2026-10-09", false).at(0).id, QString("with"));
    QCOMPARE(ReviewData::salesWithoutReceipt(db, "2026-10-09"), 2);

    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", db);
    window.showPage("review");
    window.findChild<QDateEdit *>("reviewDate")->setDate(QDate(2026, 10, 9));
    QTableWidget *table = window.findChild<QTableWidget *>("reviewSalesTable");
    QLabel *totals = window.findChild<QLabel *>("reviewSalesTotals");
    QCOMPARE(table->rowCount(), 3);                                       // shown by default
    QVERIFY(!totals->text().contains("not listed"));

    // Machine Config: the box is ticked, un-tick it and save
    window.showPage("settings");
    window.showPage("pin");
    window.findChild<QLineEdit *>("pinField")->setText("4821");
    window.findChild<QPushButton *>("pinOkButton")->click();
    QCOMPARE(window.currentPage(), QString("machine"));
    QCheckBox *show = window.findChild<QCheckBox *>("showNoReceiptCheck");
    QVERIFY(show != 0);
    QVERIFY(show->isChecked());
    show->setChecked(false);
    QCOMPARE(Settings(db).value(SettingKey::ShowSalesWithoutReceipt), QString());   // not saved before Save
    window.findChild<QPushButton *>("saveReviewOptionsButton")->click();
    QCOMPARE(Settings(db).value(SettingKey::ShowSalesWithoutReceipt), QString("0"));
    QVERIFY(window.findChild<QListWidget *>("changeLog")->item(0)->text().contains("Sales without a receipt: yes -> no"));

    // the Review screen lists only the sale with a receipt, says how many are left out, and its totals still count all three
    window.showPage("review");
    window.findChild<QDateEdit *>("reviewDate")->setDate(QDate(2026, 10, 9));
    QCOMPARE(table->rowCount(), 1);
    QVERIFY(totals->text().contains("Spot sales: 3 records"));
    QVERIFY(totals->text().contains("2 sales without a receipt are not listed"));

    // and back
    window.showPage("settings");
    window.showPage("pin");
    window.findChild<QLineEdit *>("pinField")->setText("4821");
    window.findChild<QPushButton *>("pinOkButton")->click();
    QVERIFY(!window.findChild<QCheckBox *>("showNoReceiptCheck")->isChecked());   // shown again when the page is reopened
    window.findChild<QCheckBox *>("showNoReceiptCheck")->setChecked(true);
    window.findChild<QPushButton *>("saveReviewOptionsButton")->click();
    window.showPage("review");
    window.findChild<QDateEdit *>("reviewDate")->setDate(QDate(2026, 10, 9));
    QCOMPARE(table->rowCount(), 3);
}
void Smart210Test::theUploadModeIsPickedAndSavedOnMachineConfig()
{
    setUpLoggedInTerminalWithAPin();
    serverKnowsThisTerminal();
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    window.showPage("settings");
    window.showPage("pin");
    window.findChild<QLineEdit *>("pinField")->setText("4821");
    window.findChild<QPushButton *>("pinOkButton")->click();
    QCOMPARE(window.currentPage(), QString("machine"));

    QComboBox *mode = window.findChild<QComboBox *>("uploadModeCombo");
    QLabel *hint = window.findChild<QLabel *>("uploadModeHint");
    QVERIFY(mode != 0 && hint != 0);
    QCOMPARE(mode->count(), 3);
    QCOMPARE(mode->currentText(), QString("Manual"));                    // the default
    QVERIFY(hint->text().contains("only when the cashier presses Upload data"));

    mode->setCurrentIndex(mode->findText("At close of day"));
    mode->activated(mode->currentIndex());
    QVERIFY(hint->text().contains("business days that are over"));
    QCOMPARE(Settings(QSqlDatabase::database(connection_)).value(SettingKey::UploadMode), QString());   // not saved before Save
    window.findChild<QPushButton *>("saveUploadModeButton")->click();
    QCOMPARE(Settings(QSqlDatabase::database(connection_)).value(SettingKey::UploadMode), QString("close"));
    QVERIFY(window.findChild<QListWidget *>("changeLog")->item(0)->text().contains("Upload: manual -> close"));

    mode->setCurrentIndex(mode->findText("Continuous"));
    mode->activated(mode->currentIndex());
    QVERIFY(hint->text().contains("30 minutes after it was made"));
    window.findChild<QPushButton *>("saveUploadModeButton")->click();
    QCOMPARE(Settings(QSqlDatabase::database(connection_)).value(SettingKey::UploadMode), QString("continuous"));

    window.showPage("settings");
    window.showPage("pin");
    window.findChild<QLineEdit *>("pinField")->setText("4821");
    window.findChild<QPushButton *>("pinOkButton")->click();
    QCOMPARE(window.findChild<QComboBox *>("uploadModeCombo")->currentText(), QString("Continuous"));   // shown again when the page is reopened
}

void Smart210Test::theTerminalUploadsByItselfInTheAutomaticModes()
{
    setUpSellingTerminal();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const QDateTime now = QDateTime::currentDateTime();
    const QString columns = "(id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id, sale_date, synced) ";
    run("INSERT INTO sales " + columns + "VALUES ('old', '" + now.addDays(-3).toString("yyyy-MM-dd HH:mm:ss") + "', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 7, '"
        + Checkout::businessDay(now.addDays(-3)) + "', 0)");
    run("INSERT INTO sales " + columns + "VALUES ('recent', '" + now.addSecs(-20).toString("yyyy-MM-dd HH:mm:ss") + "', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 7, '"
        + Checkout::businessDay(now.addSecs(-20)) + "', 0)");
    serveUploads();
    server_->reply("/api/push/sales", 200, q("{'status':'ok','saved':['old'],'rejected':[]}"));

    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", db);
    window.setUploadIntervalMs(100);
    QTRY_VERIFY_WITH_TIMEOUT(server_->log().contains("GET /api/pull/ticket_loans?limit=200"), 10000);   // the first download is done
    QTest::qWait(500);

    // manual (the default): nothing goes up by itself
    QVERIFY(!server_->log().contains("POST /api/push/sales"));
    QCOMPARE(one("SELECT synced FROM sales WHERE id = 'old'").toInt(), 0);

    // at the close of the day: what belongs to a business day that is over goes, the running day stays
    Settings(db).setValue(SettingKey::UploadMode, "close");
    QTRY_COMPARE_WITH_TIMEOUT(one("SELECT synced FROM sales WHERE id = 'old'").toInt(), 1, 10000);
    QCOMPARE(one("SELECT synced FROM sales WHERE id = 'recent'").toInt(), 0);
    QTRY_COMPARE_WITH_TIMEOUT(footerWaiting(window), QString("Waiting for upload: 1"), 5000);

    // continuous: the same record goes once it is older than the grace time; the one made a moment ago stays
    run("UPDATE sales SET synced = 0 WHERE id = 'old'");
    Settings(db).setValue(SettingKey::UploadMode, "continuous");
    QTRY_COMPARE_WITH_TIMEOUT(one("SELECT synced FROM sales WHERE id = 'old'").toInt(), 1, 10000);
    QCOMPARE(one("SELECT synced FROM sales WHERE id = 'recent'").toInt(), 0);
}

void Smart210Test::aNozzleGoesToTheWebAppByItselfWhateverTheUploadMode()
{
    setUpSellingTerminal();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    run("INSERT INTO sales (id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id, sale_date, synced) "
        "VALUES ('waits', '2020-01-01 10:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 7, '2020-01-01', 0)");
    run("UPDATE nozzles SET synced = 1");
    serveUploads();
    server_->reply("/api/push/nozzles", 200, q("{'status':'ok','saved':['5'],'rejected':[]}"));

    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", db);
    window.setUploadIntervalMs(100);
    QTRY_VERIFY_WITH_TIMEOUT(server_->log().contains("GET /api/pull/ticket_loans?limit=200"), 10000);   // the first download is done
    QTest::qWait(500);
    QVERIFY(!server_->log().contains("POST /api/push/nozzles"));   // nothing waits

    // a nozzle that was added on Machine Config goes up at once in the manual mode, and only the nozzle: the sale waits for the cashier
    QVERIFY(Settings(db).value(SettingKey::UploadMode).isEmpty() || Settings(db).value(SettingKey::UploadMode) == "manual");
    run("INSERT INTO nozzles (number, tank_id, fuel_type, board_address, mode, synced, deleted) VALUES (5, 1, 'D', 5, 0, 0, 0)");
    QTRY_VERIFY_WITH_TIMEOUT(server_->log().contains("POST /api/push/nozzles"), 10000);
    QTRY_COMPARE_WITH_TIMEOUT(one("SELECT synced FROM nozzles WHERE number = 5").toInt(), 1, 10000);
    QTest::qWait(300);
    QVERIFY(!server_->log().contains("POST /api/push/sales"));
    QCOMPARE(one("SELECT synced FROM sales WHERE id = 'waits'").toInt(), 0);
    QCOMPARE(footerWaiting(window), QString("Waiting for upload: 1"));
}

void Smart210Test::theTerminalTellsTheWebAppWhenNothingOfAFinishedDayWaits()
{
    setUpSellingTerminal();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const QDateTime now = QDateTime::currentDateTime();
    const QString columns = "(id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id, sale_date, synced) ";
    run("INSERT INTO sales " + columns + "VALUES ('old', '" + now.addDays(-3).toString("yyyy-MM-dd HH:mm:ss") + "', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 7, '"
        + Checkout::businessDay(now.addDays(-3)) + "', 0)");
    run("INSERT INTO sales " + columns + "VALUES ('recent', '" + now.addSecs(-20).toString("yyyy-MM-dd HH:mm:ss") + "', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 7, '"
        + Checkout::businessDay(now.addSecs(-20)) + "', 0)");
    serveUploads();
    server_->reply("/api/sync/uploaded", 200, q("{'status':'ok'}"));

    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", db);
    window.setUploadIntervalMs(100);
    QTRY_VERIFY_WITH_TIMEOUT(server_->log().contains("GET /api/pull/ticket_loans?limit=200"), 10000);   // the first download is done
    QTest::qWait(500);

    // a record of a business day that is over waits (the upload mode is manual): the web app is not told that the terminal is clear
    QVERIFY(!server_->log().contains("POST /api/sync/uploaded"));

    // it is uploaded: only the running day's record is left, and that one is the cashier's until the day is over
    run("UPDATE sales SET synced = 1 WHERE id = 'old'");
    QTRY_VERIFY_WITH_TIMEOUT(server_->log().contains("POST /api/sync/uploaded"), 10000);
}

void Smart210Test::unsentBeforeCountsTheRefusedRecordsToo()
{
    QSqlDatabase db = openDatabase();
    const QString columns = "(id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id, sale_date, synced) ";
    run("INSERT INTO sales " + columns + "VALUES ('refused', '2026-10-03 09:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5, '2026-10-03', 0)");
    run("INSERT INTO sales " + columns + "VALUES ('sent', '2026-10-03 10:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5, '2026-10-03', 1)");
    run("INSERT INTO sales " + columns + "VALUES ('today', '2026-10-07 10:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5, '2026-10-07', 0)");
    run("INSERT INTO upload_errors (table_name, row_key, message, created_at) VALUES ('sales', 'refused', 'no', '2026-10-03 11:00:00')");

    QCOMPARE(Uploader::pendingTotal(db, QString(), "2026-10-07 09:00:00"), 0);       // the automatic upload leaves a refused record alone
    QCOMPARE(Uploader::unsentBefore(db, "2026-10-07 09:00:00"), 1);                  // but the web app does not have it: the terminal is not clear
    QCOMPARE(Uploader::unsentBefore(db, "2026-10-03 00:00:00"), 0);                  // nothing was made before that
}

void Smart210Test::theReviewScreenOffersToPrintTheTicketOfASpotSaleAndTheRemainderTicketOfASupply()
{
    setUpLoggedInTerminalWithAPin();
    recordADay();                                                       // a spot sale, and a supply with a remainder ticket
    const QString columns = "INSERT INTO sales (id, created_at, customer_id, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, "
                            "unpaid_amount, receipt, usage, comment, sale_date, user_id) VALUES ";
    run(columns + "('sale-2', '2026-10-07 09:30:00', 1, 'ticket', 'D', 750, 1000, 'cash', 7500, 7500, 0, NULL, 'sale', NULL, '2026-10-07', 5)");
    run(columns + "('sale-3', '2026-10-07 09:40:00', NULL, 'card', 'D', 750, 2000, 'cash', 15000, 15000, 0, NULL, 'sale', NULL, '2026-10-07', 5)");
    run("INSERT INTO supplements (id, fuel_type, nozzle_id, out_amount_lt, out_amount_kg, preset_kg, density, mode, supplied_at, supply_date) "
        "VALUES ('sup-2', 'D', 2, 600, 500, 500, 8300, 'auto', '2026-10-07 09:50:00', '2026-10-07')");       // no remainder
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    window.showPage("review");
    window.findChild<QDateEdit *>("reviewDate")->setDate(QDate(2026, 10, 7));
    QPushButton *print = window.findChild<QPushButton *>("printTicketButton");
    QTableWidget *sales = window.findChild<QTableWidget *>("reviewSalesTable");
    QTableWidget *supplies = window.findChild<QTableWidget *>("reviewSupplyTable");
    QTabWidget *tabs = window.findChild<QTabWidget *>("reviewTabs");
    QVERIFY(print != 0 && sales != 0 && supplies != 0 && tabs != 0);
    QVERIFY(!print->isEnabled());                                       // nothing is selected

    QCOMPARE(sales->rowCount(), 3);
    for (int row = 0; row < sales->rowCount(); ++row) {
        sales->selectRow(row);
        const bool spot = sales->item(row, 1)->text().startsWith("Spot");
        QCOMPARE(print->isEnabled(), spot);                             // only a spot sale has a ticket: a ticket sale's tickets are the customer's, a card top-up has none
    }

    tabs->setCurrentIndex(1);
    QCOMPARE(supplies->rowCount(), 2);
    for (int row = 0; row < supplies->rowCount(); ++row) {
        supplies->selectRow(row);
        QCOMPARE(print->isEnabled(), supplies->item(row, 3)->text() == "12.65");   // the supply that made a remainder ticket (its litres)
    }

    // the remainder ticket is printed again by the program: the request reaches it, and the ticket counts as printed once it was sent
    QSignalSpy requested(window.findChild<ReviewPage *>(), SIGNAL(printRemainderRequested(QString)));
    supplies->selectRow(0);
    QVERIFY(print->isEnabled());
    print->click();
    QCOMPARE(requested.size(), 1);
    QCOMPARE(requested.at(0).at(0).toString(), QString("0000021500000001"));
}

void Smart210Test::pressingUploadSendsEverythingAndEmptiesTheScreen()
{
    setUpLoggedInTerminalWithAPin();
    recordADay();
    serveUploads();
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    window.showPage("review");
    window.findChild<QDateEdit *>("reviewDate")->setDate(QDate(2026, 10, 7));
    QPushButton *upload = window.findChild<QPushButton *>("uploadButton");
    QLabel *status = window.findChild<QLabel *>("uploadStatus");

    upload->click();
    QVERIFY(!upload->isEnabled()); // not twice

    QTRY_COMPARE_WITH_TIMEOUT(status->text(), QString("Uploaded 6 records."), 10000);
    QCOMPARE(Uploader::pendingTotal(QSqlDatabase::database(connection_)), 0);
    QTableWidget *sales = window.findChild<QTableWidget *>("reviewSalesTable");
    QCOMPARE(sales->rowCount(), 1);                          // the record is still listed
    QCOMPARE(sales->item(0, 8)->text(), QString("Uploaded")); // but no longer waiting
    QVERIFY(!upload->isEnabled());                           // and there is nothing to upload

    window.showPage("spot");
    QCOMPARE(footerWaiting(window), QString("Everything is uploaded"));
}

void Smart210Test::aFailedUploadSaysSoAndCanBePressedAgain()
{
    setUpLoggedInTerminalWithAPin();
    recordADay();
    serveUploads();
    server_->reply("/api/push/sales", 503, q("{'status':'busy'}"));
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    window.showPage("review");
    window.findChild<QDateEdit *>("reviewDate")->setDate(QDate(2026, 10, 7));
    QPushButton *upload = window.findChild<QPushButton *>("uploadButton");
    QLabel *status = window.findChild<QLabel *>("uploadStatus");

    upload->click();

    QTRY_VERIFY_WITH_TIMEOUT(status->text().contains("did not finish"), 10000);
    QVERIFY(status->text().contains("busy"));
    QCOMPARE(Uploader::pendingTotal(QSqlDatabase::database(connection_)), 6);
    QTRY_VERIFY_WITH_TIMEOUT(upload->isEnabled(), 2000); // again

    serveUploads();
    upload->click();
    QTRY_COMPARE_WITH_TIMEOUT(status->text(), QString("Uploaded 6 records."), 10000);
}

void Smart210Test::refusedRecordsStayListedWithTheirReason()
{
    setUpLoggedInTerminalWithAPin();
    recordADay();
    serveUploads();
    server_->reply("/api/push/sales", 200, q("{'status':'ok','saved':[],'rejected':[{'key':'sale-1','error':'customer does not exist'}]}"));
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    window.showPage("review");
    window.findChild<QDateEdit *>("reviewDate")->setDate(QDate(2026, 10, 7));
    QPushButton *upload = window.findChild<QPushButton *>("uploadButton");

    upload->click();

    QLabel *status = window.findChild<QLabel *>("uploadStatus");
    QTRY_VERIFY_WITH_TIMEOUT(status->text().contains("refused 1"), 10000);
    QTableWidget *sales = window.findChild<QTableWidget *>("reviewSalesTable");
    QCOMPARE(sales->rowCount(), 1); // still there, waiting to be uploaded again once fixed on the web
    QCOMPARE(sales->item(0, 8)->text(), QString("Refused"));
    QCOMPARE(sales->item(0, 8)->toolTip(), QString("customer does not exist"));
    QVERIFY(window.findChild<QLabel *>("reviewDayInfo")->text().contains("1 refused"));
    window.showPage("spot");
    QCOMPARE(footerWaiting(window), QString("Waiting for upload: 1")); // the refused one still waits
}

void Smart210Test::withNothingWaitingTheUploadButtonIsOff()
{
    setUpLoggedInTerminalWithAPin();
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));

    window.showPage("review");

    QCOMPARE(window.currentPage(), QString("review"));
    QVERIFY(!window.findChild<QPushButton *>("uploadButton")->isEnabled());
}

// ---- spot sale ----

// The digits of the code of a ticket this terminal makes, as the config download brings them: the first is the fuel type, the second the kind.
static void setTicketCodes(Settings &settings)
{
    settings.setValue("ticket_fuel_digit_A", "1");
    settings.setValue("ticket_fuel_digit_D", "2");
    settings.setValue("ticket_type_digit_direct", "1");
    settings.setValue("ticket_type_digit_self", "2");
    settings.setValue("ticket_type_digit_other", "3");
    settings.setValue("ticket_type_digit_ticket_loan", "4");
    settings.setValue("ticket_type_digit_card", "5");
    settings.setValue("ticket_type_digit_remainder", "9");
}
void Smart210Test::setUpSelling()
{
    QSqlDatabase db = openDatabase();
    run("INSERT OR REPLACE INTO fuel_types (fuel_type, name, price, density) VALUES ('D', 'Diesel', 750, 8300)");
    run("INSERT OR REPLACE INTO customers (id, name, phone, is_self, deleted) VALUES (1, 'Self', NULL, 1, 0)");
    run("INSERT OR REPLACE INTO customers (id, name, phone, is_self, deleted) VALUES (2, 'ACME', NULL, 0, 0)");
    run("INSERT OR REPLACE INTO customers (id, name, phone, is_self, deleted) VALUES (3, 'Gone Ltd', NULL, 0, 1)");
    Settings settings(db);
    settings.setValue(SettingKey::TicketSign, "00000");
    setTicketCodes(settings);
    settings.setValue(SettingKey::TicketSerialBase, "500000000");
}

static SpotSale::Input saleInput(qlonglong kgX100, int customer = 2)
{
    SpotSale::Input input;
    input.usage = "sale";
    input.fuelType = "D";
    input.customerId = customer;
    input.priceX100 = 750;
    input.kgX100 = kgX100;

    return input;
}

void Smart210Test::theAmountAndTheWeightFollowEachOther()
{
    QCOMPARE(SpotSale::amountFor(1050, 750), qlonglong(7875));   // 10.50 kg at 7.50
    QCOMPARE(SpotSale::amountFor(1333, 750), qlonglong(9998));   // 13.33 kg: 99.975 rounds to 99.98
    QCOMPARE(SpotSale::kgFor(10000, 750), qlonglong(1333));      // 100.00 buys 13.33 kg
    QCOMPARE(SpotSale::kgFor(7875, 750), qlonglong(1050));
    QCOMPARE(SpotSale::kgFor(1000, 0), qlonglong(0));           // no price: nothing to divide by
    QCOMPARE(SpotSale::amountFor(SpotSale::MaxKgX100, SpotSale::MaxPriceX100), qlonglong(99999900LL * 999999900LL / 100)); // the largest: no overflow
}

void Smart210Test::aSpotSaleWritesTheSaleItsTicketAndNothingElse()
{
    setUpSelling();
    QSqlDatabase db = QSqlDatabase::database(connection_);

    const SpotSale::Result result = SpotSale::create(db, saleInput(1050), 7, QDateTime(QDate(2026, 10, 7), QTime(9, 30, 0)));

    QVERIFY2(result.ok, qPrintable(result.error));
    QCOMPARE(result.barcode, QString("0000021500000001")); // sign 00000, code 21, the first serial above the base
    QCOMPARE(result.totalX100, qlonglong(7875));

    QSqlQuery query(db);
    QVERIFY(query.exec("SELECT id, created_at, customer_id, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, unpaid_amount, "
                       "usage, sale_date, user_id, synced, deleted_at, receipt FROM sales"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toString(), result.saleId);
    QCOMPARE(query.value(0).toString().size(), 36);
    QCOMPARE(query.value(1).toString(), QString("2026-10-07 09:30:00"));
    QCOMPARE(query.value(2).toInt(), 2);
    QCOMPARE(query.value(3).toString(), QString("spot"));
    QCOMPARE(query.value(5).toLongLong(), qlonglong(750));
    QCOMPARE(query.value(6).toLongLong(), qlonglong(1050));
    QCOMPARE(query.value(7).toString(), QString("cash"));
    QCOMPARE(query.value(8).toLongLong(), qlonglong(7875));
    QCOMPARE(query.value(9).toLongLong(), qlonglong(7875)); // paid in full
    QCOMPARE(query.value(10).toLongLong(), qlonglong(0));
    QCOMPARE(query.value(11).toString(), QString("sale"));
    QCOMPARE(query.value(12).toString(), Checkout::businessDay(QDateTime::fromString(query.value(1).toString(), "yyyy-MM-dd HH:mm:ss"))); // the business day of the moment, at once
    QCOMPARE(query.value(13).toInt(), 7);
    QCOMPARE(query.value(14).toInt(), 0);                   // waits for the upload
    QVERIFY(query.value(15).isNull());
    QVERIFY(!query.next());

    QVERIFY(query.exec("SELECT barcode, fuel_type, spec, amount_kg, status, sale_id, sale_date, synced FROM tickets"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toLongLong(), qlonglong(21500000001LL));
    QVERIFY(query.value(2).isNull());                       // a minted ticket has no spec
    QCOMPARE(query.value(3).toLongLong(), qlonglong(1050));
    QCOMPARE(query.value(4).toInt(), 1);                    // sold
    QCOMPARE(query.value(5).toString(), result.saleId);
    QCOMPARE(query.value(7).toInt(), 0);
    QCOMPARE(countRows("ticket_loans"), 0);
    QCOMPARE(Uploader::pendingTotal(db), 2);                // the sale and its ticket
}

void Smart210Test::anAmountTypedByTheCashierIsKeptExactly()
{
    setUpSelling();
    SpotSale::Input input = saleInput(1333); // 13.33 kg
    input.amountX100 = 10000;                // the customer pays exactly 100.00

    const SpotSale::Result result = SpotSale::create(QSqlDatabase::database(connection_), input, 7, QDateTime::currentDateTime());

    QVERIFY(result.ok);
    QCOMPARE(result.totalX100, qlonglong(10000)); // not 99.98
    QSqlQuery query(QSqlDatabase::database(connection_));
    QVERIFY(query.exec("SELECT total_amount, paid_amount, amount_kg FROM sales"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toLongLong(), qlonglong(10000));
    QCOMPARE(query.value(1).toLongLong(), qlonglong(10000));
    QCOMPARE(query.value(2).toLongLong(), qlonglong(1333));
}

void Smart210Test::ownUseAndOtherHaveNoAmountAndALoanMakesATicketLoan()
{
    setUpSelling();
    QSqlDatabase db = QSqlDatabase::database(connection_);

    SpotSale::Input self = saleInput(500, 1);
    self.usage = "self";
    QVERIFY(SpotSale::create(db, self, 7, QDateTime::currentDateTime()).ok);
    SpotSale::Input other = saleInput(300);
    other.usage = "other";
    other.amountX100 = 5000; // an amount typed for something that has none is ignored
    QVERIFY(SpotSale::create(db, other, 7, QDateTime::currentDateTime()).ok);

    QSqlQuery query(db);
    QVERIFY(query.exec("SELECT usage, total_amount, paid_amount, amount_kg FROM sales ORDER BY amount_kg"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toString(), QString("other"));
    QCOMPARE(query.value(1).toLongLong(), qlonglong(0));
    QCOMPARE(query.value(2).toLongLong(), qlonglong(0));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toString(), QString("self"));
    QCOMPARE(countRows("ticket_loans"), 0);

    SpotSale::Input loan = saleInput(2000);
    loan.usage = "ticket_loan";
    const SpotSale::Result result = SpotSale::create(db, loan, 7, QDateTime::currentDateTime());
    QVERIFY(result.ok);
    QCOMPARE(result.totalX100, qlonglong(0));
    QVERIFY(query.exec("SELECT id, customer_id, fuel_type, amount_kg, settled_amount_kg, sale_id, user_id, deleted, synced FROM ticket_loans"));
    QVERIFY(query.next());
    QCOMPARE(query.value(1).toInt(), 2);
    QCOMPARE(query.value(3).toLongLong(), qlonglong(2000));
    QCOMPARE(query.value(4).toLongLong(), qlonglong(0));
    QCOMPARE(query.value(5).toString(), result.saleId); // the loan belongs to its sale
    QCOMPARE(query.value(7).toInt(), 0);
    QCOMPARE(query.value(8).toInt(), 0);
}

void Smart210Test::ticketNumbersNeverRepeatEvenAfterAPurgeOrOnANewBoard()
{
    setUpSelling();
    QSqlDatabase db = QSqlDatabase::database(connection_);

    QCOMPARE(SpotSale::create(db, saleInput(100), 7, QDateTime::currentDateTime()).barcode, QString("0000021500000001"));
    QCOMPARE(SpotSale::create(db, saleInput(100), 7, QDateTime::currentDateTime()).barcode, QString("0000021500000002"));

    run("DELETE FROM tickets"); // a purge removed them; the counter remembers
    QCOMPARE(SpotSale::create(db, saleInput(100), 7, QDateTime::currentDateTime()).barcode, QString("0000021500000003"));

    // a replaced board: nothing local, but the web app says it already holds up to 500000010
    run("DELETE FROM tickets");
    run("DELETE FROM settings WHERE key = 'serial_next_21'");
    Settings(db).setValue("serial_floor_21", "500000010");
    QCOMPARE(SpotSale::create(db, saleInput(100), 7, QDateTime::currentDateTime()).barcode, QString("0000021500000011"));

    // tickets already in the database push the number up too
    run("DELETE FROM settings WHERE key = 'serial_next_21'");
    Settings(db).setValue("serial_floor_21", "500000000");
    run("INSERT INTO tickets (barcode, fuel_type, amount_kg, status, synced) VALUES (21500000050, 'D', 100, 2, 1)");
    QCOMPARE(SpotSale::create(db, saleInput(100), 7, QDateTime::currentDateTime()).barcode, QString("0000021500000051"));
}

void Smart210Test::aSpotSaleThatIsNotAllowedWritesNothing()
{
    setUpSelling();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const QDateTime now = QDateTime::currentDateTime();

    SpotSale::Input input = saleInput(1050);
    QVERIFY(!SpotSale::create(db, input, 0, now).ok);                         // nobody logged in

    input = saleInput(1050); input.usage = "gift";
    QVERIFY(!SpotSale::create(db, input, 7, now).ok);
    input = saleInput(1050); input.fuelType = "X";
    QVERIFY(!SpotSale::create(db, input, 7, now).ok);
    QVERIFY(!SpotSale::create(db, saleInput(1050, 99), 7, now).ok);           // no such customer
    QVERIFY(!SpotSale::create(db, saleInput(1050, 3), 7, now).ok);            // a customer deleted on the web
    QVERIFY(!SpotSale::create(db, saleInput(0), 7, now).ok);                  // no weight
    QVERIFY(!SpotSale::create(db, saleInput(SpotSale::MaxKgX100 + 1), 7, now).ok);
    input = saleInput(1050); input.priceX100 = 0;                             // a sale needs a price
    QVERIFY(!SpotSale::create(db, input, 7, now).ok);
    input = saleInput(1050); input.amountX100 = 0;
    QVERIFY(!SpotSale::create(db, input, 7, now).ok);
    input = saleInput(1050); input.amountX100 = SpotSale::MaxAmountX100 + 1;
    QVERIFY(!SpotSale::create(db, input, 7, now).ok);

    QCOMPARE(countRows("sales"), 0);
    QCOMPARE(countRows("tickets"), 0);
    QCOMPARE(countRows("ticket_loans"), 0);
    QCOMPARE(SpotSale::create(db, saleInput(1050), 7, now).barcode, QString("0000021500000001")); // and no number was used up
}

// The code of a ticket this terminal makes: the first digit is the fuel type (1 gasoline, 2 diesel), the second the kind (1 spot sale, 2 self use, 3 other use,
// 4 ticket loan, 5 card, 9 remainder); every code has its own serial
void Smart210Test::theCodeOfATicketIsTheFuelTypeAndTheKindAndTheSerialCountsPerCode()
{
    setUpSelling();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    QString error;

    QCOMPARE(TicketNumbers::code(db, "direct", "D"), QString("21"));
    QCOMPARE(TicketNumbers::code(db, "direct", "A"), QString("11"));
    QCOMPARE(TicketNumbers::code(db, "self", "D"), QString("22"));
    QCOMPARE(TicketNumbers::code(db, "other", "A"), QString("13"));
    QCOMPARE(TicketNumbers::code(db, "ticket_loan", "D"), QString("24"));
    QCOMPARE(TicketNumbers::code(db, "card", "A"), QString("15"));
    QCOMPARE(TicketNumbers::code(db, "remainder", "D"), QString("29"));
    QVERIFY(TicketNumbers::code(db, "direct", "X").isEmpty());          // a fuel type with no digit
    QVERIFY(TicketNumbers::code(db, "nonsense", "D").isEmpty());        // a kind with no digit
    QVERIFY(TicketNumbers::available(db, "card", "A"));
    QVERIFY(!TicketNumbers::available(db, "card", "X"));
    QCOMPARE(TicketNumbers::typeOfUsage("sale"), QString("direct"));
    QCOMPARE(TicketNumbers::typeOfUsage("self"), QString("self"));
    QCOMPARE(TicketNumbers::typeOfUsage("other"), QString("other"));
    QCOMPARE(TicketNumbers::typeOfUsage("ticket_loan"), QString("ticket_loan"));

    // the first number of each code is above the base; the next one of the same code follows it, and another code has its own count
    QCOMPARE(TicketNumbers::next(db, "direct", "D", &error), 21500000001LL);
    QCOMPARE(TicketNumbers::next(db, "direct", "D", &error), 21500000002LL);
    QCOMPARE(TicketNumbers::next(db, "direct", "A", &error), 11500000001LL);
    QCOMPARE(TicketNumbers::next(db, "card", "A", &error), 15500000001LL);
    QCOMPARE(TicketNumbers::next(db, "remainder", "D", &error), 29500000001LL);
    QCOMPARE(TicketNumbers::next(db, "self", "D", &error), 22500000001LL);
    QCOMPARE(Barcode::fromKey(15500000001LL), QString("0000015500000001"));

    // what the web app holds for a code counts for that code only
    Settings(db).setValue("serial_floor_15", "500000010");
    QCOMPARE(TicketNumbers::next(db, "card", "A", &error), 15500000011LL);
    QCOMPARE(TicketNumbers::next(db, "direct", "A", &error), 11500000002LL);

    // no digit: no number
    QCOMPARE(TicketNumbers::next(db, "direct", "X", &error), -1LL);
    QVERIFY(error.contains("not known"));
}
void Smart210Test::withoutTheTicketNumbersFromTheServerNothingIsSold()
{
    setUpSelling();
    run("DELETE FROM settings WHERE key = 'ticket_sign'"); // no config downloaded with the sign

    const SpotSale::Result result = SpotSale::create(QSqlDatabase::database(connection_), saleInput(1050), 7, QDateTime::currentDateTime());

    QVERIFY(!result.ok);
    QVERIFY(result.error.contains("Download"));
    QCOMPARE(countRows("sales"), 0);
}

void Smart210Test::theConfigDownloadBringsTheTicketNumbering()
{
    QSqlDatabase db = openDatabase();
    QJsonObject config = configObject();
    config.insert("ticket_sign", "00007");
    config.insert("pos_codes", QJsonDocument::fromJson(q("{'fuel':{'A':'1','D':'2'},'type':{'direct':'1','self':'2','other':'3','ticket_loan':'4','card':'5','remainder':'9'}}")).object());
    config.insert("ticket_serial_floor", QJsonDocument::fromJson(q("{'21':500000012,'22':500000000}")).object());
    QString error;

    QVERIFY(SyncStore(db).applyConfig(config, &error));

    Settings settings(db);
    QCOMPARE(settings.value(SettingKey::TicketSign), QString("00007")); // leading zeros kept
    QCOMPARE(settings.value("ticket_fuel_digit_A"), QString("1"));
    QCOMPARE(settings.value("ticket_fuel_digit_D"), QString("2"));
    QCOMPARE(settings.value("ticket_type_digit_direct"), QString("1"));
    QCOMPARE(settings.value("ticket_type_digit_card"), QString("5"));
    QCOMPARE(settings.value("ticket_type_digit_remainder"), QString("9"));
    QCOMPARE(TicketNumbers::code(db, "card", "A"), QString("15"));
    QCOMPARE(TicketNumbers::code(db, "remainder", "D"), QString("29"));
    QCOMPARE(settings.value("serial_floor_21"), QString("500000012"));

    // a server that sends none of it leaves what the terminal already has
    QVERIFY(SyncStore(db).applyConfig(configObject(), &error));
    QCOMPARE(settings.value(SettingKey::TicketSign), QString("00007"));
}

void Smart210Test::aSpotSaleCanBeUploadedAsItIs()
{
    configureServer();
    setUpSelling();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const SpotSale::Result result = SpotSale::create(db, saleInput(1050), 7, QDateTime(QDate(2026, 10, 7), QTime(9, 30, 0)));
    QVERIFY(result.ok);
    server_->reply("/api/push/sales", 200, "{\"status\":\"ok\",\"saved\":[\"" + result.saleId.toUtf8() + "\"],\"rejected\":[]}");
    server_->reply("/api/push/tickets", 200, q("{'status':'ok','saved':['0000021500000001'],'rejected':[]}"));
    Uploader uploader(db);
    QString message;

    QVERIFY(uploadOk(uploader, &message));

    QCOMPARE(server_->log(), QStringList() << "POST /api/push/sales" << "POST /api/push/tickets");
    const QJsonObject sale = QJsonDocument::fromJson(server_->requests().at(0).body).object().value("rows").toArray().at(0).toObject();
    QCOMPARE(sale.value("sale_type").toString(), QString("spot"));
    QCOMPARE(sale.value("payment_method").toString(), QString("cash"));
    QCOMPARE(sale.value("total_amount").toString(), QString("78.75"));
    QCOMPARE(sale.value("unpaid_amount").toString(), QString("0.00"));
    QCOMPARE(sale.value("customer_id").toInt(), 2);
    QCOMPARE(sale.value("created_at").toString().size(), 19);
    QCOMPARE(sale.value("sale_date").toString(), Checkout::businessDay(QDateTime::fromString(sale.value("created_at").toString(), "yyyy-MM-dd HH:mm:ss"))); // not dated yet: the business day of its time
    QVERIFY(!sale.contains("sold_at") && !sale.contains("date"));
    const QJsonObject ticket = QJsonDocument::fromJson(server_->requests().at(1).body).object().value("rows").toArray().at(0).toObject();
    QCOMPARE(ticket.value("barcode").toString(), QString("0000021500000001"));
    QCOMPARE(ticket.value("status").toInt(), 1);
    QCOMPARE(ticket.value("sale_id").toString(), result.saleId);
    QCOMPARE(ticket.value("amount_kg").toString(), QString("10.50"));
    QVERIFY(!ticket.value("sale_date").isNull());
}

// ---- the spot sale screen ----

void Smart210Test::setUpSellingTerminal()
{
    setUpLoggedInTerminalWithAPin();
    setUpSelling();
}

void Smart210Test::theSpotSaleScreenCalculatesBothWaysAndSaves()
{
    setUpSellingTerminal();
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    QTRY_VERIFY_WITH_TIMEOUT(server_->log().contains("GET /api/pull/ticket_loans?limit=200"), 10000);
    QTest::qWait(300); // the start-up download has finished and replaced the fuel types and customers

    window.showPage("spot");
    QCOMPARE(window.currentPage(), QString("spot"));
    QComboBox *fuel = window.findChild<QComboBox *>("fuelCombo");
    QComboBox *customer = window.findChild<QComboBox *>("customerCombo");
    QLineEdit *price = window.findChild<QLineEdit *>("priceField");
    QLineEdit *kg = window.findChild<QLineEdit *>("kgField");
    QLineEdit *amount = window.findChild<QLineEdit *>("amountField");
    QLabel *status = window.findChild<QLabel *>("saleStatus");

    // the screen must fit the 1280 x 720 terminal next to the sidebar, header and footer
    QVERIFY(window.findChild<SpotSalePage *>()->minimumSizeHint().width() <= 1050);
    QVERIFY(window.findChild<SpotSalePage *>()->minimumSizeHint().height() <= 600);
    QCOMPARE(window.findChild<QLineEdit *>("paymentField")->text(), QString("Cash")); // a spot sale is always cash
    QVERIFY(window.findChild<QLineEdit *>("paymentField")->isReadOnly());

    fuel->setCurrentIndex(fuel->findData("D"));
    QCOMPARE(price->text(), QString("7.50")); // the downloaded price

    kg->clear();
    QTest::keyClicks(kg, "10.50");
    QCOMPARE(amount->text(), QString("78.75")); // weight typed: the amount follows

    amount->clear();
    QTest::keyClicks(amount, "100");
    QCOMPARE(kg->text(), QString("13.33"));            // amount typed: the weight follows

    price->clear();
    QTest::keyClicks(price, "8");
    QCOMPARE(kg->text(), QString("12.50"));            // the price changed, the amount typed last still counts

    // the customer starts as Individual (the one the download marks); with none chosen the sale is refused
    QCOMPARE(customer->currentText(), QString("Self"));
    customer->setCurrentIndex(0);
    window.findChild<QPushButton *>("saveSaleButton")->click();
    QVERIFY(status->text().contains("customer"));
    QCOMPARE(countRows("sales"), 0);

    customer->setCurrentIndex(customer->findText("ACME"));
    window.findChild<QCheckBox *>("receiptCheck")->setChecked(true);
    window.findChild<QPushButton *>("saveSaleButton")->click();

    QVERIFY2(status->text().startsWith("Saved: 12.50 kg, amount 100.00. Ticket 00000215"), qPrintable(status->text()));
    QCOMPARE(countRows("sales"), 1);
    QCOMPARE(one("SELECT receipt FROM sales").toString(), QString("1")); // the receipt box is kept with the sale
    QVERIFY(!window.findChild<QCheckBox *>("receiptCheck")->isChecked());  // and is clear for the next one
    QCOMPARE(kg->text(), QString());                   // ready for the next one, the fuel and customer stay
    QCOMPARE(customer->currentText(), QString("ACME"));
    QSqlQuery query(QSqlDatabase::database(connection_));
    QVERIFY(query.exec("SELECT price, amount_kg, total_amount, user_id FROM sales"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toLongLong(), qlonglong(800)); // the price changed for this sale only
    QCOMPARE(query.value(1).toLongLong(), qlonglong(1250));
    QCOMPARE(query.value(2).toLongLong(), qlonglong(10000));
    QCOMPARE(query.value(3).toInt(), 7);                    // the cashier who is logged in

    window.showPage("spot");
    QCOMPARE(window.currentPage(), QString("spot"));
    QCOMPARE(footerWaiting(window), QString("Waiting for upload: 2")); // the sale and its ticket
}

void Smart210Test::ownUseGoesToTheSelfCustomerAndHasNoAmount()
{
    setUpSellingTerminal();
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    QTRY_VERIFY_WITH_TIMEOUT(server_->log().contains("GET /api/pull/ticket_loans?limit=200"), 10000);
    QTest::qWait(300);
    window.showPage("spot");
    QComboBox *customer = window.findChild<QComboBox *>("customerCombo");
    QLineEdit *amount = window.findChild<QLineEdit *>("amountField");

    window.findChild<QComboBox *>("usageCombo")->setCurrentIndex(1); // self use

    QCOMPARE(customer->currentText(), QString("Self"));
    QVERIFY(!customer->isEnabled());
    QVERIFY(!amount->isEnabled());

    QLineEdit *kg = window.findChild<QLineEdit *>("kgField");
    QTest::keyClicks(kg, "5");
    window.findChild<QPushButton *>("saveSaleButton")->click();
    QSqlQuery query(QSqlDatabase::database(connection_));
    QVERIFY(query.exec("SELECT usage, customer_id, total_amount, amount_kg FROM sales"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toString(), QString("self"));
    QCOMPARE(query.value(1).toInt(), 1);
    QCOMPARE(query.value(2).toLongLong(), qlonglong(0));
    QCOMPARE(query.value(3).toLongLong(), qlonglong(500));

    window.findChild<QComboBox *>("usageCombo")->setCurrentIndex(0); // back to a sale: the customer is free again, and starts as Individual
    QVERIFY(customer->isEnabled());
    QCOMPARE(customer->currentText(), QString("Self"));
    QVERIFY(amount->isEnabled());
}

void Smart210Test::otherUseIsIndividualTooAndALoanNeedsACustomerWhoIsChosen()
{
    setUpSellingTerminal();
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    QTRY_VERIFY_WITH_TIMEOUT(server_->log().contains("GET /api/pull/ticket_loans?limit=200"), 10000);
    QTest::qWait(300);
    window.showPage("spot");
    QComboBox *customer = window.findChild<QComboBox *>("customerCombo");
    QComboBox *usage = window.findChild<QComboBox *>("usageCombo");

    QCOMPARE(customer->currentText(), QString("Self"));                // a sale starts with Individual (the customer the download marks)
    usage->setCurrentIndex(2);                                          // other use: Individual, and it cannot be changed
    QCOMPARE(customer->currentText(), QString("Self"));
    QVERIFY(!customer->isEnabled());

    usage->setCurrentIndex(3);                                          // a ticket loan is to a customer who is chosen
    QVERIFY(customer->isEnabled());
    QCOMPARE(customer->currentIndex(), 0);
    customer->setCurrentIndex(customer->findText("ACME"));
    usage->setCurrentIndex(0);
    QCOMPARE(customer->currentText(), QString("ACME"));                // a customer who was chosen stays for a sale
    usage->setCurrentIndex(1);
    QCOMPARE(customer->currentText(), QString("Self"));
    usage->setCurrentIndex(3);
    QCOMPARE(customer->currentIndex(), 0);
}

static void typeInto(QLineEdit *field, const QString &text); // (below, with the ticket sale tests)

// Enter in the field of a form moves on to the next one
static void enterIn(QWidget *widget)
{
    QTest::keyClick(widget, Qt::Key_Return);
}

void Smart210Test::theSpotSaleIsFilledWithTheKeyboardAlone()
{
    setUpSellingTerminal();
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    QTRY_VERIFY_WITH_TIMEOUT(server_->log().contains("GET /api/pull/ticket_loans?limit=200"), 10000);
    QTest::qWait(300);
    window.showPage("spot");
    SpotSalePage *page = window.findChild<SpotSalePage *>();
    QComboBox *customer = window.findChild<QComboBox *>("customerCombo");
    QComboBox *fuel = window.findChild<QComboBox *>("fuelCombo");
    QLineEdit *price = window.findChild<QLineEdit *>("priceField");
    QComboBox *usage = window.findChild<QComboBox *>("usageCombo");
    QLineEdit *kg = window.findChild<QLineEdit *>("kgField");
    QLineEdit *amount = window.findChild<QLineEdit *>("amountField");
    QComboBox *result = window.findChild<QComboBox *>("resultCombo");
    QCheckBox *receipt = window.findChild<QCheckBox *>("receiptCheck");
    QLineEdit *comment = window.findChild<QLineEdit *>("commentField");
    QPushButton *save = window.findChild<QPushButton *>("saveSaleButton");
    QLabel *status = window.findChild<QLabel *>("saleStatus");

    QCOMPARE(page->focusWidget(), static_cast<QWidget *>(kg));          // the cursor starts at the weight
    QCOMPARE(window.findChild<QPushButton *>("navSales")->focusPolicy(), Qt::NoFocus); // the sidebar is not in the way

    // Enter goes through the fields in the order of the form, from the first one
    QWidget *order[] = {customer, fuel, price, usage, kg, amount, result, receipt, comment, save};
    customer->setFocus();
    for (unsigned i = 0; i + 1 < sizeof(order) / sizeof(order[0]); ++i) {
        QCOMPARE(page->focusWidget(), order[i]);
        enterIn(order[i]);
    }
    QCOMPARE(page->focusWidget(), static_cast<QWidget *>(save));

    // a sale: the weight, then Enter on every field to the Save button, then Enter on it
    kg->setFocus();
    QTest::keyClicks(kg, "10.5");
    QCOMPARE(amount->text(), QString("78.75"));
    enterIn(kg);
    QCOMPARE(page->focusWidget(), static_cast<QWidget *>(amount));
    enterIn(amount);
    enterIn(result);
    QCOMPARE(page->focusWidget(), static_cast<QWidget *>(receipt));
    QTest::keyClick(receipt, Qt::Key_Space);                             // Space ticks the box, Enter only moves on
    QVERIFY(receipt->isChecked());
    enterIn(receipt);
    QCOMPARE(page->focusWidget(), static_cast<QWidget *>(comment));
    QTest::keyClicks(comment, "plate 12");
    enterIn(comment);
    QCOMPARE(page->focusWidget(), static_cast<QWidget *>(save));
    QCOMPARE(countRows("sales"), 0);
    enterIn(save);
    QVERIFY2(status->text().startsWith("Saved: 10.50 kg, amount 78.75"), qPrintable(status->text()));
    QCOMPARE(countRows("sales"), 1);
    QCOMPARE(one("SELECT comment FROM sales").toString(), QString("plate 12"));
    QCOMPARE(one("SELECT customer_id FROM sales").toInt(), 1);           // Individual
    QCOMPARE(page->focusWidget(), static_cast<QWidget *>(kg));           // ready for the next sale
    QVERIFY(kg->text().isEmpty());

    // Space on the button saves too; own use has no amount: it is jumped over
    usage->setCurrentIndex(1);
    kg->setFocus();
    QTest::keyClicks(kg, "5");
    enterIn(kg);
    QCOMPARE(page->focusWidget(), static_cast<QWidget *>(result));
    save->setFocus();
    QTest::keyClick(save, Qt::Key_Space);
    QCOMPARE(countRows("sales"), 2);
    QCOMPARE(page->focusWidget(), static_cast<QWidget *>(kg));

    // a sale that is refused keeps the cursor on the button
    kg->clear();
    save->setFocus();
    enterIn(save);
    QCOMPARE(countRows("sales"), 2);
    QVERIFY(status->text().contains("weight"));
    QCOMPARE(page->focusWidget(), static_cast<QWidget *>(save));
}

void Smart210Test::theTicketSaleIsFilledWithTheKeyboardAlone()
{
    setUpSellingTerminal();
    addStockTickets();
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    QTRY_VERIFY_WITH_TIMEOUT(server_->log().contains("GET /api/pull/ticket_loans?limit=200"), 10000);
    QTest::qWait(300);
    window.showPage("ticket");
    TicketSalePage *page = window.findChild<TicketSalePage *>();
    QComboBox *customer = window.findChild<QComboBox *>("tsCustomerCombo");
    QComboBox *fuel = window.findChild<QComboBox *>("tsFuelCombo");
    QLineEdit *price = window.findChild<QLineEdit *>("tsPriceField");
    QComboBox *usage = window.findChild<QComboBox *>("tsUsageCombo");
    QComboBox *spec = window.findChild<QComboBox *>("tsSpecCombo");
    QLineEdit *start = window.findChild<QLineEdit *>("tsStartField");
    QLineEdit *count = window.findChild<QLineEdit *>("tsCountField");
    QCheckBox *receipt = window.findChild<QCheckBox *>("tsReceiptCheck");
    QLineEdit *comment = window.findChild<QLineEdit *>("tsCommentField");
    QPushButton *save = window.findChild<QPushButton *>("saveTicketSaleButton");
    QTableWidget *items = window.findChild<QTableWidget *>("tsItemsTable");

    QCOMPARE(page->focusWidget(), static_cast<QWidget *>(customer));    // the cursor starts at the customer
    customer->setCurrentIndex(customer->findText("ACME"));

    QWidget *order[] = {customer, fuel, price, usage, spec, start, count};
    for (unsigned i = 0; i + 1 < sizeof(order) / sizeof(order[0]); ++i) {
        QCOMPARE(page->focusWidget(), order[i]);
        enterIn(order[i]);
    }
    QCOMPARE(page->focusWidget(), static_cast<QWidget *>(count));

    // Enter in the count adds the item and the cursor goes back to the spec for the next one
    typeInto(count, "2");
    enterIn(count);
    QCOMPARE(items->rowCount(), 1);
    QCOMPARE(page->focusWidget(), static_cast<QWidget *>(spec));

    // an arrow key in the spec is a new item: Enter goes on to the start serial as usual
    QTest::keyClick(spec, Qt::Key_Down);
    enterIn(spec);
    QCOMPARE(page->focusWidget(), static_cast<QWidget *>(start));
    enterIn(start);
    enterIn(count);
    QCOMPARE(items->rowCount(), 2);
    QCOMPARE(page->focusWidget(), static_cast<QWidget *>(spec));

    // one more Enter at the spec, nothing typed: no more items, on to the receipt and the comment
    enterIn(spec);
    QCOMPARE(page->focusWidget(), static_cast<QWidget *>(receipt));
    enterIn(receipt);
    QCOMPARE(page->focusWidget(), static_cast<QWidget *>(comment));
    QTest::keyClicks(comment, "morning");
    enterIn(comment);
    QCOMPARE(page->focusWidget(), static_cast<QWidget *>(save));
    QCOMPARE(countRows("sales"), 0);
    enterIn(save);
    QCOMPARE(countRows("sales"), 1);
    QCOMPARE(one("SELECT comment FROM sales").toString(), QString("morning"));
    QCOMPARE(items->rowCount(), 0);
    QCOMPARE(page->focusWidget(), static_cast<QWidget *>(customer));    // ready for the next customer
}
// ---- ticket sale ----

// Tickets of every kind in the local database: three 5 kg and one 10 kg Diesel in stock, a Gasoline one, and one sold,
// one supplied and one restricted.
void Smart210Test::addStockTickets()
{
    const char *rows[] = {
        "(3000000011, 'D', 0, 500, 0, 1)", "(3000000012, 'D', 0, 500, 0, 1)", "(3000000013, 'D', 0, 500, 0, 1)", "(3000000014, 'D', 1, 1000, 0, 1)",
        "(3000000021, 'A', 0, 500, 0, 1)", "(3000000031, 'D', 0, 500, 1, 1)", "(3000000032, 'D', 0, 500, 2, 1)", "(3000000033, 'D', 0, 500, 3, 1)",
    };
    for (unsigned i = 0; i < sizeof(rows) / sizeof(rows[0]); ++i) {
        run(QString("INSERT OR REPLACE INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES ") + QString::fromLatin1(rows[i]));
    }
}

void Smart210Test::aScannedTicketIsCheckedAtOnce()
{
    setUpSelling();
    addStockTickets();
    QSqlDatabase db = QSqlDatabase::database(connection_);

    const TicketSale::Scan ok = TicketSale::scan(db, "0000003000000011", QString());
    QVERIFY(ok.ok);
    QCOMPARE(ok.fuelType, QString("D"));
    QCOMPARE(ok.kgX100, qlonglong(500));
    QVERIFY(TicketSale::scan(db, " 0000003000000014 ", "D").ok); // spaces from the scanner are ignored

    QVERIFY(TicketSale::scan(db, "123", QString()).error.contains("16 digits"));
    QVERIFY(TicketSale::scan(db, "000000300000001x", QString()).error.contains("16 digits"));
    QVERIFY(TicketSale::scan(db, "0000003000000099", QString()).error.contains("not known"));
    QVERIFY(TicketSale::scan(db, "0000003000000031", QString()).error.contains("already sold"));
    QVERIFY(TicketSale::scan(db, "0000003000000032", QString()).error.contains("already used"));
    QVERIFY(TicketSale::scan(db, "0000003000000033", QString()).error.contains("restricted"));
    QVERIFY(TicketSale::scan(db, "0000003000000021", "D").error.contains("another fuel type")); // Gasoline among Diesel
    QVERIFY(TicketSale::scan(db, "0000003000000021", QString()).ok);                              // but fine as the first one
}

static TicketSale::Input ticketInput(const QList<qlonglong> &barcodes, int customer = 2)
{
    TicketSale::Input input;
    input.usage = "sale";
    input.fuelType = "D";
    input.customerId = customer;
    input.priceX100 = 750;
    input.barcodes = barcodes;

    return input;
}

void Smart210Test::aTicketSaleSellsTheTicketsAndWritesTheSale()
{
    setUpSelling();
    addStockTickets();
    QSqlDatabase db = QSqlDatabase::database(connection_);

    const TicketSale::Result result = TicketSale::create(db, ticketInput(QList<qlonglong>() << 3000000011LL << 3000000012LL << 3000000014LL), 7,
                                                         QDateTime(QDate(2026, 10, 7), QTime(10, 0, 0)));

    QVERIFY2(result.ok, qPrintable(result.error));
    QCOMPARE(result.tickets, 3);
    QCOMPARE(result.kgX100, qlonglong(2000)); // 5 + 5 + 10 kg
    QCOMPARE(result.totalX100, qlonglong(15000));

    QSqlQuery query(db);
    QVERIFY(query.exec("SELECT id, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, unpaid_amount, usage, sale_date, user_id, synced FROM sales"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toString(), result.saleId);
    QCOMPARE(query.value(1).toString(), QString("ticket"));
    QCOMPARE(query.value(4).toLongLong(), qlonglong(2000));
    QCOMPARE(query.value(5).toString(), QString("cash"));
    QCOMPARE(query.value(6).toLongLong(), qlonglong(15000));
    QCOMPARE(query.value(7).toLongLong(), qlonglong(15000)); // paid in full
    QCOMPARE(query.value(8).toLongLong(), qlonglong(0));
    QVERIFY(QDate::fromString(query.value(10).toString(), "yyyy-MM-dd").isValid()); // the date is filled in at once (the business day of the moment)
    QCOMPARE(query.value(12).toInt(), 0);

    QVERIFY(query.exec("SELECT barcode, status, sale_id, sale_date, synced FROM tickets WHERE sale_id IS NOT NULL ORDER BY barcode"));
    QStringList sold;
    while (query.next()) {
        sold << QString::number(query.value(0).toLongLong());
        QCOMPARE(query.value(1).toInt(), 1);
        QCOMPARE(query.value(2).toString(), result.saleId);
        QCOMPARE(query.value(3).toString(), QString("2026-10-07 10:00:00"));
        QCOMPARE(query.value(4).toInt(), 0);
    }
    QCOMPARE(sold, QStringList() << "3000000011" << "3000000012" << "3000000014");

    // the rest of the stock was not touched
    QVERIFY(query.exec("SELECT status, synced FROM tickets WHERE barcode = 3000000013"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toInt(), 0);
    QCOMPARE(query.value(1).toInt(), 1);
    QCOMPARE(Uploader::pendingTotal(db), 4); // the sale and its three tickets
}

void Smart210Test::ticketSalesOfOwnUseAndLoanHaveNoAmountAndALoanMakesAnEntry()
{
    setUpSelling();
    addStockTickets();
    QSqlDatabase db = QSqlDatabase::database(connection_);

    TicketSale::Input own = ticketInput(QList<qlonglong>() << 3000000011LL, 1);
    own.usage = "self";
    QVERIFY(TicketSale::create(db, own, 7, QDateTime::currentDateTime()).ok);

    TicketSale::Input loan = ticketInput(QList<qlonglong>() << 3000000012LL << 3000000013LL);
    loan.usage = "ticket_loan";
    const TicketSale::Result result = TicketSale::create(db, loan, 7, QDateTime::currentDateTime());
    QVERIFY(result.ok);
    QCOMPARE(result.totalX100, qlonglong(0));

    QSqlQuery query(db);
    QVERIFY(query.exec("SELECT usage, total_amount, paid_amount FROM sales ORDER BY usage"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toString(), QString("self"));
    QCOMPARE(query.value(1).toLongLong(), qlonglong(0));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toString(), QString("ticket_loan"));
    QVERIFY(query.exec("SELECT amount_kg, sale_id, settled_amount_kg FROM ticket_loans"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toLongLong(), qlonglong(1000));
    QCOMPARE(query.value(1).toString(), result.saleId);
    QCOMPARE(query.value(2).toLongLong(), qlonglong(0));
    QVERIFY(!query.next());
}

void Smart210Test::aTicketSaleThatIsNotAllowedChangesNothing()
{
    setUpSelling();
    addStockTickets();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const QDateTime now = QDateTime::currentDateTime();
    const QList<qlonglong> two = QList<qlonglong>() << 3000000011LL << 3000000012LL;

    QVERIFY(!TicketSale::create(db, ticketInput(two), 0, now).ok);                                    // nobody logged in
    QVERIFY(!TicketSale::create(db, ticketInput(QList<qlonglong>()), 7, now).ok);                      // nothing scanned
    QVERIFY(!TicketSale::create(db, ticketInput(two, 99), 7, now).ok);                                 // no such customer
    QVERIFY(!TicketSale::create(db, ticketInput(two, 3), 7, now).ok);                                  // a customer deleted on the web
    TicketSale::Input input = ticketInput(two); input.priceX100 = 0;                                   // a sale needs a price
    QVERIFY(!TicketSale::create(db, input, 7, now).ok);
    input = ticketInput(two); input.usage = "gift";
    QVERIFY(!TicketSale::create(db, input, 7, now).ok);
    input = ticketInput(two); input.fuelType = "A";                                                    // the tickets are Diesel
    QVERIFY(!TicketSale::create(db, input, 7, now).ok);

    // one ticket in the list was sold in the meantime: the whole sale is refused, the good ticket stays in stock
    const TicketSale::Result stale = TicketSale::create(db, ticketInput(QList<qlonglong>() << 3000000011LL << 3000000031LL), 7, now);
    QVERIFY(!stale.ok);
    QVERIFY(stale.error.contains("0000003000000031"));
    QVERIFY(stale.error.contains("already sold"));
    // the same ticket twice, and two fuel types
    QVERIFY(!TicketSale::create(db, ticketInput(QList<qlonglong>() << 3000000011LL << 3000000011LL), 7, now).ok);
    QVERIFY(!TicketSale::create(db, ticketInput(QList<qlonglong>() << 3000000011LL << 3000000021LL), 7, now).ok);

    QCOMPARE(countRows("sales"), 0);
    QSqlQuery query(db);
    QVERIFY(query.exec("SELECT COUNT(*) FROM tickets WHERE status = 0 AND synced = 1"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toInt(), 5); // all five stock tickets are as they were
    QVERIFY(TicketSale::create(db, ticketInput(two), 7, now).ok); // and a good sale still works
}

void Smart210Test::aTicketSaleCanBeUploadedAsItIs()
{
    configureServer();
    setUpSelling();
    addStockTickets();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    TicketSale::Input input = ticketInput(QList<qlonglong>() << 3000000011LL << 3000000012LL);
    TicketSale::Line line; // the item as entered
    line.spec = 0; line.quantity = 2; line.start = 11; line.end = 12; line.kgX100 = 1000;
    input.lines << line;
    const TicketSale::Result result = TicketSale::create(db, input, 7, QDateTime(QDate(2026, 10, 7), QTime(10, 0, 0)));
    QVERIFY(result.ok);
    QCOMPARE(countRows("sale_items"), 1);
    server_->reply("/api/push/sales", 200, "{\"status\":\"ok\",\"saved\":[\"" + result.saleId.toUtf8() + "\"],\"rejected\":[]}");
    server_->reply("/api/push/tickets", 200, q("{'status':'ok','saved':['0000003000000011','0000003000000012'],'rejected':[]}"));
    Uploader uploader(db);
    QString message;

    QVERIFY(uploadOk(uploader, &message));

    QCOMPARE(server_->log(), QStringList() << "POST /api/push/sales" << "POST /api/push/tickets");
    const QJsonObject sale = QJsonDocument::fromJson(server_->requests().at(0).body).object().value("rows").toArray().at(0).toObject();
    QCOMPARE(sale.value("sale_type").toString(), QString("ticket"));
    QCOMPARE(sale.value("amount_kg").toString(), QString("10.00"));
    QCOMPARE(sale.value("total_amount").toString(), QString("75.00"));
    // the items go with the sale, not as a table of their own (nothing waits for them separately)
    const QJsonArray items = sale.value("items").toArray();
    QCOMPARE(items.size(), 1);
    QCOMPARE(items.at(0).toObject().value("spec").toInt(), 0);
    QCOMPARE(items.at(0).toObject().value("start_serial").toInt(), 11);
    QCOMPARE(items.at(0).toObject().value("end_serial").toInt(), 12);
    QCOMPARE(items.at(0).toObject().value("quantity").toInt(), 2);
    QCOMPARE(items.at(0).toObject().value("amount_kg").toString(), QString("10.00"));
    const QJsonArray tickets = QJsonDocument::fromJson(server_->requests().at(1).body).object().value("rows").toArray();
    QCOMPARE(tickets.size(), 2);
    QCOMPARE(tickets.at(0).toObject().value("barcode").toString(), QString("0000003000000011"));
    QCOMPARE(tickets.at(0).toObject().value("status").toInt(), 1);
    QCOMPARE(tickets.at(0).toObject().value("sale_id").toString(), result.saleId);
    QCOMPARE(tickets.at(0).toObject().value("sale_date").toString(), QString("2026-10-07 10:00:00"));
    QCOMPARE(Uploader::pendingTotal(db), 0);
}

// ---- the ticket sale screen ----

static void typeAndEnter(QLineEdit *field, const QString &text)
{
    field->clear();
    QTest::keyClicks(field, text);
    QTest::keyClick(field, Qt::Key_Return);
}

// the combos of the screen report a choice of the cashier with `activated`
static void choose(QComboBox *combo, const QVariant &data)
{
    combo->setCurrentIndex(combo->findData(data));
    QMetaObject::invokeMethod(combo, "activated", Qt::DirectConnection, Q_ARG(int, combo->currentIndex()));
}

static void typeInto(QLineEdit *field, const QString &text)
{
    field->clear();
    QTest::keyClicks(field, text);
}

void Smart210Test::theItemsOfASaleTakeTheirSerialsLikeOnTheWeb()
{
    setUpSelling();
    addStockTickets();
    QSqlDatabase db = QSqlDatabase::database(connection_);

    // the specs of Diesel in stock: 5 kg (three tickets) and 10 kg (one); the sold, used and restricted ones are not in stock
    const QList<TicketSale::SpecStock> specs = TicketSale::specs(db, "D");
    QCOMPARE(specs.size(), 2);
    QCOMPARE(specs.at(0).spec, 0);
    QCOMPARE(specs.at(0).kgX100, qlonglong(500));
    QCOMPARE(specs.at(0).count, 3);
    QCOMPARE(specs.at(1).count, 1);

    QList<TicketSale::Stock> inStock = TicketSale::stock(db, "D", 0);
    QCOMPARE(inStock.size(), 3);
    QCOMPARE(inStock.first().serial(), qlonglong(11));

    // no start serial: the lowest ones; a start serial: that run
    TicketSale::Taken lowest = TicketSale::take(&inStock, 2, 0);
    QVERIFY(lowest.ok);
    QCOMPARE(lowest.start, qlonglong(11));
    QCOMPARE(lowest.end, qlonglong(12));
    QCOMPARE(lowest.kgX100, qlonglong(1000));
    QCOMPARE(lowest.barcodes, QList<qlonglong>() << 3000000011LL << 3000000012LL);
    QCOMPARE(inStock.size(), 1);                                  // given once
    QVERIFY(!TicketSale::take(&inStock, 2, 0).ok);                // not enough left
    QVERIFY(!TicketSale::take(&inStock, 1, 12).ok);               // 12 is gone
    QCOMPARE(inStock.size(), 1);                                  // a refusal changes nothing
    QVERIFY(TicketSale::take(&inStock, 1, 13).ok);

    // a gap in the serials: a run must have every ticket (11, 12, 13 and 16 are in stock)
    run("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (3000000016, 'D', 0, 500, 0, 1)");
    inStock = TicketSale::stock(db, "D", 0);
    QVERIFY(!TicketSale::take(&inStock, 3, 12).ok);               // 12, 13, 16 is not a run
    QVERIFY(!TicketSale::take(&inStock, 2, 16).ok);
    QVERIFY(TicketSale::take(&inStock, 3, 11).ok);
    QVERIFY(!TicketSale::take(&inStock, 1, 0).ok == false);       // 16 is still there for the lowest
    QVERIFY(TicketSale::take(&inStock, 0, 0).ok == false);        // nothing is not an item

    // the items of a sale in order: a ticket is given once
    QList<TicketSale::Item> items;
    TicketSale::Item a;
    a.spec = 0; a.quantity = 2;                     // the lowest two: 11, 12
    TicketSale::Item b;
    b.spec = 0; b.quantity = 1; b.start = 12;       // 12 is taken by the item above
    TicketSale::Item c;
    c.spec = 0; c.quantity = 1;                     // the lowest left: 13
    TicketSale::Item d;
    d.spec = 1; d.quantity = 1; d.start = 14;
    items << a << b << c << d;
    QMap<int, QList<TicketSale::Stock> > left;
    const QList<TicketSale::Taken> taken = TicketSale::resolve(db, "D", items, &left);
    QCOMPARE(taken.size(), 4);
    QVERIFY(taken.at(0).ok);
    QVERIFY(!taken.at(1).ok);
    QVERIFY(taken.at(2).ok);
    QCOMPARE(taken.at(2).start, qlonglong(13));
    QVERIFY(taken.at(3).ok);
    QCOMPARE(left.value(0).size(), 1);              // only 16 is left of the 5 kg tickets
    QCOMPARE(left.value(1).size(), 0);
}

void Smart210Test::aReceiptIsKeptWithTheTicketSale()
{
    setUpSelling();
    addStockTickets();
    QSqlDatabase db = QSqlDatabase::database(connection_);

    TicketSale::Input withReceipt = ticketInput(QList<qlonglong>() << 3000000011LL);
    withReceipt.receipt = true;
    QVERIFY(TicketSale::create(db, withReceipt, 7, QDateTime::currentDateTime()).ok);
    QVERIFY(TicketSale::create(db, ticketInput(QList<qlonglong>() << 3000000012LL), 7, QDateTime::currentDateTime()).ok);

    QSqlQuery query(db);
    QVERIFY(query.exec("SELECT receipt FROM sales ORDER BY created_at, rowid"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toString(), QString("1"));
    QVERIFY(query.next());
    QVERIFY(query.value(0).isNull());
}

void Smart210Test::theTicketSaleScreenBuildsTheSaleFromItemsAndSaveSellsThem()
{
    setUpSellingTerminal();
    addStockTickets();
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    QTRY_VERIFY_WITH_TIMEOUT(server_->log().contains("GET /api/pull/ticket_loans?limit=200"), 10000);
    QTest::qWait(300); // the start-up download has finished

    window.showPage("ticket");
    QCOMPARE(window.currentPage(), QString("ticket"));
    QComboBox *fuel = window.findChild<QComboBox *>("tsFuelCombo");
    QComboBox *spec = window.findChild<QComboBox *>("tsSpecCombo");
    QLineEdit *start = window.findChild<QLineEdit *>("tsStartField");
    QLineEdit *count = window.findChild<QLineEdit *>("tsCountField");
    QLineEdit *end = window.findChild<QLineEdit *>("tsEndField");
    QLabel *result = window.findChild<QLabel *>("tsCheckResult");
    QTableWidget *items = window.findChild<QTableWidget *>("tsItemsTable");
    QLabel *totals = window.findChild<QLabel *>("tsTotals");
    QLabel *status = window.findChild<QLabel *>("tsStatus");
    QLineEdit *price = window.findChild<QLineEdit *>("tsPriceField");
    QPushButton *check = window.findChild<QPushButton *>("tsCheckButton");
    QPushButton *add = window.findChild<QPushButton *>("tsAddButton");

    // the screen must fit the 1280 x 720 terminal next to the sidebar, header and footer
    QVERIFY(window.findChild<TicketSalePage *>()->minimumSizeHint().width() <= 1050);
    QVERIFY(window.findChild<TicketSalePage *>()->minimumSizeHint().height() <= 600);
    choose(fuel, "D");
    QCOMPARE(price->text(), QString("7.50"));            // the fuel type gives the price
    QCOMPARE(spec->count(), 2);                          // 5 kg (3) and 10 kg (1)
    QVERIFY(spec->itemText(0).contains("5.00 kg") && spec->itemText(0).contains("(3)"));
    QCOMPARE(start->text(), QString("11"));              // the first serial in stock is filled in
    QCOMPARE(count->text(), QString("1"));
    QCOMPARE(end->text(), QString("11"));
    QVERIFY(end->isReadOnly());

    typeInto(count, "2");
    QCOMPARE(end->text(), QString("12"));                // the end follows from the start and the count
    check->click();
    QCOMPARE(result->text(), QString("Valid: serials 11 to 12 can be sold."));
    add->click();
    QCOMPARE(items->rowCount(), 1);
    QCOMPARE(items->item(0, 0)->text(), QString("5.00 kg"));
    QCOMPARE(items->item(0, 1)->text(), QString("2"));
    QCOMPARE(items->item(0, 2)->text(), QString("0000003000000011")); // the start and the end are full barcodes
    QCOMPARE(items->item(0, 3)->text(), QString("0000003000000012"));
    QCOMPARE(items->item(0, 4)->text(), QString("10.00"));
    QCOMPARE(start->text(), QString("13"));              // the next start is the first one left
    QCOMPARE(totals->text(), QString("2 tickets, 10.00 kg, amount 75.00"));

    // serials that an item above has taken, or that are not all in stock, are not valid and are not added
    typeInto(start, "12");
    typeInto(count, "1");
    check->click();
    QVERIFY(result->text().startsWith("Not valid"));
    add->click();
    QCOMPARE(items->rowCount(), 1);
    typeInto(start, "13");
    typeInto(count, "2");
    QCOMPARE(end->text(), QString("14"));
    check->click();
    QVERIFY(result->text().contains("Not valid: some tickets from 13 to 14"));

    // the 10 kg tickets
    spec->setCurrentIndex(1);
    QMetaObject::invokeMethod(spec, "activated", Qt::DirectConnection, Q_ARG(int, 1));
    QCOMPARE(start->text(), QString("14"));
    count->setText("1");
    add->click();
    QCOMPARE(items->rowCount(), 2);
    QCOMPARE(totals->text(), QString("3 tickets, 20.00 kg, amount 150.00"));

    // one item is taken out again (a double click asks first and then does this) and the others are worked out anew
    QVERIFY(window.findChild<QPushButton *>("tsRemoveButton") == 0);
    window.findChild<TicketSalePage *>()->removeItem(0);
    QCOMPARE(items->rowCount(), 1);
    QCOMPARE(totals->text(), QString("1 tickets, 10.00 kg, amount 75.00"));
    spec->setCurrentIndex(0);
    QMetaObject::invokeMethod(spec, "activated", Qt::DirectConnection, Q_ARG(int, 0));
    QCOMPARE(start->text(), QString("11"));
    typeInto(count, "2");
    add->click();
    QCOMPARE(items->rowCount(), 2);
    QCOMPARE(totals->text(), QString("3 tickets, 20.00 kg, amount 150.00"));

    // no customer yet
    window.findChild<QPushButton *>("saveTicketSaleButton")->click();
    QVERIFY(status->text().contains("customer"));
    QCOMPARE(countRows("sales"), 0);

    QComboBox *customer = window.findChild<QComboBox *>("tsCustomerCombo");
    customer->setCurrentIndex(customer->findText("ACME"));
    price->clear();
    QTest::keyClicks(price, "8");                    // the price changed for this sale
    QCOMPARE(totals->text(), QString("3 tickets, 20.00 kg, amount 160.00"));
    window.findChild<QCheckBox *>("tsReceiptCheck")->setChecked(true);
    window.findChild<QPushButton *>("saveTicketSaleButton")->click();

    QCOMPARE(status->text(), QString("Saved: 3 tickets, 20.00 kg, amount 160.00"));
    QCOMPARE(items->rowCount(), 0);                  // ready for the next customer
    QCOMPARE(customer->currentText(), QString("ACME"));
    QVERIFY(!window.findChild<QCheckBox *>("tsReceiptCheck")->isChecked());
    QSqlQuery query(QSqlDatabase::database(connection_));
    QVERIFY(query.exec("SELECT price, amount_kg, total_amount, user_id, receipt FROM sales"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toLongLong(), qlonglong(800));
    QCOMPARE(query.value(1).toLongLong(), qlonglong(2000));
    QCOMPARE(query.value(2).toLongLong(), qlonglong(16000));
    QCOMPARE(query.value(3).toInt(), 7);
    QCOMPARE(query.value(4).toString(), QString("1"));
    QCOMPARE(one("SELECT COUNT(*) FROM tickets WHERE sale_id IS NOT NULL AND status = 1").toInt(), 3);
    // the items as entered are kept with the sale, in the order they were added: the 10 kg ticket, then the two 5 kg tickets
    QCOMPARE(one("SELECT group_concat(spec || ':' || start_serial || '-' || end_serial || 'x' || quantity || '=' || amount_kg, ' ') FROM sale_items").toString(),
             QString("1:14-14x1=1000 0:11-12x2=1000"));
    QCOMPARE(one("SELECT COUNT(*) FROM sale_items WHERE sale_id IN (SELECT id FROM sales)").toInt(), 2);
    QCOMPARE(one("SELECT group_concat(barcode) FROM (SELECT barcode FROM tickets WHERE sale_id IS NOT NULL ORDER BY barcode)").toString(),
             QString("3000000011,3000000012,3000000014"));

    // the stock has changed: what was sold is not offered again
    QCOMPARE(spec->count(), 1);                      // only the 5 kg ticket 13 is left of Diesel
    QCOMPARE(start->text(), QString("13"));

    window.showPage("spot");
    QCOMPARE(window.currentPage(), QString("spot"));
    QCOMPARE(footerWaiting(window), QString("Waiting for upload: 4")); // the sale and three tickets
}

void Smart210Test::changingTheFuelTypeStartsTheItemsAgain()
{
    setUpSellingTerminal();
    addStockTickets();
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    QTRY_VERIFY_WITH_TIMEOUT(server_->log().contains("GET /api/pull/ticket_loans?limit=200"), 10000);
    QTest::qWait(300);
    window.showPage("ticket");
    QComboBox *fuel = window.findChild<QComboBox *>("tsFuelCombo");
    QLineEdit *price = window.findChild<QLineEdit *>("tsPriceField");
    QLineEdit *start = window.findChild<QLineEdit *>("tsStartField");
    QTableWidget *items = window.findChild<QTableWidget *>("tsItemsTable");

    choose(fuel, "D");
    window.findChild<QPushButton *>("tsAddButton")->click();
    QCOMPARE(items->rowCount(), 1);
    QCOMPARE(price->text(), QString("7.50"));

    choose(fuel, "A");                               // Gasoline: its own price, its own tickets, and no items from Diesel
    QCOMPARE(items->rowCount(), 0);
    QCOMPARE(price->text(), QString("8.00"));
    QCOMPARE(start->text(), QString("21"));
    QCOMPARE(window.findChild<QComboBox *>("tsSpecCombo")->count(), 1);

    window.findChild<QPushButton *>("tsAddButton")->click();
    QCOMPARE(items->rowCount(), 1);
    window.findChild<QPushButton *>("tsClearButton")->click();
    QCOMPARE(items->rowCount(), 0);
}

// ---- deleting a mistaken spot sale ----

static SpotSale::Input spotInput(qlonglong kgX100, const QString &usage = "sale")
{
    SpotSale::Input input;
    input.usage = usage;
    input.fuelType = "D";
    input.customerId = 2;
    input.priceX100 = 750;
    input.kgX100 = kgX100;

    return input;
}

void Smart210Test::aMistakenSpotSaleCanBeDeletedAndNeverGoesToTheServer()
{
    setUpSelling();
    addStockTickets();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const SpotSale::Result wrong = SpotSale::create(db, spotInput(1050), 7, QDateTime(QDate(2026, 10, 7), QTime(9, 0, 0)));
    const SpotSale::Result right = SpotSale::create(db, spotInput(1000), 7, QDateTime(QDate(2026, 10, 7), QTime(9, 1, 0)));
    QVERIFY(wrong.ok && right.ok);
    QCOMPARE(SaleDeletion::blocker(db, wrong.saleId, 7), QString());

    QString error;
    QVERIFY2(SaleDeletion::remove(db, wrong.saleId, 7, QDateTime(QDate(2026, 10, 7), QTime(9, 2, 0)), &error), qPrintable(error));

    QSqlQuery query(db);
    QVERIFY(query.exec("SELECT deleted_at, synced FROM sales WHERE id = '" + wrong.saleId + "'"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toString(), QString("2026-10-07 09:02:00")); // only marked: it stays in the deleted list
    QVERIFY(query.exec("SELECT status, sale_id FROM tickets WHERE sale_id = '" + wrong.saleId + "'"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toInt(), 3);                                  // restricted, and still linked to its sale
    QVERIFY(query.exec("SELECT deleted_at FROM sales WHERE id = '" + right.saleId + "'"));
    QVERIFY(query.next());
    QVERIFY(query.value(0).isNull());                                      // the correct one is untouched

    // the upload sends the correct sale and its ticket, and nothing of the deleted one
    QCOMPARE(Uploader::pendingTotal(db), 2);
    const UploadSummary summary = UploadSummary::compute(db);
    QCOMPARE(summary.sales, 1);
    QCOMPARE(summary.deletedSales, 1);

    // its ticket can never be sold or used, and a second delete is refused
    QVERIFY(TicketSale::scan(db, QString("0000021500000001"), QString()).error.contains("restricted"));
    QVERIFY(!SaleDeletion::remove(db, wrong.saleId, 7, QDateTime::currentDateTime(), &error));
    QVERIFY(error.contains("already deleted"));
    QCOMPARE(SpotSale::create(db, spotInput(500), 7, QDateTime::currentDateTime()).barcode, QString("0000021500000003")); // its number is not reused
}

void Smart210Test::deletedSalesDoNotReachTheServer()
{
    configureServer();
    setUpSelling();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const SpotSale::Result wrong = SpotSale::create(db, spotInput(1050, "ticket_loan"), 7, QDateTime::currentDateTime());
    QString error;
    QVERIFY(SaleDeletion::remove(db, wrong.saleId, 7, QDateTime::currentDateTime(), &error));
    Uploader uploader(db);
    QString message;

    QVERIFY(uploadOk(uploader, &message));

    QCOMPARE(message, QString("Uploaded 0 records."));
    QCOMPARE(server_->requests().size(), 0); // no sale, no ticket, no loan
    QSqlQuery query(db);
    QVERIFY(query.exec("SELECT deleted FROM ticket_loans"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toInt(), 1); // the loan made with it went too
}

void Smart210Test::onlyAnUnmovedSpotSaleOfTheLoggedInCashierMayBeDeleted()
{
    setUpSelling();
    addStockTickets();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const QDateTime now = QDateTime::currentDateTime();

    QVERIFY(SaleDeletion::blocker(db, "no-such-sale", 7).contains("does not exist"));

    // uploaded: final
    const SpotSale::Result uploaded = SpotSale::create(db, spotInput(500), 7, now);
    run("UPDATE sales SET synced = 1 WHERE id = '" + uploaded.saleId + "'");
    QVERIFY(SaleDeletion::blocker(db, uploaded.saleId, 7).contains("already uploaded"));

    // a ticket sale is not deleted on the terminal
    const TicketSale::Result ticket = TicketSale::create(db, ticketInput(QList<qlonglong>() << 3000000011LL), 7, now);
    QVERIFY(SaleDeletion::blocker(db, ticket.saleId, 7).contains("Only a spot sale"));

    // another cashier's sale, and nobody logged in
    const SpotSale::Result mine = SpotSale::create(db, spotInput(500), 7, now);
    QVERIFY(SaleDeletion::blocker(db, mine.saleId, 8).contains("another cashier"));
    QVERIFY(SaleDeletion::blocker(db, mine.saleId, 0).contains("another cashier"));

    // fuel was dispensed for its ticket
    const SpotSale::Result supplied = SpotSale::create(db, spotInput(500), 7, now);
    run("UPDATE tickets SET status = 2 WHERE sale_id = '" + supplied.saleId + "'");
    QVERIFY(SaleDeletion::blocker(db, supplied.saleId, 7).contains("dispensed"));

    // its ticket waits at a nozzle (a sale set directly): the board has its weight as the preset, so the sale stays until the ticket is out of the queue
    const SpotSale::Result queued = SpotSale::create(db, spotInput(500), 7, now);
    const qlonglong queuedBarcode = one("SELECT barcode FROM tickets WHERE sale_id = '" + queued.saleId + "'").toLongLong();
    QVERIFY(SupplyQueue::add(db, 1, QString::number(queuedBarcode).rightJustified(16, '0'), "D", now).ok);
    QVERIFY(SaleDeletion::blocker(db, queued.saleId, 7).contains("waits at nozzle 1"));
    SupplyQueue::clear(db, 1);
    QCOMPARE(SaleDeletion::blocker(db, queued.saleId, 7), QString());

    // a loan that was settled against, here or already counted from the web
    const SpotSale::Result loan = SpotSale::create(db, spotInput(2000, "ticket_loan"), 7, now);
    QCOMPARE(SaleDeletion::blocker(db, loan.saleId, 7), QString());
    run("UPDATE ticket_loans SET settled_amount_kg = 500 WHERE sale_id = '" + loan.saleId + "'");
    QVERIFY(SaleDeletion::blocker(db, loan.saleId, 7).contains("surrendered"));
    run("UPDATE ticket_loans SET settled_amount_kg = 0 WHERE sale_id = '" + loan.saleId + "'");
    run("INSERT INTO ticket_loan_settlements (id, loan_id, ticket_barcode, amount_kg, settled_at, user_id, synced) "
        "SELECT 'set-1', id, 3000000013, 500, '2026-10-07 11:00:00', 7, 0 FROM ticket_loans WHERE sale_id = '" + loan.saleId + "'");
    QVERIFY(SaleDeletion::blocker(db, loan.saleId, 7).contains("surrendered"));

    // a refused delete changes nothing
    QString error;
    QVERIFY(!SaleDeletion::remove(db, supplied.saleId, 7, now, &error));
    QSqlQuery query(db);
    QVERIFY(query.exec("SELECT COUNT(*) FROM sales WHERE deleted_at IS NOT NULL"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toInt(), 0);
}

void Smart210Test::theDeletedSaleIsPurgedWithItsTicketAndLoanAfterThePurgeDays()
{
    setUpSelling();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const SpotSale::Result loan = SpotSale::create(db, spotInput(2000, "ticket_loan"), 7, QDateTime(QDate(2026, 10, 1), QTime(9, 0, 0)));
    QString error;
    QVERIFY(SaleDeletion::remove(db, loan.saleId, 7, QDateTime(QDate(2026, 10, 1), QTime(9, 5, 0)), &error));

    QCOMPARE(Purger::purge(db, 3, QDateTime(QDate(2026, 10, 3), QTime(12, 0, 0))), 0);  // still in the deleted list on the second day
    QVERIFY(Purger::purge(db, 3, QDateTime(QDate(2026, 10, 6), QTime(12, 0, 0))) >= 3); // gone with its ticket and loan on the fifth

    QCOMPARE(countRows("sales"), 0);
    QCOMPARE(countRows("tickets"), 0);
    QCOMPARE(countRows("ticket_loans"), 0);
}

// ---- ticket loan settlement ----

// Loans and tickets customers could hold: sold Diesel tickets 34 and 35 (5 kg each), a sold Gasoline one, plus the stock ones.
void Smart210Test::addLoansAndSoldTickets()
{
    addStockTickets();
    run("INSERT OR REPLACE INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (3000000034, 'D', 0, 500, 1, 1)");
    run("INSERT OR REPLACE INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (3000000035, 'D', 0, 500, 1, 1)");
    run("INSERT OR REPLACE INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (3000000036, 'A', 0, 500, 1, 1)");
    run("INSERT INTO ticket_loans (id, customer_id, fuel_type, amount_kg, settled_amount_kg, loaned_at, deleted, synced) VALUES ('loan-a', 2, 'D', 2000, 0, '2026-10-05 10:00:00', 0, 1)");
    run("INSERT INTO ticket_loans (id, customer_id, fuel_type, amount_kg, settled_amount_kg, loaned_at, deleted, synced) VALUES ('loan-b', 1, 'A', 1000, 400, '2026-10-04 10:00:00', 0, 1)");
    run("INSERT INTO ticket_loans (id, customer_id, fuel_type, amount_kg, settled_amount_kg, loaned_at, deleted, synced) VALUES ('loan-paid', 2, 'D', 1000, 1000, '2026-10-03 10:00:00', 0, 1)");
    run("INSERT INTO ticket_loans (id, customer_id, fuel_type, amount_kg, settled_amount_kg, loaned_at, deleted, synced) VALUES ('loan-gone', 2, 'D', 1000, 0, '2026-10-02 10:00:00', 1, 1)");
}

void Smart210Test::onlyLoansWithABalanceAreOpenOldestFirst()
{
    setUpSelling();
    addLoansAndSoldTickets();

    const QList<LoanSettlement::Loan> loans = LoanSettlement::openLoans(QSqlDatabase::database(connection_));

    QCOMPARE(loans.size(), 2); // not the paid one, not the deleted one
    QCOMPARE(loans.at(0).id, QString("loan-b"));
    QCOMPARE(loans.at(0).customer, QString("Self"));
    QCOMPARE(loans.at(0).fuelType, QString("A"));
    QCOMPARE(loans.at(0).balanceX100(), qlonglong(600));
    QCOMPARE(loans.at(1).id, QString("loan-a"));
    QCOMPARE(loans.at(1).customer, QString("ACME"));
    QCOMPARE(loans.at(1).balanceX100(), qlonglong(2000));
}

void Smart210Test::aSurrenderedTicketMustBeSoldAndOfTheLoansFuel()
{
    setUpSelling();
    addLoansAndSoldTickets();
    QSqlDatabase db = QSqlDatabase::database(connection_);

    const LoanSettlement::Scan ok = LoanSettlement::scan(db, " 0000003000000034 ", "D");
    QVERIFY(ok.ok);
    QCOMPARE(ok.kgX100, qlonglong(500));

    QVERIFY(LoanSettlement::scan(db, "123", "D").error.contains("16 digits"));
    QVERIFY(LoanSettlement::scan(db, "0000003000000099", "D").error.contains("not known"));
    QVERIFY(LoanSettlement::scan(db, "0000003000000011", "D").error.contains("never sold"));      // in stock: free fuel otherwise
    QVERIFY(LoanSettlement::scan(db, "0000003000000032", "D").error.contains("already used"));
    QVERIFY(LoanSettlement::scan(db, "0000003000000033", "D").error.contains("restricted"));
    QVERIFY(LoanSettlement::scan(db, "0000003000000036", "D").error.contains("another fuel type")); // Gasoline for a Diesel loan
    QVERIFY(LoanSettlement::scan(db, "0000003000000036", "A").ok);
}

void Smart210Test::surrenderingTicketsSuppliesThemAndRaisesTheSettledTotal()
{
    setUpSelling();
    addLoansAndSoldTickets();
    QSqlDatabase db = QSqlDatabase::database(connection_);

    const LoanSettlement::Result result = LoanSettlement::create(db, "loan-a", QList<qlonglong>() << 3000000034LL << 3000000035LL, 7,
                                                                 QDateTime(QDate(2026, 10, 7), QTime(11, 0, 0)));

    QVERIFY2(result.ok, qPrintable(result.error));
    QCOMPARE(result.tickets, 2);
    QCOMPARE(result.kgX100, qlonglong(1000));
    QCOMPARE(result.balanceX100, qlonglong(1000)); // 20 kg lent, 10 kg back

    QSqlQuery query(db);
    QVERIFY(query.exec("SELECT loan_id, ticket_barcode, amount_kg, settled_at, date, user_id, synced FROM ticket_loan_settlements ORDER BY ticket_barcode"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toString(), QString("loan-a"));
    QCOMPARE(query.value(1).toLongLong(), qlonglong(3000000034LL));
    QCOMPARE(query.value(2).toLongLong(), qlonglong(500)); // the ticket's own weight
    QCOMPARE(query.value(3).toString(), QString("2026-10-07 11:00:00"));
    QCOMPARE(query.value(4).toString(), QString("2026-10-07")); // the date is filled in at once
    QCOMPARE(query.value(5).toInt(), 7);
    QCOMPARE(query.value(6).toInt(), 0);
    QVERIFY(query.next());
    QVERIFY(!query.next());

    QVERIFY(query.exec("SELECT status, supply_date, supplement_id, synced FROM tickets WHERE barcode = 3000000034"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toInt(), 2);                   // supplied, not at a nozzle
    QCOMPARE(query.value(1).toString(), QString("2026-10-07 11:00:00"));
    QVERIFY(query.value(2).isNull());                       // no supplement
    QCOMPARE(query.value(3).toInt(), 0);

    QVERIFY(query.exec("SELECT settled_amount_kg, synced FROM ticket_loans WHERE id = 'loan-a'"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toLongLong(), qlonglong(1000));
    QCOMPARE(query.value(1).toInt(), 1);                    // a loan from the web is not uploaded again
    QCOMPARE(Uploader::pendingTotal(db), 4);                // two settlements and two tickets
    QCOMPARE(LoanSettlement::openLoans(db).at(1).balanceX100(), qlonglong(1000));

    // the rest pays it off, and more than lent is accepted like on the web (the balance goes below zero)
    const LoanSettlement::Result more = LoanSettlement::create(db, "loan-a", QList<qlonglong>() << 3000000031LL << 3000000034LL, 7, QDateTime::currentDateTime());
    QVERIFY(!more.ok); // 34 was already surrendered
    const LoanSettlement::Result last = LoanSettlement::create(db, "loan-a", QList<qlonglong>() << 3000000031LL, 7, QDateTime::currentDateTime());
    QVERIFY(last.ok);
    QCOMPARE(last.balanceX100, qlonglong(500));
}

void Smart210Test::aSettlementThatIsNotAllowedChangesNothing()
{
    setUpSelling();
    addLoansAndSoldTickets();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const QDateTime now = QDateTime::currentDateTime();
    const QList<qlonglong> good = QList<qlonglong>() << 3000000034LL;

    QVERIFY(!LoanSettlement::create(db, "loan-a", good, 0, now).ok);                                   // nobody logged in
    QVERIFY(!LoanSettlement::create(db, "loan-a", QList<qlonglong>(), 7, now).ok);                      // nothing scanned
    QVERIFY(!LoanSettlement::create(db, "no-loan", good, 7, now).ok);
    QVERIFY(!LoanSettlement::create(db, "loan-gone", good, 7, now).ok);                                 // a loan deleted on the web
    QVERIFY(!LoanSettlement::create(db, "loan-a", QList<qlonglong>() << 3000000034LL << 3000000011LL, 7, now).ok); // one ticket never sold
    QVERIFY(!LoanSettlement::create(db, "loan-a", QList<qlonglong>() << 3000000034LL << 3000000034LL, 7, now).ok); // the same twice
    QVERIFY(!LoanSettlement::create(db, "loan-a", QList<qlonglong>() << 3000000034LL << 3000000036LL, 7, now).ok); // another fuel

    QCOMPARE(countRows("ticket_loan_settlements"), 0);
    QSqlQuery query(db);
    QVERIFY(query.exec("SELECT status FROM tickets WHERE barcode = 3000000034"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toInt(), 1); // still sold, not supplied
    QVERIFY(query.exec("SELECT settled_amount_kg FROM ticket_loans WHERE id = 'loan-a'"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toLongLong(), qlonglong(0));
    QVERIFY(LoanSettlement::create(db, "loan-a", good, 7, now).ok); // and a good one still works
}

void Smart210Test::aSettlementCanBeUploadedAsItIs()
{
    configureServer();
    setUpSelling();
    addLoansAndSoldTickets();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    QVERIFY(LoanSettlement::create(db, "loan-a", QList<qlonglong>() << 3000000034LL, 7, QDateTime(QDate(2026, 10, 7), QTime(11, 0, 0))).ok);
    server_->reply("/api/push/ticket_loan_settlements", 200, q("{'status':'ok','saved':[],'rejected':[]}")); // ids are random: take the keys from the request below
    Uploader uploader(db);
    QString message;

    uploadOk(uploader, &message);

    QCOMPARE(server_->log().first(), QString("POST /api/push/ticket_loan_settlements")); // before the tickets
    QVERIFY(server_->log().contains("POST /api/push/tickets"));
    const QJsonObject settlement = QJsonDocument::fromJson(server_->requests().at(0).body).object().value("rows").toArray().at(0).toObject();
    QCOMPARE(settlement.value("loan_id").toString(), QString("loan-a"));
    QCOMPARE(settlement.value("ticket_barcode").toString(), QString("0000003000000034"));
    QCOMPARE(settlement.value("amount_kg").toString(), QString("5.00"));
    QCOMPARE(settlement.value("settled_at").toString(), QString("2026-10-07 11:00:00"));
    QCOMPARE(settlement.value("user_id").toInt(), 7);
    QCOMPARE(settlement.value("id").toString().size(), 36);
    int ticketsRequest = 0;
    for (int i = 0; i < server_->requests().size(); ++i) {
        if (server_->requests().at(i).path == "/api/push/tickets") {
            ticketsRequest = i;
        }
    }
    const QJsonObject ticket = QJsonDocument::fromJson(server_->requests().at(ticketsRequest).body).object().value("rows").toArray().at(0).toObject();
    QCOMPARE(ticket.value("status").toInt(), 2);
    QCOMPARE(ticket.value("supply_date").toString(), QString("2026-10-07 11:00:00"));
    QVERIFY(ticket.value("supplement_id").isNull());
}

void Smart210Test::aDownloadDoesNotUndoASettlementThatIsNotUploadedYet()
{
    QSqlDatabase db = openDatabase();
    setUpSelling();
    addLoansAndSoldTickets();
    SyncStore store(db);
    LoanSettlement::create(db, "loan-a", QList<qlonglong>() << 3000000034LL, 7, QDateTime::currentDateTime());

    // the web app does not know the settlement yet: its total for the loan is still 0
    const SyncStore::PageResult kept = store.applyPage("ticket_loans", rowsOf(
        "[{'id':'loan-a','customer_id':2,'fuel_type':'D','amount_kg':'20.00','settled_amount_kg':'0.00','loaned_at':'2026-10-05 10:00:00','sale_id':null,'deleted':false}]"));
    QCOMPARE(kept.skipped, 1);
    QCOMPARE(kept.keys.size(), 0);
    QSqlQuery query(db);
    QVERIFY(query.exec("SELECT settled_amount_kg FROM ticket_loans WHERE id = 'loan-a'"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toLongLong(), qlonglong(500));

    // after the upload the web app's sum includes it
    run("UPDATE ticket_loan_settlements SET synced = 1");
    const SyncStore::PageResult taken = store.applyPage("ticket_loans", rowsOf(
        "[{'id':'loan-a','customer_id':2,'fuel_type':'D','amount_kg':'20.00','settled_amount_kg':'5.00','loaned_at':'2026-10-05 10:00:00','sale_id':null,'deleted':false}]"));
    QCOMPARE(taken.stored, 1);
    QVERIFY(query.exec("SELECT settled_amount_kg FROM ticket_loans WHERE id = 'loan-a'"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toLongLong(), qlonglong(500));
}


void Smart210Test::theLoanScreenListsTheDebtsAndSettlesTheChosenOneInADialog()
{
    setUpSellingTerminal();
    addLoansAndSoldTickets();
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    QTRY_VERIFY_WITH_TIMEOUT(server_->log().contains("GET /api/pull/ticket_loans?limit=200"), 10000);
    QTest::qWait(300); // the start-up download has finished

    window.showPage("loan");
    QCOMPARE(window.currentPage(), QString("loan"));
    QTableWidget *loans = window.findChild<QTableWidget *>("loansTable");
    QLabel *status = window.findChild<QLabel *>("loanStatus");
    QPushButton *settle = window.findChild<QPushButton *>("loanSettleButton");
    QVERIFY(window.findChild<QLineEdit *>("loanScanField") == 0);       // the scanning is in the dialog
    QVERIFY(!settle->isEnabled());                                      // no debt is chosen
    QVERIFY(loans->rowCount() >= 2);

    // choose the Diesel loan of ACME (loaned 2026-10-05) and press Settle: the dialog scans the tickets
    for (int row = 0; row < loans->rowCount(); ++row) {
        if (loans->item(row, 2)->text() == "2026-10-05 10:00:00") {
            loans->selectRow(row);
            loans->setCurrentCell(row, 0);
        }
    }
    QVERIFY(settle->isEnabled());

    settleTickets_ = QStringList() << "0000003000000034" << "0000003000000034" << "0000003000000011" << "0000003000000036";
    settleNow_ = false;                                                 // look first: Close
    QTimer::singleShot(50, this, SLOT(answerTheSettleDialog()));
    settle->click();
    QVERIFY(settleSeen_.at(1).contains("already in the list"));
    QVERIFY(settleSeen_.at(2).contains("never sold"));
    QVERIFY(settleSeen_.at(3).contains("another fuel type"));
    QCOMPARE(settleTotals_, QString("1 tickets, 5.00 kg, balance after: 15.00 kg"));
    QCOMPARE(countRows("ticket_loan_settlements"), 0);                  // Close settles nothing
    QVERIFY(status->text().isEmpty());

    settleTickets_ = QStringList() << "0000003000000034";
    settleNow_ = true;
    QTimer::singleShot(50, this, SLOT(answerTheSettleDialog()));
    settle->click();

    QCOMPARE(status->text(), QString("Settled 1 tickets, 5.00 kg. Still owed: 15.00 kg"));
    QCOMPARE(countRows("ticket_loan_settlements"), 1);
    QVERIFY(!settle->isEnabled());                                      // the list is new: choose again
    bool found = false;
    for (int row = 0; row < loans->rowCount(); ++row) {
        if (loans->item(row, 2)->text() == "2026-10-05 10:00:00") {
            found = true;
            QCOMPARE(loans->item(row, 4)->text(), QString("5.00"));  // settled
            QCOMPARE(loans->item(row, 5)->text(), QString("15.00")); // balance
        }
    }
    QVERIFY(found);

    window.showPage("spot");
    QCOMPARE(window.currentPage(), QString("spot"));
    QCOMPARE(footerWaiting(window), QString("Waiting for upload: 2")); // the settlement and the ticket
}

// ---- the checkout ----

void Smart210Test::theBusinessDayRunsFromNineToNine()
{
    QCOMPARE(Checkout::businessDay(QDateTime(QDate(2026, 10, 7), QTime(9, 0, 0))), QString("2026-10-07"));
    QCOMPARE(Checkout::businessDay(QDateTime(QDate(2026, 10, 7), QTime(8, 59, 59))), QString("2026-10-06"));
    QCOMPARE(Checkout::businessDay(QDateTime(QDate(2026, 10, 7), QTime(0, 30, 0))), QString("2026-10-06")); // after midnight: the day before
    QCOMPARE(Checkout::businessDay(QDateTime(QDate(2026, 10, 7), QTime(23, 59, 59))), QString("2026-10-07"));
    QCOMPARE(Checkout::businessDay(QDateTime(QDate(2026, 10, 1), QTime(3, 0, 0))), QString("2026-09-30")); // across a month
}

// Records of several times: sales on three business days, a supply, a remainder, a loan and a settlement, plus ones that must be left alone.
void Smart210Test::addRecordsOfSeveralDays()
{
    const QString sale = "INSERT INTO sales (id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id, synced, deleted_at, sale_date) VALUES ";
    run(sale + "('a', '2026-10-06 22:00:00', 'spot', 'D', 750, 1000, 'cash', 7500, 7500, 'sale', 7, 0, NULL, NULL)");  // business day 10-06
    run(sale + "('b', '2026-10-07 03:00:00', 'spot', 'A', 800, 500, 'cash', 4000, 4000, 'sale', 7, 0, NULL, NULL)");   // 10-06 as well (after midnight)
    run(sale + "('c', '2026-10-07 09:00:00', 'spot', 'D', 750, 200, 'cash', 0, 0, 'self', 7, 0, NULL, NULL)");           // 10-07
    run(sale + "('d', '2026-10-07 15:00:00', 'ticket', 'D', 750, 1000, 'cash', 7500, 7500, 'sale', 7, 0, NULL, NULL)");  // 10-07
    run(sale + "('gone', '2026-10-07 10:00:00', 'spot', 'D', 750, 999, 'cash', 1, 1, 'sale', 7, 0, '2026-10-07 10:05:00', NULL)"); // deleted: never stamped
    run(sale + "('sent', '2026-10-05 10:00:00', 'spot', 'D', 750, 999, 'cash', 1, 1, 'sale', 7, 1, NULL, NULL)");         // uploaded: not ours to stamp
    run(sale + "('done', '2026-10-05 11:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 7, 0, NULL, '2026-10-05')"); // has its date
    run("INSERT INTO supplements (id, fuel_type, nozzle_id, out_amount_lt, out_amount_kg, preset_kg, supplied_at, synced) VALUES ('p', 'D', 2, 1265, 1050, 1050, '2026-10-07 10:00:00', 0)");
    run("INSERT INTO remainders (id, supplement_id, ticket_barcode, fuel_type, amount_kg, created_at, synced) VALUES ('r', 'p', 21500000001, 'D', 250, '2026-10-07 10:01:00', 0)");
    run("INSERT INTO ticket_loans (id, customer_id, fuel_type, amount_kg, loaned_at, deleted, synced) VALUES ('l', 2, 'D', 2000, '2026-10-06 12:00:00', 0, 0)");
    run("INSERT INTO ticket_loans (id, customer_id, fuel_type, amount_kg, loaned_at, deleted, synced) VALUES ('pulled', 2, 'D', 3000, '2026-10-01 12:00:00', 0, 1)"); // from the web
    run("INSERT INTO ticket_loan_settlements (id, loan_id, ticket_barcode, amount_kg, settled_at, user_id, synced) VALUES ('s', 'l', 3000000034, 500, '2026-10-07 11:00:00', 7, 0)");
}

static QStringList datesOf(QSqlDatabase db, const QString &table)
{
    QStringList list;
    QSqlQuery query(db);
    const QString column = table == "sales" ? "sale_date" : (table == "supplements" ? "supply_date" : (table == "remainders" ? "remainder_date" : "date"));
    query.exec("SELECT id || '=' || COALESCE(" + column + ", '-') FROM " + table + " ORDER BY id");
    while (query.next()) {
        list << query.value(0).toString();
    }

    return list;
}

void Smart210Test::theCheckoutStampsOpenRecordsByTheirOwnDay()
{
    QSqlDatabase db = openDatabase();
    addRecordsOfSeveralDays();
    QCOMPARE(Checkout::openRecords(db), 8); // sales a, b, c, d; the supply, remainder, loan and settlement

    const Checkout::Run run = Checkout::run(db, QDateTime(QDate(2026, 10, 7), QTime(18, 0, 0)), "manual", 7, true);

    QCOMPARE(run.records, 8);
    QCOMPARE(run.dates, QStringList() << "2026-10-06" << "2026-10-07");
    QCOMPARE(datesOf(db, "sales"), QStringList() << "a=2026-10-06" << "b=2026-10-06" << "c=2026-10-07" << "d=2026-10-07"
                                                 << "done=2026-10-05" << "gone=-" << "sent=-"); // deleted and uploaded ones stay as they were
    QCOMPARE(datesOf(db, "supplements"), QStringList() << "p=2026-10-07");
    QCOMPARE(datesOf(db, "remainders"), QStringList() << "r=2026-10-07");
    QCOMPARE(datesOf(db, "ticket_loans"), QStringList() << "l=2026-10-06" << "pulled=-"); // a loan from the web is not stamped
    QCOMPARE(datesOf(db, "ticket_loan_settlements"), QStringList() << "s=2026-10-07");

    QSqlQuery log(db);
    QVERIFY(log.exec("SELECT date, mode, user_id, records, performed_at FROM checkouts ORDER BY date"));
    QVERIFY(log.next());
    QCOMPARE(log.value(0).toString(), QString("2026-10-06"));
    QCOMPARE(log.value(1).toString(), QString("manual"));
    QCOMPARE(log.value(2).toInt(), 7);
    QCOMPARE(log.value(3).toInt(), 3);                          // a, b and the loan
    QCOMPARE(log.value(4).toString(), QString("2026-10-07 18:00:00"));
    QVERIFY(log.next());
    QCOMPARE(log.value(3).toInt(), 5);

    // a second run finds nothing
    QCOMPARE(Checkout::run(db, QDateTime(QDate(2026, 10, 7), QTime(19, 0, 0)), "manual", 7, true).records, 0);
    QCOMPARE(countRows("checkouts"), 2);
    QCOMPARE(Checkout::openRecords(db), 0);
}

void Smart210Test::theAutomaticCheckoutOnlyClosesBusinessDaysThatAreOver()
{
    QSqlDatabase db = openDatabase();
    addRecordsOfSeveralDays();

    // at noon on 10-07 the business day 10-07 is still running
    QCOMPARE(Checkout::overdueRecords(db, QDateTime(QDate(2026, 10, 7), QTime(12, 0, 0))), 3); // a, b and the loan
    Checkout::Run first = Checkout::run(db, QDateTime(QDate(2026, 10, 7), QTime(12, 0, 0)), "auto", 0, false);
    QCOMPARE(first.records, 3);
    QCOMPARE(first.dates, QStringList() << "2026-10-06");
    QCOMPARE(Checkout::openRecords(db), 5); // the 10-07 records are still open
    QSqlQuery log(db);
    QVERIFY(log.exec("SELECT mode, user_id FROM checkouts"));
    QVERIFY(log.next());
    QCOMPARE(log.value(0).toString(), QString("auto"));
    QVERIFY(log.value(1).isNull()); // nobody pressed it

    // the terminal was off at 9 AM and is switched on at 14:00 on 10-08: the catch-up closes 10-07
    QCOMPARE(Checkout::overdueRecords(db, QDateTime(QDate(2026, 10, 8), QTime(14, 0, 0))), 5);
    Checkout::Run catchUp = Checkout::run(db, QDateTime(QDate(2026, 10, 8), QTime(14, 0, 0)), "auto", 0, false);
    QCOMPARE(catchUp.records, 5);
    QCOMPARE(catchUp.dates, QStringList() << "2026-10-07");
    QCOMPARE(Checkout::openRecords(db), 0);
}

void Smart210Test::theCheckoutReportAddsUpTheDay()
{
    QSqlDatabase db = openDatabase();
    addRecordsOfSeveralDays();
    Checkout::run(db, QDateTime(QDate(2026, 10, 7), QTime(18, 0, 0)), "manual", 7, true);

    const Checkout::Report r = Checkout::report(db, "2026-10-07");

    QCOMPARE(r.sales, 2);                       // c and d (not the deleted one)
    QCOMPARE(r.salesKgX100, qlonglong(1200));
    QCOMPARE(r.salesAmountX100, qlonglong(7500));
    QCOMPARE(r.salesPaidX100, qlonglong(7500));
    QCOMPARE(r.byUsage.size(), 2);
    QCOMPARE(r.byUsage.at(0).label, QString("sale"));
    QCOMPARE(r.byUsage.at(0).kgX100, qlonglong(1000));
    QCOMPARE(r.byUsage.at(1).label, QString("self"));
    QCOMPARE(r.byUsage.at(1).amountX100, qlonglong(0));
    QCOMPARE(r.byFuel.size(), 1);
    QCOMPARE(r.byFuel.at(0).label, QString("D"));
    QCOMPARE(r.supplements, 1);
    QCOMPARE(r.litersX100, qlonglong(1265));
    QCOMPARE(r.supplementKgX100, qlonglong(1050));
    QCOMPARE(r.remainders, 1);
    QCOMPARE(r.remainderKgX100, qlonglong(250));
    QCOMPARE(r.settlements, 1);
    QCOMPARE(r.settlementKgX100, qlonglong(500));
    QCOMPARE(r.loans, 0);

    const Checkout::Report before = Checkout::report(db, "2026-10-06");
    QCOMPARE(before.sales, 2);
    QCOMPARE(before.byFuel.size(), 2);
    QCOMPARE(before.loans, 1);
    QCOMPARE(before.loanKgX100, qlonglong(2000));

    QCOMPARE(Checkout::days(db), QStringList() << "2026-10-07" << "2026-10-06" << "2026-10-05"); // newest first, 10-05 was checked out before
}

void Smart210Test::aCheckedOutDayCanBeMovedByOneDayWithinADay()
{
    QSqlDatabase db = openDatabase();
    addRecordsOfSeveralDays();
    Checkout::run(db, QDateTime(QDate(2026, 10, 7), QTime(18, 0, 0)), "manual", 7, true);
    const QDateTime soon(QDate(2026, 10, 7), QTime(20, 0, 0));
    QString error;

    QCOMPARE(Checkout::shiftBlocker(db, "2026-10-07", soon), QString());
    QVERIFY(!Checkout::shift(db, "2026-10-07", 2, soon, &error));   // only one day at a time
    QVERIFY(Checkout::shift(db, "2026-10-07", -1, soon, &error));

    QCOMPARE(Checkout::report(db, "2026-10-07").sales, 0);
    QCOMPARE(Checkout::report(db, "2026-10-06").sales, 4);            // merged with what was there
    QCOMPARE(Checkout::days(db), QStringList() << "2026-10-06" << "2026-10-05");
    QSqlQuery query(db);
    QVERIFY(query.exec("SELECT COUNT(*) FROM checkouts WHERE date = '2026-10-07'"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toInt(), 0); // the log moved with it
    QVERIFY(Checkout::shift(db, "2026-10-06", 1, soon, &error)); // and back
    QCOMPARE(Checkout::report(db, "2026-10-07").sales, 4);
}

void Smart210Test::aDayCannotBeMovedAfterADayOrOnceAnyRecordIsUploaded()
{
    QSqlDatabase db = openDatabase();
    addRecordsOfSeveralDays();
    Checkout::run(db, QDateTime(QDate(2026, 10, 7), QTime(18, 0, 0)), "manual", 7, true);
    QString error;

    // the next day, after 24 hours
    const QDateTime late(QDate(2026, 10, 8), QTime(18, 1, 0));
    QVERIFY(Checkout::shiftBlocker(db, "2026-10-07", late).contains("one day"));
    QVERIFY(!Checkout::shift(db, "2026-10-07", 1, late, &error));
    QCOMPARE(Checkout::report(db, "2026-10-07").sales, 2);

    // a record of the day was uploaded
    const QDateTime soon(QDate(2026, 10, 7), QTime(20, 0, 0));
    run("UPDATE sales SET synced = 1 WHERE id = 'c'");
    QVERIFY(Checkout::shiftBlocker(db, "2026-10-07", soon).contains("uploaded"));
    QVERIFY(!Checkout::shift(db, "2026-10-07", -1, soon, &error));

    // a day nobody checked out here
    QVERIFY(Checkout::shiftBlocker(db, "2026-10-05", soon).contains("not checked out"));
}

void Smart210Test::theUploadChecksOutWhatIsStillOpen()
{
    setUpSellingTerminal();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    SpotSale::Input input;
    input.usage = "sale"; input.fuelType = "D"; input.customerId = 2; input.priceX100 = 750; input.kgX100 = 1050;
    const SpotSale::Result made = SpotSale::create(db, input, 7, QDateTime::currentDateTime());
    QVERIFY(made.ok);
    run("UPDATE sales SET sale_date = NULL"); // a record of an older version of the program, made before the date was filled in at once
    server_->reply("/api/push/sales", 200, "{\"status\":\"ok\",\"saved\":[\"" + made.saleId.toUtf8() + "\"],\"rejected\":[]}");
    server_->reply("/api/push/tickets", 200, q("{'status':'ok','saved':['0000021500000001'],'rejected':[]}"));
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", db);
    QTRY_VERIFY_WITH_TIMEOUT(server_->log().contains("GET /api/pull/ticket_loans?limit=200"), 10000);
    QTest::qWait(300);
    QVERIFY(Checkout::openRecords(db) >= 1);

    window.showPage("upload");
    window.findChild<QPushButton *>("uploadButton")->click();

    QTRY_VERIFY_WITH_TIMEOUT(server_->log().contains("POST /api/push/sales"), 10000);
    int salesRequest = 0;
    for (int i = 0; i < server_->requests().size(); ++i) {
        if (server_->requests().at(i).path == "/api/push/sales") {
            salesRequest = i;
        }
    }
    const QJsonObject sale = QJsonDocument::fromJson(server_->requests().at(salesRequest).body).object().value("rows").toArray().at(0).toObject();
    QCOMPARE(sale.value("sale_date").toString(), Checkout::businessDay(QDateTime::currentDateTime())); // never uploaded without it
    QSqlQuery log(db);
    QVERIFY(log.exec("SELECT mode, user_id FROM checkouts"));
    QVERIFY(log.next());
    QCOMPARE(log.value(0).toString(), QString("upload"));
    QCOMPARE(log.value(1).toInt(), 7);
}

void Smart210Test::theTerminalClosesOverdueDaysByItselfAtStart()
{
    setUpSellingTerminal();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const QDateTime now = QDateTime::currentDateTime();
    run("INSERT INTO sales (id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id, synced) "
        "VALUES ('old', '" + now.addDays(-3).toString("yyyy-MM-dd HH:mm:ss") + "', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 7, 0)");
    run("INSERT INTO sales (id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id, synced) "
        "VALUES ('now', '" + now.toString("yyyy-MM-dd HH:mm:ss") + "', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 7, 0)");

    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", db);

    QSqlQuery query(db);
    QVERIFY(query.exec("SELECT id, sale_date FROM sales ORDER BY id"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toString(), QString("now"));
    QVERIFY(query.value(1).isNull());                                   // the running business day stays open
    QVERIFY(query.next());
    QCOMPARE(query.value(1).toString(), Checkout::businessDay(now.addDays(-3))); // the catch-up closed the old one
    QVERIFY(query.exec("SELECT mode FROM checkouts"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toString(), QString("auto"));
}

// ---- printing ----

void Smart210Test::theEscPosCommandsAreTheRightBytes()
{
    QCOMPARE(EscPos::initialize(), QByteArray("\x1B\x40", 2));
    QCOMPARE(EscPos::align(EscPos::Center), QByteArray("\x1B\x61\x01", 3));
    QCOMPARE(EscPos::feed(3), QByteArray("\x1B\x64\x03", 3));
    QCOMPARE(EscPos::cut(), QByteArray("\x1D\x56\x42\x03", 4));
    QCOMPARE(EscPos::labelFeed(), QByteArray("\x0C", 1));
    QCOMPARE(EscPos::statusRequest(), QByteArray("\x10\x04\x01\x10\x04\x02\x10\x04\x04", 9));
}

void Smart210Test::theBarcodeIsNativeCode128SubsetC()
{
    // 16 digits are 8 symbols of two digits: 00 00 02 15 00 00 00 01, after the "{C" that selects subset C
    const QByteArray expected = QByteArray("\x1D\x48\x02", 3) + QByteArray("\x1D\x68\x50", 3) + QByteArray("\x1D\x77\x03", 3)
        + QByteArray("\x1D\x6B\x49\x0A", 4) + QByteArray("{C", 2) + QByteArray("\x00\x00\x02\x0F\x00\x00\x00\x01", 8);

    QCOMPARE(EscPos::barcode128C("0000021500000001", 3, 80), expected);

    // the largest symbol is 99
    QVERIFY(EscPos::barcode128C("9900", 2, 60).endsWith(QByteArray("{C", 2) + QByteArray("\x63\x00", 2)));
    // the module is kept between 2 and 6 dots
    QVERIFY(EscPos::barcode128C("0000", 9, 60).contains(QByteArray("\x1D\x77\x06", 3)));
    QVERIFY(EscPos::barcode128C("0000", 1, 60).contains(QByteArray("\x1D\x77\x02", 3)));
}

// The bars of a ticket as a bitmap: Start C, one symbol for two digits, the check symbol and Stop. Every symbol is 11 modules wide, the stop 13.
void Smart210Test::theBarcodeOfATicketIsDrawnAsCode128CWithItsCheckSymbol()
{
    // "1234": symbols 12 and 34; check = (105 + 12 * 1 + 34 * 2) % 103 = 82; Start C is 211232, Stop 2331112
    const QList<int> widths = TicketRenderer::barcodeModules("1234");
    int modules = 0;
    foreach (int width, widths) {
        modules += width;
    }
    QCOMPARE(widths.size(), 6 * 4 + 7);                              // start, two symbols, the check and the stop
    QCOMPARE(modules, 11 * 4 + 13);
    QCOMPARE(widths.mid(0, 6), QList<int>() << 2 << 1 << 1 << 2 << 3 << 2);               // Start C: 211232
    QCOMPARE(widths.mid(6, 6), QList<int>() << 1 << 1 << 2 << 2 << 3 << 2);               // 12: 112232
    QCOMPARE(widths.mid(12, 6), QList<int>() << 1 << 3 << 1 << 1 << 2 << 3);              // 34: 131123
    QCOMPARE(widths.mid(18, 6), QList<int>() << 1 << 2 << 1 << 2 << 4 << 1);              // the check symbol 82: 121241
    QCOMPARE(widths.mid(widths.size() - 7), QList<int>() << 2 << 3 << 3 << 1 << 1 << 1 << 2);   // Stop: 2331112
    QVERIFY(TicketRenderer::barcodeModules("123").isEmpty());          // an odd number of digits
    QVERIFY(TicketRenderer::barcodeModules("12a4").isEmpty());
    QVERIFY(TicketRenderer::barcodeModules("").isEmpty());

    // a ticket: 8 symbols and the start and the check: 10 * 11 + 13 modules; the picture has bars in it and the digits under them
    const QFont font = TicketRenderer::fontFor(PrintOptions());
    const QList<int> ticket = TicketRenderer::barcodeModules("0000021500000001");
    int ticketModules = 0;
    foreach (int width, ticket) {
        ticketModules += width;
    }
    QCOMPARE(ticketModules, 10 * 11 + 13);
    const QImage image = TicketRenderer::barcodeImage("0000021500000001", 3, 80, font, 576);
    QCOMPARE(image.width(), 576);
    QVERIFY(image.height() > 80);
    int dark = 0;
    for (int y = 4; y < 4 + 80; ++y) {                                // inside the bars: a dark column every few dots
        for (int x = 0; x < image.width(); ++x) {
            if (qGray(image.pixel(x, y)) < 128) {
                ++dark;
            }
        }
    }
    QVERIFY(dark > 80 * 3 * 20);                                      // at least 20 modules of black in 80 rows
    bool textBelow = false;
    for (int y = 4 + 80 + 2; y < image.height() && !textBelow; ++y) {
        for (int x = 0; x < image.width(); ++x) {
            if (qGray(image.pixel(x, y)) < 128) {
                textBelow = true;
                break;
            }
        }
    }
    QVERIFY(textBelow);                                               // the digits are printed under the bars
    QVERIFY(TicketRenderer::barcodeImage("123", 3, 80, font, 576).isNull());

    // paper too narrow for the module: it is made thinner so that the bars fit
    const QImage narrow = TicketRenderer::barcodeImage("0000021500000001", 6, 80, font, 400);
    QCOMPARE(narrow.width(), 400);
}

void Smart210Test::aPictureBecomesBlackAndWhiteRasterRows()
{
    QImage image(10, 2, QImage::Format_RGB32);
    image.fill(Qt::white);
    image.setPixel(0, 0, qRgb(0, 0, 0));   // row 0: the first and the 10th dot black
    image.setPixel(9, 0, qRgb(0, 0, 0));
    image.setPixel(1, 1, qRgb(250, 250, 250)); // nearly white: stays white
    image.setPixel(2, 1, qRgb(20, 20, 20));    // dark: black

    const QByteArray bytes = EscPos::raster(image);

    // GS v 0 m, 2 bytes per row (10 dots round up to 16), 2 rows, then the rows
    QCOMPARE(bytes.left(8), QByteArray("\x1D\x76\x30\x00\x02\x00\x02\x00", 8));
    QCOMPARE(bytes.mid(8), QByteArray("\x80\x40\x20\x00", 4)); // row 0: 1000 0000 | 0100 0000 ; row 1: 0010 0000 | 0000 0000
    QCOMPARE(bytes.size(), 12);
}

void Smart210Test::tallPicturesAreSentInBandsOf128Rows()
{
    QImage image(8, 300, QImage::Format_RGB32);
    image.fill(Qt::black);

    const QByteArray bytes = EscPos::raster(image);

    // three bands: 128 + 128 + 44 rows of one byte, each with its own 8-byte header
    QCOMPARE(bytes.size(), 3 * 8 + 300);
    QCOMPARE(bytes.mid(0, 8), QByteArray("\x1D\x76\x30\x00\x01\x00\x80\x00", 8));
    QCOMPARE(bytes.mid(8 + 128, 8), QByteArray("\x1D\x76\x30\x00\x01\x00\x80\x00", 8));
    QCOMPARE(bytes.mid(2 * (8 + 128), 8), QByteArray("\x1D\x76\x30\x00\x01\x00\x2C\x00", 8));
    QCOMPARE(bytes.mid(8, 128), QByteArray(128, '\xFF'));
}

void Smart210Test::theStatusAnswerSaysWhatIsWrongWithThePrinter()
{
    QCOMPARE(EscPos::statusProblem(QByteArray("\x16\x12\x12", 3)), QString()); // all fine
    QCOMPARE(EscPos::statusProblem(QByteArray()), QString());                  // no answer: nothing known to be wrong
    QVERIFY(EscPos::statusProblem(QByteArray("\x16\x16\x12", 3)).contains("cover"));
    QVERIFY(EscPos::statusProblem(QByteArray("\x16\x12\x72", 3)).contains("paper"));
    QVERIFY(EscPos::statusProblem(QByteArray("\x16\x32\x12", 3)).contains("paper"));
    QVERIFY(EscPos::statusProblem(QByteArray("\x16\x52\x12", 3)).contains("error"));
    QVERIFY(EscPos::statusProblem(QByteArray("\x1E\x12\x12", 3)).contains("offline"));
}

// The stream as a list of command names, so the order on the paper can be checked.
static QStringList commandsOf(const QByteArray &job)
{
    QStringList list;
    int i = 0;
    while (i < job.size()) {
        const unsigned char c = job.at(i);
        if (c == 0x1B && i + 1 < job.size()) {
            const unsigned char k = job.at(i + 1);
            list << (k == 0x40 ? "init" : k == 0x61 ? "align" : "feed");
            i += (k == 0x40) ? 2 : 3;
        } else if (c == 0x1D && i + 1 < job.size()) {
            const unsigned char k = job.at(i + 1);
            if (k == 0x76) { // raster: 8 header bytes and rows x bytes of data
                const int perRow = (unsigned char)job.at(i + 4) | ((unsigned char)job.at(i + 5) << 8);
                const int rows = (unsigned char)job.at(i + 6) | ((unsigned char)job.at(i + 7) << 8);
                list << "raster";
                i += 8 + perRow * rows;
            } else if (k == 0x6B) { // barcode: GS k 73 n data
                list << "barcode";
                i += 4 + (unsigned char)job.at(i + 3);
            } else if (k == 0x56) {
                list << "cut";
                i += 4;
            } else {
                i += 3; // GS H, GS h, GS w
            }
        } else if (c == 0x0C) {
            list << "formfeed";
            ++i;
        } else {
            list << "?";
            ++i;
        }
    }

    return list;
}

static TicketData sampleTicket()
{
    TicketData ticket;
    ticket.title = "Gas Station Fuel Ticket";
    ticket.fuelName = "Diesel";
    ticket.kgX100 = 1050;
    ticket.when = QDateTime(QDate(2026, 10, 7), QTime(9, 30, 0));
    ticket.barcode = "0000021500000001";

    return ticket;
}

void Smart210Test::theTicketHasTheTitleTheDetailsTheBarcodeAndTheLogoInThatOrder()
{
    QTemporaryDir logoDir;
    QImage logo(200, 80, QImage::Format_RGB32);
    logo.fill(Qt::black);
    QVERIFY(logo.save(logoDir.path() + "/logo.png"));
    PrintOptions options;
    options.logoPath = logoDir.path() + "/logo.png";

    const QStringList commands = commandsOf(TicketRenderer::render(sampleTicket(), options));

    // the barcode is a bitmap too (so every printer prints it, a virtual printer included)
    QCOMPARE(commands, QStringList() << "init" << "align" << "raster" << "raster" << "feed" << "raster" << "feed" << "raster" << "cut");

    options.barcodeMode = "printer";                                       // or the printer's own command
    QCOMPARE(commandsOf(TicketRenderer::render(sampleTicket(), options)), QStringList() << "init" << "align" << "raster" << "raster" << "feed" << "barcode" << "feed" << "raster" << "cut");
}

void Smart210Test::withoutALogoThereIsNothingAfterTheBarcode()
{
    PrintOptions options; // no logo file
    options.logoPath = "/no/such/logo.png";

    QCOMPARE(commandsOf(TicketRenderer::render(sampleTicket(), options)),
             QStringList() << "init" << "align" << "raster" << "raster" << "feed" << "raster" << "feed" << "cut");

    options.endMode = "label";
    QVERIFY(commandsOf(TicketRenderer::render(sampleTicket(), options)).last() == "formfeed");
    options.endMode = "none";
    QVERIFY(commandsOf(TicketRenderer::render(sampleTicket(), options)).last() == "feed");
}

void Smart210Test::theTitleShrinksThenWrapsToFitThePaper()
{
    const QFont font = TicketRenderer::fontFor(PrintOptions());

    const QImage shortTitle = TicketRenderer::titleImage("Fuel Ticket", font, 576);
    const QImage longTitle = TicketRenderer::titleImage("Gas Station Liquefied Petroleum Gas Retail Fuel Ticket For Cylinders", font, 576);
    const QImage huge = TicketRenderer::titleImage(QString(200, 'W'), font, 576);

    QCOMPARE(shortTitle.width(), 576);
    QCOMPARE(longTitle.width(), 576); // always as wide as the paper
    QVERIFY(longTitle.height() <= shortTitle.height() + 40); // shrunk to one line or wrapped to two
    QVERIFY(huge.height() > longTitle.height());             // a title that cannot fit wraps to more lines
    QVERIFY(huge.height() < 400);                            // but never beyond the 50 mm of the ticket
    QCOMPARE(TicketRenderer::detailsImage(sampleTicket(), font, 576).width(), 576);

    // something was drawn: not an all-white strip
    bool dark = false;
    for (int x = 0; x < shortTitle.width() && !dark; ++x) {
        for (int y = 0; y < shortTitle.height(); ++y) {
            dark = dark || qGray(shortTitle.pixel(x, y)) < 128;
        }
    }
    // (the offscreen platform of a test run may have no fonts at all; the drawing is then not checked)
    QVERIFY(dark || QFontDatabase().families().isEmpty());
}

void Smart210Test::theLogoIsScaledDownToFit()
{
    QTemporaryDir dir;
    QImage big(1000, 600, QImage::Format_RGB32);
    big.fill(Qt::black);
    QVERIFY(big.save(dir.path() + "/big.png"));
    QImage small(100, 40, QImage::Format_RGB32);
    small.fill(Qt::black);
    QVERIFY(small.save(dir.path() + "/small.png"));

    const QImage scaled = TicketRenderer::logoImage(dir.path() + "/big.png", 576, 100);
    const QImage kept = TicketRenderer::logoImage(dir.path() + "/small.png", 576, 100);

    QCOMPARE(scaled.width(), 576);
    QVERIFY(scaled.height() <= 108);           // 100 dots and the margin
    QCOMPARE(kept.height(), 48);               // a small logo is not made larger
    QVERIFY(TicketRenderer::logoImage(dir.path() + "/missing.png", 576, 100).isNull());
    QVERIFY(TicketRenderer::logoImage(QString(), 576, 100).isNull());
}

void Smart210Test::aSpotSalesTicketIsReadFromTheSale()
{
    setUpSelling();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    PrintOptions options;
    options.titles["self"] = "My Own Self Title";
    PrintService service(db, options);
    const SpotSale::Result sale = SpotSale::create(db, spotInput(1050), 7, QDateTime(QDate(2026, 10, 7), QTime(9, 30, 0)));
    SpotSale::Input own = spotInput(500, "self");
    own.customerId = 1;
    const SpotSale::Result self = SpotSale::create(db, own, 7, QDateTime(QDate(2026, 10, 7), QTime(9, 40, 0)));

    TicketData ticket;
    QString error;
    QVERIFY2(service.ticketOf(sale.saleId, &ticket, &error), qPrintable(error));
    QCOMPARE(ticket.title, QString("Gas Station Fuel Ticket"));  // the default for a sale
    QCOMPARE(ticket.fuelName, QString("Diesel"));                  // the name, not the letter
    QCOMPARE(ticket.kgX100, qlonglong(1050));
    QCOMPARE(ticket.when, QDateTime(QDate(2026, 10, 7), QTime(9, 30, 0)));
    QCOMPARE(ticket.barcode, QString("0000021500000001"));

    QVERIFY(service.ticketOf(self.saleId, &ticket, &error));
    QCOMPARE(ticket.title, QString("My Own Self Title"));         // config.ini overrides the title of a use

    QString deletedError;
    run("UPDATE sales SET deleted_at = '2026-10-07 10:00:00' WHERE id = '" + self.saleId + "'");
    QVERIFY(!service.ticketOf(self.saleId, &ticket, &deletedError)); // a deleted sale prints nothing
    QVERIFY(!service.ticketOf("no-such-sale", &ticket, &deletedError));
}

void Smart210Test::aTicketReachesThePrinterAfterTheStatusCheck()
{
    setUpSelling();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    FakePrinter printer;
    QVERIFY(printer.listen());
    printer.goodStatus();
    Settings(db).setValue(SettingKey::PrinterHost, "127.0.0.1");
    Settings(db).setInt(SettingKey::PrinterPort, printer.port());
    PrintOptions options;
    options.barcodeMode = "printer";                                     // the printer's own barcode command
    PrintService service(db, options);
    const SpotSale::Result sale = SpotSale::create(db, spotInput(1050), 7, QDateTime::currentDateTime());
    QSignalSpy spy(&service, SIGNAL(finished(bool,QString)));

    service.printSaleTicket(sale.saleId);
    QVERIFY(spy.wait(5000));

    QVERIFY2(spy.at(0).at(0).toBool(), qPrintable(spy.at(0).at(1).toString()));
    QTRY_COMPARE_WITH_TIMEOUT(printer.jobs().size(), 1, 3000);
    QVERIFY(printer.jobs().at(0).startsWith(QByteArray("\x1B\x40", 2)));          // the ticket itself, after ESC @
    QVERIFY(printer.jobs().at(0).contains(EscPos::barcode128C("0000021500000001", 3, 80)));
}

void Smart210Test::aPrinterThatReportsAProblemGetsNoJob()
{
    setUpSelling();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    FakePrinter printer;
    QVERIFY(printer.listen());
    printer.setStatus(QByteArray("\x16\x12\x72", 3)); // out of paper
    Settings(db).setValue(SettingKey::PrinterHost, "127.0.0.1");
    Settings(db).setInt(SettingKey::PrinterPort, printer.port());
    PrintService service(db, PrintOptions());
    const SpotSale::Result sale = SpotSale::create(db, spotInput(1050), 7, QDateTime::currentDateTime());
    QSignalSpy spy(&service, SIGNAL(finished(bool,QString)));

    service.printSaleTicket(sale.saleId);
    QVERIFY(spy.wait(5000));

    QVERIFY(!spy.at(0).at(0).toBool());
    QVERIFY(spy.at(0).at(1).toString().contains("paper"));
    QTest::qWait(200);
    QCOMPARE(printer.jobs().size(), 0); // nothing was printed, so the cashier prints again after changing the paper
}

void Smart210Test::aPrinterThatDoesNotAnswerTheStatusQuestionIsSentTheJobAnyway()
{
    setUpSelling();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    FakePrinter printer;
    QVERIFY(printer.listen()); // no status set: it stays silent
    Settings(db).setValue(SettingKey::PrinterHost, "127.0.0.1");
    Settings(db).setInt(SettingKey::PrinterPort, printer.port());
    PrintService service(db, PrintOptions());
    service.setStatusWaitMs(200);
    const SpotSale::Result sale = SpotSale::create(db, spotInput(1050), 7, QDateTime::currentDateTime());
    QSignalSpy spy(&service, SIGNAL(finished(bool,QString)));

    service.printSaleTicket(sale.saleId);
    QVERIFY(spy.wait(5000));

    QVERIFY(spy.at(0).at(0).toBool());
    QTRY_COMPARE_WITH_TIMEOUT(printer.jobs().size(), 1, 3000);
}

// 009: a terminal database at version 8 gets the print count of a ticket and keeps its tickets
void Smart210Test::anOldDatabaseGetsThePrintCountOfATicket()
{
    QSqlDatabase db = openDatabase(false);
    const QStringList all = Migrator::bundled();
    QVERIFY(all.at(8).contains("ADD COLUMN print_count"));

    QString error;
    QVERIFY2(Migrator::apply(db, all.mid(0, 8), &error), qPrintable(error));
    QCOMPARE(Migrator::version(db), 8);
    QVERIFY(!migratedColumnsOf(db, "tickets").contains("print_count"));
    run("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (3000000041, 'D', 0, 1500, 1, 1)");

    QVERIFY2(Migrator::apply(db, all, &error), qPrintable(error));
    QCOMPARE(Migrator::version(db), 12);
    QVERIFY(migratedColumnsOf(db, "tickets").contains("print_count") && migratedColumnsOf(db, "tickets").contains("last_printed_at"));
    QCOMPARE(one("SELECT COUNT(*) FROM tickets WHERE barcode = 3000000041 AND amount_kg = 1500 AND print_count = 0 AND last_printed_at IS NULL").toInt(), 1);
}
void Smart210Test::printingSaysWhyItCannotPrint()
{
    setUpSelling();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    PrintService service(db, PrintOptions());
    const SpotSale::Result sale = SpotSale::create(db, spotInput(1050), 7, QDateTime::currentDateTime());
    QSignalSpy spy(&service, SIGNAL(finished(bool,QString)));

    service.printSaleTicket(sale.saleId); // no printer address yet
    QCOMPARE(spy.size(), 1);
    QVERIFY(!spy.at(0).at(0).toBool());
    QVERIFY(spy.at(0).at(1).toString().contains("No printer is set up"));

    FakePrinter printer;
    QVERIFY(printer.listen());
    const quint16 port = printer.port();
    printer.close();                                           // nothing listens: switched off
    Settings(db).setValue(SettingKey::PrinterHost, "127.0.0.1");
    Settings(db).setInt(SettingKey::PrinterPort, port);
    service.printSaleTicket(sale.saleId);
    QVERIFY(spy.wait(10000));
    QVERIFY(!spy.at(1).at(0).toBool());
    QVERIFY(spy.at(1).at(1).toString().startsWith("Cannot reach the printer"));

    service.printSaleTicket("no-such-sale");
    QVERIFY(!spy.last().at(0).toBool());
    QVERIFY(spy.last().at(1).toString().contains("no ticket to print"));
}

void Smart210Test::thePrinterCanBeCheckedWithoutPrinting()
{
    setUpSelling();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    FakePrinter printer;
    QVERIFY(printer.listen());
    printer.goodStatus();
    Settings(db).setValue(SettingKey::PrinterHost, "127.0.0.1");
    Settings(db).setInt(SettingKey::PrinterPort, printer.port());
    PrintService service(db, PrintOptions());
    service.setStatusWaitMs(200);
    QSignalSpy spy(&service, SIGNAL(finished(bool,QString)));

    service.checkPrinter();
    QVERIFY(spy.wait(5000));
    QVERIFY(spy.at(0).at(0).toBool());
    QVERIFY(spy.at(0).at(1).toString().contains("ready"));

    printer.setStatus(QByteArray("\x16\x16\x12", 3)); // cover open
    service.checkPrinter();
    QVERIFY(spy.wait(5000));
    QVERIFY(!spy.at(1).at(0).toBool());
    QVERIFY(spy.at(1).at(1).toString().contains("cover"));

    printer.setStatus(QByteArray()); // silent
    service.checkPrinter();
    QVERIFY(spy.wait(5000));
    QVERIFY(spy.at(2).at(0).toBool());
    QVERIFY(spy.at(2).at(1).toString().contains("did not report"));
    QCOMPARE(printer.jobs().size(), 0); // a check prints nothing
}

void Smart210Test::theSpotSaleScreenPrintsAtOnceAndPrintsAgainOnRequest()
{
    setUpSellingTerminal();
    FakePrinter printer;
    QVERIFY(printer.listen());
    printer.goodStatus();
    Settings(QSqlDatabase::database(connection_)).setValue(SettingKey::PrinterHost, "127.0.0.1");
    Settings(QSqlDatabase::database(connection_)).setInt(SettingKey::PrinterPort, printer.port());
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    QTRY_VERIFY_WITH_TIMEOUT(server_->log().contains("GET /api/pull/ticket_loans?limit=200"), 10000);
    QTest::qWait(300);
    window.showPage("spot");
    QLineEdit *kg = window.findChild<QLineEdit *>("kgField");
    QComboBox *customer = window.findChild<QComboBox *>("customerCombo");
    QLabel *status = window.findChild<QLabel *>("saleStatus");
    QPushButton *again = window.findChild<QPushButton *>("printAgainButton");
    QVERIFY(!again->isEnabled()); // nothing to print yet

    customer->setCurrentIndex(customer->findText("ACME"));
    QTest::keyClicks(kg, "10.5");
    window.findChild<QPushButton *>("saveSaleButton")->click();

    QTRY_VERIFY_WITH_TIMEOUT(status->text().contains("sent to the printer"), 5000);
    QVERIFY(status->text().startsWith("Saved: 10.50 kg"));
    QTRY_COMPARE_WITH_TIMEOUT(printer.jobs().size(), 1, 3000); // printed without anyone asking
    QVERIFY(again->isEnabled());

    again->click();
    QTRY_COMPARE_WITH_TIMEOUT(printer.jobs().size(), 2, 5000);
    QCOMPARE(printer.jobs().at(1), printer.jobs().at(0)); // the same ticket again
    QCOMPARE(countRows("sales"), 1);                       // and not a second sale
}

void Smart210Test::aSaleIsKeptWhenThePrinterFailsAndCanBePrintedLater()
{
    setUpSellingTerminal();
    FakePrinter printer;
    QVERIFY(printer.listen());
    const quint16 port = printer.port();
    printer.close(); // the printer is off
    Settings(QSqlDatabase::database(connection_)).setValue(SettingKey::PrinterHost, "127.0.0.1");
    Settings(QSqlDatabase::database(connection_)).setInt(SettingKey::PrinterPort, port);
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    QTRY_VERIFY_WITH_TIMEOUT(server_->log().contains("GET /api/pull/ticket_loans?limit=200"), 10000);
    QTest::qWait(300);
    window.showPage("spot");
    QComboBox *customer = window.findChild<QComboBox *>("customerCombo");
    QLabel *status = window.findChild<QLabel *>("saleStatus");
    customer->setCurrentIndex(customer->findText("ACME"));
    QTest::keyClicks(window.findChild<QLineEdit *>("kgField"), "5");

    window.findChild<QPushButton *>("saveSaleButton")->click();

    QTRY_VERIFY_WITH_TIMEOUT(status->text().contains("It was not printed"), 15000);
    QVERIFY(status->text().startsWith("Saved: 5.00 kg"));
    QCOMPARE(countRows("sales"), 1);                                  // the sale is there
    QVERIFY(window.findChild<QPushButton *>("printAgainButton")->isEnabled()); // and the cashier can try again
}

void Smart210Test::thePrinterAddressIsSetAndCheckedOnMachineConfig()
{
    setUpLoggedInTerminalWithAPin();
    serverKnowsThisTerminal();
    FakePrinter printer;
    QVERIFY(printer.listen());
    printer.goodStatus();
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    window.showPage("settings");
    window.showPage("pin");
    window.findChild<QLineEdit *>("pinField")->setText("4821");
    window.findChild<QPushButton *>("pinOkButton")->click();
    QCOMPARE(window.currentPage(), QString("machine"));

    QLineEdit *host = window.findChild<QLineEdit *>("printerHost");
    QLineEdit *port = window.findChild<QLineEdit *>("printerPort");
    QLabel *status = window.findChild<QLabel *>("printerStatus");
    QCOMPARE(port->text(), QString("9100")); // the usual port
    host->setText("127.0.0.1");
    port->setText("0");
    window.findChild<QPushButton *>("savePrinterButton")->click();
    QVERIFY(status->text().contains("port"));
    QCOMPARE(Settings(QSqlDatabase::database(connection_)).value(SettingKey::PrinterHost), QString());

    port->setText(QString::number(printer.port()));
    window.findChild<QPushButton *>("checkPrinterButton")->click(); // saves, then asks the printer

    QTRY_VERIFY_WITH_TIMEOUT(status->text().contains("ready"), 5000);
    Settings settings(QSqlDatabase::database(connection_));
    QCOMPARE(settings.value(SettingKey::PrinterHost), QString("127.0.0.1"));
    QCOMPARE(settings.intValue(SettingKey::PrinterPort), int(printer.port()));
    const MachineConfig::Change change = changes().recentChanges(1).at(0);
    QCOMPARE(change.what, QString("printer"));
    QCOMPARE(change.newValue, "127.0.0.1:" + QString::number(printer.port()));
    QCOMPARE(change.userName, QString("Cash One"));
}

// ---- Nozzle board protocol (task 6.2) ----

namespace {

// A bus on the fake boards with quick timings, for the tests.
int freePort()
{
    QTcpServer probe;
    probe.listen(QHostAddress::Any, 0);
    const int port = probe.serverPort();
    probe.close();

    return port;
}

void quickBus(BoardBus *bus, FakeBoards *boards)
{
    Q_UNUSED(boards);
    bus->setListenPort(freePort());
    bus->setCycleMs(5);
    bus->setAnswerTimeoutMs(60);
    bus->setOfflineRetryMs(100);
    bus->setMissLimit(3);
}

// the bus listens, the converter (the fake boards) connects to it
bool startBus(BoardBus *bus, FakeBoards *boards)
{
    if (!bus->start()) {
        return false;
    }
    boards->connectTo(quint16(bus->listenPort()));

    return true;
}

int flaggedPolls(const QSignalSpy &spy, int address, int flag)
{
    int count = 0;
    for (int i = 0; i < spy.size(); ++i) {
        const BoardProtocol::Status status = qvariant_cast<BoardProtocol::Status>(spy.at(i).at(1));
        if (spy.at(i).at(0).toInt() == address && (status.flags & flag)) {
            ++count;
        }
    }

    return count;
}

QByteArray hexOf(const QByteArray &bytes)
{
    return bytes.toHex();
}

}

void Smart210Test::theCrcAndTheFramesMatchTheSpecExamples()
{
    using namespace BoardProtocol;
    QCOMPARE(int(crc16("123456789")), 0x4B37);
    QCOMPARE(hexOf(build(1, 5, CmdPoll)), QByteArray("020105020010b9"));

    QByteArray poll;
    poll.append(char(3));
    poll.append(char(0));
    poll.append(char(0));
    poll.append(QByteArray("\x2A\x00\x00\x00", 4));
    poll.append(QByteArray("\xD4\x30\x00\x00", 4)); // 12500 g
    poll.append(QByteArray("\x6C\x5A\x00\x00", 4)); // 23148 ml
    QCOMPARE(hexOf(build(1, 5, CmdPoll | AnswerFlag, poll)), QByteArray("020105820f0300002a000000d43000006c5a00005720"));

    QCOMPARE(hexOf(build(2, 6, CmdSetPreset, presetData(15000))), QByteArray("0202060504983a0000592a"));
    QCOMPARE(hexOf(build(2, 7, NakCommand, QByteArray("\x03\x06", 2))), QByteArray("020207ff020306a51f"));
}

void Smart210Test::theParserFindsFramesInPiecesAndGarbage()
{
    using namespace BoardProtocol;
    const QByteArray one = build(1, 1, CmdPoll);
    const QByteArray two = build(2, 9, CmdStart | AnswerFlag, QByteArray("\x02\x02\x02\x02", 4)); // STX bytes inside the data

    // in single bytes
    FrameParser pieces;
    Frame frame;
    QList<int> seen;
    const QByteArray both = one + two;
    for (int i = 0; i < both.size(); ++i) {
        pieces.feed(both.mid(i, 1));
        while (pieces.next(&frame)) {
            seen.append(frame.address);
        }
    }
    QCOMPARE(seen, QList<int>() << 1 << 2);

    // garbage in front, a spoiled frame, an impossible length, then a good one
    QByteArray spoiled = one;
    spoiled[spoiled.size() - 1] = char(spoiled.at(spoiled.size() - 1) ^ 0x01);
    FrameParser rough;
    rough.feed(QByteArray("\xFF\x02\x02", 3) + spoiled + QByteArray("\x02\x01\x01\xEE\xC8", 5) + two);
    QVERIFY(rough.next(&frame));
    QCOMPARE(frame.address, 2);
    QCOMPARE(frame.seq, 9);
    QCOMPARE(frame.command, int(CmdStart | AnswerFlag));
    QCOMPARE(frame.data, QByteArray("\x02\x02\x02\x02", 4));
    QVERIFY(!rough.next(&frame));

    // half a frame waits for the rest
    FrameParser half;
    half.feed(two.left(6));
    QVERIFY(!half.next(&frame));
    half.feed(two.mid(6));
    QVERIFY(half.next(&frame));
}

void Smart210Test::theDataOfTheCommandsRoundTrips()
{
    using namespace BoardProtocol;
    QCOMPARE(hexOf(presetData(15000)), QByteArray("983a0000"));
    QCOMPARE(hexOf(resultIdData(0x01020304)), QByteArray("04030201"));

    Status status;
    QVERIFY(parseStatus(QByteArray::fromHex("0300002a000000d43000006c5a0000983a0000"), &status));
    QCOMPARE(status.state, int(StateDispensing));
    QCOMPARE(status.resultId, quint32(42));
    QCOMPARE(status.dispensedG, quint32(12500));
    QCOMPARE(status.dispensedMl, quint32(23148));
    QCOMPARE(status.presetG, quint32(15000));
    QVERIFY(!parseStatus(QByteArray(18, 0), &status));

    Result result;
    QVERIFY(parseResult(QByteArray::fromHex("2a000000983a0000d43000006c5a000001"), &result));
    QCOMPARE(result.resultId, quint32(42));
    QCOMPARE(result.presetG, quint32(15000));
    QCOMPARE(result.dispensedG, quint32(12500));
    QCOMPARE(result.reason, 1);
    QVERIFY(!parseResult(QByteArray(16, 0), &result));

    BoardConfig config;
    QVERIFY(parseBoardConfig(QByteArray::fromHex("004400000" "06c203ae20100"), &config));
    QCOMPARE(config.mode, int(ModeAuto));
    QCOMPARE(config.fuelType, QString("D"));
    QCOMPARE(int(config.density), 8300);
    QCOMPARE(config.pulsesX100, quint32(123450));
    QVERIFY(config.isValid());
    QCOMPARE(hexOf(configData(config)), QByteArray("00440000006c203ae20100"));
    QVERIFY(!parseBoardConfig(QByteArray(10, 0), &config));
    config.fuelType = "DIESEL"; // the board keeps four characters
    QCOMPARE(hexOf(configData(config)), QByteArray("00444945536c203ae20100"));
    config.fuelType = "";
    QVERIFY(!config.isValid());

    Info info;
    QVERIFY(parseInfo(QByteArray::fromHex("020104010000ca"), &info));
    QCOMPARE(info.protocol, 2);
    QCOMPARE(info.versionMajor, 1);
    QCOMPARE(info.versionMinor, 4);
    QCOMPARE(info.serial, quint32(0xCA000001));
}

void Smart210Test::theBusPollsEveryBoardInTurn()
{
    FakeBoards boards;
    boards.add(1);
    boards.add(2);
    BoardBus bus;
    quickBus(&bus, &boards);
    bus.addBoard(1);
    bus.addBoard(2);
    QSignalSpy polled(&bus, SIGNAL(polled(int,BoardProtocol::Status)));
    QSignalSpy connection(&bus, SIGNAL(connectionChanged(bool)));
    QVERIFY(startBus(&bus, &boards));

    QTRY_VERIFY_WITH_TIMEOUT(bus.isOnline(1) && bus.isOnline(2), 3000);
    QTRY_VERIFY_WITH_TIMEOUT(boards.pollsOf(1) >= 6 && boards.pollsOf(2) >= 6, 5000);
    QVERIFY(bus.isConnected());
    QCOMPARE(connection.size(), 1);
    QVERIFY(polled.size() >= 8);
    QCOMPARE(bus.lastStatus(1).state, int(BoardProtocol::StateIdle));
    // one request at a time, round and round: the first requests alternate between the boards
    const QList<int> a = boards.board(1).requestSeqs;
    QVERIFY(a.size() >= 3);
    QCOMPARE(a.at(1), (a.at(0) + 1) & 0xFF); // SEQ moves on after each good answer
    QCOMPARE(a.at(2), (a.at(0) + 2) & 0xFF);
}

void Smart210Test::aCommandIsAnsweredAndTheNextPollShowsIt()
{
    FakeBoards boards;
    boards.add(1);
    BoardBus bus;
    quickBus(&bus, &boards);
    bus.addBoard(1);
    QSignalSpy done(&bus, SIGNAL(commandDone(int,int,QByteArray)));
    QVERIFY(startBus(&bus, &boards));
    QTRY_VERIFY_WITH_TIMEOUT(bus.isOnline(1), 3000);

    QVERIFY(bus.setPreset(1, 15000));

    QTRY_COMPARE_WITH_TIMEOUT(done.size(), 1, 3000);
    QCOMPARE(done.at(0).at(0).toInt(), 1);
    QCOMPARE(done.at(0).at(1).toInt(), int(BoardProtocol::CmdSetPreset));
    QTRY_COMPARE_WITH_TIMEOUT(bus.lastStatus(1).state, int(BoardProtocol::StateArmed), 3000);
}

void Smart210Test::theSelectKeyIsSeenOnceInThePollFlags()
{
    FakeBoards boards;
    boards.add(1);
    BoardBus bus;
    quickBus(&bus, &boards);
    bus.addBoard(1);
    QSignalSpy polled(&bus, SIGNAL(polled(int,BoardProtocol::Status)));
    QVERIFY(startBus(&bus, &boards));
    QTRY_VERIFY_WITH_TIMEOUT(bus.isOnline(1), 3000);

    boards.pressSelect(1);

    QTRY_COMPARE_WITH_TIMEOUT(flaggedPolls(polled, 1, BoardProtocol::FlagSelectKey), 1, 3000);
    QTest::qWait(150);
    QCOMPARE(flaggedPolls(polled, 1, BoardProtocol::FlagSelectKey), 1);
    QCOMPARE(bus.lastStatus(1).state, int(BoardProtocol::StateIdle));   // the key only tells: the board is chosen by the SELECT command
    QCOMPARE(boards.board(1).beeps, 0);
}

void Smart210Test::aLostAnswerCostsOneRoundAndTheCommandRunsOnce()
{
    FakeBoards boards;
    boards.add(1);
    BoardBus bus;
    quickBus(&bus, &boards);
    bus.addBoard(1);
    QSignalSpy done(&bus, SIGNAL(commandDone(int,int,QByteArray)));
    QSignalSpy failed(&bus, SIGNAL(commandFailed(int,int,int)));
    QVERIFY(startBus(&bus, &boards));
    QTRY_VERIFY_WITH_TIMEOUT(bus.isOnline(1), 3000);
    QVERIFY(bus.select(1));
    QTRY_COMPARE_WITH_TIMEOUT(done.size(), 1, 3000);
    QVERIFY(bus.setPreset(1, 15000));
    QTRY_COMPARE_WITH_TIMEOUT(done.size(), 2, 3000);

    boards.board(1).dropCommandOnce = BoardProtocol::CmdStart; // the board starts, but the answer never arrives
    QVERIFY(bus.startFill(1));

    QTRY_COMPARE_WITH_TIMEOUT(done.size(), 3, 3000);
    QCOMPARE(failed.size(), 0);
    QCOMPARE(done.at(2).at(1).toInt(), int(BoardProtocol::CmdStart));
    quint32 id = 0;
    QVERIFY(BoardProtocol::parseResultId(done.at(2).at(2).toByteArray(), &id));
    QCOMPARE(id, boards.board(1).resultId);
    QCOMPARE(boards.board(1).commands, QList<int>() << int(BoardProtocol::CmdSelect) << int(BoardProtocol::CmdSetPreset) << int(BoardProtocol::CmdStart));
    QVERIFY(bus.isOnline(1));
    // the START went out twice with the same SEQ
    const QList<int> seqs = boards.board(1).requestSeqs;
    bool repeated = false;
    for (int i = 1; i < seqs.size(); ++i) {
        repeated = repeated || seqs.at(i) == seqs.at(i - 1);
    }
    QVERIFY(repeated);
}

void Smart210Test::aSpoiledPollAnswerIsSkippedAndTheBoardStaysOnline()
{
    FakeBoards boards;
    boards.add(1);
    BoardBus bus;
    quickBus(&bus, &boards);
    bus.addBoard(1);
    QSignalSpy online(&bus, SIGNAL(boardOnlineChanged(int,bool)));
    QVERIFY(startBus(&bus, &boards));
    QTRY_VERIFY_WITH_TIMEOUT(bus.isOnline(1), 3000);
    QTRY_VERIFY_WITH_TIMEOUT(boards.pollsOf(1) >= 3, 3000);
    const int before = boards.pollsOf(1);

    boards.board(1).corruptCommandOnce = BoardProtocol::CmdPoll;

    QTRY_VERIFY_WITH_TIMEOUT(boards.pollsOf(1) >= before + 5, 3000);
    QVERIFY(bus.isOnline(1));
    QCOMPARE(online.size(), 1); // only the first coming online
}

void Smart210Test::aKeyEventSurvivesALostPollAnswer()
{
    FakeBoards boards;
    boards.add(1);
    BoardBus bus;
    quickBus(&bus, &boards);
    bus.addBoard(1);
    QSignalSpy polled(&bus, SIGNAL(polled(int,BoardProtocol::Status)));
    QVERIFY(startBus(&bus, &boards));
    QTRY_VERIFY_WITH_TIMEOUT(bus.isOnline(1), 3000);

    boards.board(1).dropCommandOnce = BoardProtocol::CmdPoll; // the answer that carries the key is lost
    boards.pressSelect(1);

    QTRY_COMPARE_WITH_TIMEOUT(flaggedPolls(polled, 1, BoardProtocol::FlagSelectKey), 1, 3000);
    QTest::qWait(150);
    QCOMPARE(flaggedPolls(polled, 1, BoardProtocol::FlagSelectKey), 1);
}

void Smart210Test::aBoardThatStopsAnsweringGoesOfflineAndTheOthersKeepRunning()
{
    FakeBoards boards;
    boards.add(1);
    boards.add(2);
    BoardBus bus;
    quickBus(&bus, &boards);
    bus.addBoard(1);
    bus.addBoard(2);
    QSignalSpy online(&bus, SIGNAL(boardOnlineChanged(int,bool)));
    QVERIFY(startBus(&bus, &boards));
    QTRY_VERIFY_WITH_TIMEOUT(bus.isOnline(1) && bus.isOnline(2), 3000);

    const int start = boards.pollsOf(2);
    QTest::qWait(600);
    const int baseline = boards.pollsOf(2) - start; // board 2 with both boards answering

    boards.board(1).silent = true;

    QTRY_VERIFY_WITH_TIMEOUT(!bus.isOnline(1), 3000);
    QVERIFY(bus.isOnline(2));
    const int before = boards.pollsOf(2);
    QTest::qWait(600);
    QVERIFY(baseline >= 4);
    QVERIFY2(boards.pollsOf(2) - before >= baseline / 3, "the dead board slows board 2 down too much");
    QVERIFY(!bus.isOnline(1));

    boards.board(1).silent = false;

    QTRY_VERIFY_WITH_TIMEOUT(bus.isOnline(1), 3000);
    QCOMPARE(online.at(online.size() - 1).at(0).toInt(), 1);
    QVERIFY(online.at(online.size() - 1).at(1).toBool());
}

void Smart210Test::aCommandWaitingForADeadBoardFails()
{
    FakeBoards boards;
    boards.add(1);
    BoardBus bus;
    quickBus(&bus, &boards);
    bus.addBoard(1);
    QSignalSpy failed(&bus, SIGNAL(commandFailed(int,int,int)));
    QVERIFY(startBus(&bus, &boards));
    QTRY_VERIFY_WITH_TIMEOUT(bus.isOnline(1), 3000);

    boards.board(1).silent = true;
    QVERIFY(bus.select(1));

    QTRY_COMPARE_WITH_TIMEOUT(failed.size(), 1, 3000);
    QCOMPARE(failed.at(0).at(1).toInt(), int(BoardProtocol::CmdSelect));
    QCOMPARE(failed.at(0).at(2).toInt(), int(BoardProtocol::ErrNoAnswer));
    QVERIFY(!bus.select(1)); // offline now: nothing is queued
}

void Smart210Test::commandsAreRefusedWhenTheyCannotBeQueued()
{
    FakeBoards boards;
    boards.add(1);
    BoardBus bus;
    quickBus(&bus, &boards);
    bus.addBoard(1);
    QVERIFY(!bus.select(1)); // not started

    QVERIFY(startBus(&bus, &boards));
    QTRY_VERIFY_WITH_TIMEOUT(bus.isOnline(1), 3000);

    QVERIFY(bus.select(1));
    QVERIFY(!bus.select(1));   // one is already waiting
    QVERIFY(!bus.select(7));   // no such board
    QVERIFY(!bus.hasBoard(0)); // 0 is no address
    bus.addBoard(0);
    bus.addBoard(248);
    QVERIFY(!bus.hasBoard(0));
    QVERIFY(!bus.hasBoard(248));
}

void Smart210Test::aNakIsReportedWithItsError()
{
    FakeBoards boards;
    boards.add(1);
    BoardBus bus;
    quickBus(&bus, &boards);
    bus.addBoard(1);
    QSignalSpy done(&bus, SIGNAL(commandDone(int,int,QByteArray)));
    QSignalSpy failed(&bus, SIGNAL(commandFailed(int,int,int)));
    QVERIFY(startBus(&bus, &boards));
    QTRY_VERIFY_WITH_TIMEOUT(bus.isOnline(1), 3000);

    QVERIFY(bus.startFill(1)); // nothing is armed

    QTRY_COMPARE_WITH_TIMEOUT(failed.size(), 1, 3000);
    QCOMPARE(failed.at(0).at(0).toInt(), 1);
    QCOMPARE(failed.at(0).at(1).toInt(), int(BoardProtocol::CmdStart));
    QCOMPARE(failed.at(0).at(2).toInt(), int(BoardProtocol::ErrWrongState));
    QCOMPARE(done.size(), 0);
    QVERIFY(bus.isOnline(1));
    QVERIFY(bus.select(1)); // and the board takes the next command
    QVERIFY(!BoardProtocol::errorText(BoardProtocol::ErrWrongState).isEmpty());
}

void Smart210Test::aFillRunsFromPresetToTheAcknowledgedResult()
{
    FakeBoards boards;
    boards.add(1);
    BoardBus bus;
    quickBus(&bus, &boards);
    bus.addBoard(1);
    QSignalSpy done(&bus, SIGNAL(commandDone(int,int,QByteArray)));
    QSignalSpy failed(&bus, SIGNAL(commandFailed(int,int,int)));
    QVERIFY(startBus(&bus, &boards));
    QTRY_VERIFY_WITH_TIMEOUT(bus.isOnline(1), 3000);

    QVERIFY(bus.select(1));
    QTRY_COMPARE_WITH_TIMEOUT(done.size(), 1, 3000);
    QVERIFY(bus.setPreset(1, 15000));
    QTRY_COMPARE_WITH_TIMEOUT(done.size(), 2, 3000);
    QCOMPARE(boards.board(1).presetG, quint32(15000));
    QVERIFY(bus.startFill(1));
    QTRY_COMPARE_WITH_TIMEOUT(done.size(), 3, 3000);
    quint32 id = 0;
    QVERIFY(BoardProtocol::parseResultId(done.at(2).at(2).toByteArray(), &id));
    QVERIFY(id != 0);
    QTRY_COMPARE_WITH_TIMEOUT(bus.lastStatus(1).state, int(BoardProtocol::StateDispensing), 3000);
    QCOMPARE(bus.lastStatus(1).resultId, id);
    QVERIFY(bus.setPreset(1, 0)); // a fill cannot be stopped from here (a preset of zero is the cancel)
    QTRY_COMPARE_WITH_TIMEOUT(failed.size(), 1, 3000);
    QCOMPARE(failed.at(0).at(2).toInt(), int(BoardProtocol::ErrWrongState));

    boards.finish(1, 14980, 27740, BoardProtocol::ReasonPresetReached);

    QTRY_COMPARE_WITH_TIMEOUT(bus.lastStatus(1).state, int(BoardProtocol::StateResult), 3000);
    QVERIFY(bus.lastStatus(1).flags & BoardProtocol::FlagResult);
    QVERIFY(bus.getResult(1));
    QTRY_VERIFY_WITH_TIMEOUT(done.size() >= 4 && done.at(3).at(1).toInt() == int(BoardProtocol::CmdGetResult), 3000);
    BoardProtocol::Result result;
    QVERIFY(BoardProtocol::parseResult(done.at(3).at(2).toByteArray(), &result));
    QCOMPARE(result.resultId, id);
    QCOMPARE(result.presetG, quint32(15000));
    QCOMPARE(result.dispensedG, quint32(14980));
    QCOMPARE(result.dispensedMl, quint32(27740));
    QCOMPARE(result.reason, int(BoardProtocol::ReasonPresetReached));

    QVERIFY(bus.ackResult(1, id)); // the result is saved here first; only then comes the ack
    QTRY_VERIFY_WITH_TIMEOUT(done.size() >= 5, 3000);
    QTRY_COMPARE_WITH_TIMEOUT(bus.lastStatus(1).state, int(BoardProtocol::StateIdle), 3000);
    QVERIFY(bus.ackResult(1, id)); // an ack sent twice is fine
    QTRY_VERIFY_WITH_TIMEOUT(done.size() >= 6, 3000);
    QCOMPARE(failed.size(), 1); // only the refused cancel
}

void Smart210Test::theLinkIsOpenedAgainAfterItDrops()
{
    FakeBoards boards;
    boards.add(1);
    BoardBus bus;
    quickBus(&bus, &boards);
    bus.addBoard(1);
    QSignalSpy connection(&bus, SIGNAL(connectionChanged(bool)));
    QSignalSpy failed(&bus, SIGNAL(commandFailed(int,int,int)));
    QVERIFY(startBus(&bus, &boards));
    QTRY_VERIFY_WITH_TIMEOUT(bus.isOnline(1), 3000);

    boards.setReconnectMs(400); // the converter takes a moment to connect again
    boards.board(1).silent = true;
    QVERIFY(bus.select(1));
    boards.dropConnection();

    QTRY_COMPARE_WITH_TIMEOUT(failed.size(), 1, 3000);
    QCOMPARE(failed.at(0).at(2).toInt(), int(BoardProtocol::ErrNoConnection));
    QVERIFY(!bus.isConnected());
    QVERIFY(!bus.isOnline(1));
    QVERIFY(!bus.select(1));

    boards.board(1).silent = false;

    QTRY_VERIFY_WITH_TIMEOUT(bus.isConnected() && bus.isOnline(1), 5000);
    QVERIFY(connection.size() >= 3); // up, down, up
    QVERIFY(bus.select(1));
}

void Smart210Test::theBusWaitsForTheConverterToConnect()
{
    FakeBoards boards;
    boards.add(1);
    BoardBus bus;
    quickBus(&bus, &boards);
    bus.addBoard(1);
    QVERIFY(bus.start()); // listening, nobody has connected
    QTest::qWait(250);
    QVERIFY(!bus.isConnected());
    QVERIFY(!bus.isOnline(1));
    QCOMPARE(boards.requests(), 0);

    boards.connectTo(quint16(bus.listenPort()));

    QTRY_VERIFY_WITH_TIMEOUT(bus.isConnected() && bus.isOnline(1), 5000);
}

void Smart210Test::aNewConverterConnectionReplacesTheOldOne()
{
    FakeBoards first;
    first.add(1);
    first.setAutoReconnect(false);
    BoardBus bus;
    quickBus(&bus, &first);
    bus.addBoard(1);
    QSignalSpy connection(&bus, SIGNAL(connectionChanged(bool)));
    QVERIFY(startBus(&bus, &first));
    QTRY_VERIFY_WITH_TIMEOUT(bus.isOnline(1), 3000);

    FakeBoards second; // the converter restarted: the old link is dead, a new one arrives
    second.add(1);
    second.connectTo(quint16(bus.listenPort()));

    QTRY_VERIFY_WITH_TIMEOUT(!first.isConnected(), 3000);
    QTRY_VERIFY_WITH_TIMEOUT(second.pollsOf(1) >= 3, 3000);
    QTRY_VERIFY_WITH_TIMEOUT(bus.isConnected() && bus.isOnline(1), 3000);
    QCOMPARE(connection.size(), 3); // up (old link), down, up (new link)
}

void Smart210Test::aBusThatCannotListenSaysWhy()
{
    QTcpServer taken;
    QVERIFY(taken.listen(QHostAddress::Any, 0));
    BoardBus bus;
    bus.setListenPort(taken.serverPort());

    QVERIFY(!bus.start());

    QVERIFY(!bus.errorText().isEmpty());
}

void Smart210Test::answersCutIntoSingleBytesStillWork()
{
    FakeBoards boards;
    boards.add(1);
    boards.cutReplies(true);
    BoardBus bus;
    quickBus(&bus, &boards);
    bus.setAnswerTimeoutMs(300);
    bus.addBoard(1);
    QSignalSpy done(&bus, SIGNAL(commandDone(int,int,QByteArray)));
    QVERIFY(startBus(&bus, &boards));
    QTRY_VERIFY_WITH_TIMEOUT(bus.isOnline(1), 3000);

    QVERIFY(bus.setPreset(1, 15000));

    QTRY_COMPARE_WITH_TIMEOUT(done.size(), 1, 3000);
    QTRY_COMPARE_WITH_TIMEOUT(bus.lastStatus(1).state, int(BoardProtocol::StateArmed), 3000);
}

void Smart210Test::stoppingTheBusStopsThePolling()
{
    FakeBoards boards;
    boards.add(1);
    BoardBus bus;
    quickBus(&bus, &boards);
    bus.addBoard(1);
    QVERIFY(startBus(&bus, &boards));
    QTRY_VERIFY_WITH_TIMEOUT(boards.pollsOf(1) >= 3, 3000);

    bus.stop();
    QTest::qWait(50);
    const int polls = boards.pollsOf(1);
    QTest::qWait(300);

    QCOMPARE(boards.pollsOf(1), polls);
    QVERIFY(!bus.isConnected());
    QVERIFY(!bus.isOnline(1));
}

// ---- the nozzles are the boards 1 to 6, and each board holds its own config ----

namespace {

BoardProtocol::BoardConfig boardConfig(const char *fuel, int density, int pulsesX100, int mode = BoardProtocol::ModeAuto)
{
    BoardProtocol::BoardConfig config;
    config.mode = mode;
    config.fuelType = fuel;
    config.density = quint16(density);
    config.pulsesX100 = quint32(pulsesX100);

    return config;
}

}

void Smart210Test::nozzlesAreAlwaysTheBoardsOneToSix()
{
    MachineConfig config(openDatabase());

    // nothing is detected by itself: a terminal that is new has no nozzle, and a board that answers makes none
    QVERIFY(config.nozzles().isEmpty());
    QCOMPARE(MachineConfig::NozzleCount, 6);   // the addresses that can be added are 1 to 6
    for (int number = 1; number <= MachineConfig::NozzleCount; ++number) {
        QVERIFY(!config.isConfigured(number));
        QVERIFY(!config.nozzle(number).exists());
        QVERIFY(!config.nozzle(number).seen());
        QVERIFY(!config.storeBoardConfig(number, boardConfig("D", 8300, 100000)));
    }
    QCOMPARE(countRows("nozzles"), 0);
}
void Smart210Test::aNozzleIsAddedByHandCheckedAndKeptUpToDateFromItsBoard()
{
    QSqlDatabase db = openDatabase();
    run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (1, 'T1', 'D', 5000000, 100000)");
    run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (2, 'T2', 'A', 5000000, 100000)");
    run("INSERT INTO fuel_types (fuel_type, name, price, density) VALUES ('D', 'Diesel', 750, 8300)");
    run("INSERT INTO fuel_types (fuel_type, name, price, density) VALUES ('A', 'Gasoline', 800, NULL)");
    addUser(7, "Cash One", "secret1");
    MachineConfig config(db);
    config.setLogFile(changeLog());
    QString why;

    // what is refused
    QVERIFY(!config.addNozzle(0, "D", 0, 7, &why));
    QVERIFY2(why.contains("1 to 6"), qPrintable(why));
    QVERIFY(!config.addNozzle(7, "D", 0, 7, &why));
    QVERIFY(!config.addNozzle(1, "", 0, 7, &why));
    QVERIFY2(why.contains("fuel type"), qPrintable(why));
    QVERIFY(!config.addNozzle(1, "Z", 0, 7, &why));                        // a fuel type the web app does not have
    QVERIFY(!config.addNozzle(1, "D", 2, 7, &why));                        // tank 2 holds gasoline
    QVERIFY2(why.contains("does not hold"), qPrintable(why));
    QCOMPARE(countRows("nozzles"), 0);

    // a nozzle: its number, its fuel type and, here, the only tank of that fuel type; the board has not answered yet
    QVERIFY2(config.addNozzle(1, "D", 0, 7, &why), qPrintable(why));
    MachineConfig::Nozzle nozzle = config.nozzle(1);
    QVERIFY(nozzle.exists());
    QVERIFY(!nozzle.seen());
    QCOMPARE(nozzle.fuelType, QString("D"));
    QCOMPARE(nozzle.tankId, 1);
    QVERIFY(config.isConfigured(1));
    QVERIFY(!config.isConfigured(2));
    QCOMPARE(config.nozzles().size(), 1);
    QCOMPARE(Uploader::pending(db).value("nozzles"), 1);                    // it has a tank: the web app is told
    QVERIFY(!config.addNozzle(1, "D", 0, 7, &why));                         // not twice
    QVERIFY2(why.contains("added already"), qPrintable(why));
    QVERIFY(config.addNozzle(3, "A", 2, 7, &why));                          // its tank given
    QCOMPARE(config.nozzle(3).tankId, 2);
    QCOMPARE(config.nozzles().size(), 2);
    QCOMPARE(config.nozzles().at(0).number, 1);                             // by number
    QCOMPARE(config.nozzles().at(1).number, 3);

    // the board answers: its config is kept for the nozzle (the board's fuel type counts), another address makes nothing
    QVERIFY(config.storeBoardConfig(1, boardConfig("D", 8300, 120000)));
    QVERIFY(config.nozzle(1).seen());
    QCOMPARE(config.nozzle(1).pulsesPerLiter, qlonglong(120000));
    QVERIFY(!config.storeBoardConfig(2, boardConfig("D", 8300, 120000)));
    QCOMPARE(countRows("nozzles"), 2);

    // the change log says who added what
    const QList<MachineConfig::Change> changes = config.recentChanges(5);
    QVERIFY(changes.size() >= 2);
    QCOMPARE(changes.at(0).what, QString("nozzle:3"));
    QCOMPARE(changes.at(0).newValue, QString("added A"));
    QCOMPARE(changes.at(0).userName, QString("Cash One"));
}

void Smart210Test::aRemovedNozzleGoesAwayAndTheWebAppIsToldInTheOrderTheyWereMade()
{
    configureServer();
    QSqlDatabase db = openDatabase();
    run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (1, 'T1', 'D', 5000000, 100000)");
    run("INSERT INTO fuel_types (fuel_type, name, price, density) VALUES ('D', 'Diesel', 750, 8300)");
    MachineConfig config(db);
    QString why;
    QVERIFY(config.addNozzle(1, "D", 0, 0, &why));
    QVERIFY(config.addNozzle(2, "D", 0, 0, &why));
    run("UPDATE nozzles SET synced = 1");                                   // both went up before

    // a nozzle with an open fill cannot be removed
    run("INSERT INTO supply_sessions (nozzle_number, preset_kg, started_at) VALUES (1, 3000, '2026-10-24 09:00:00')");
    QVERIFY(!config.removeNozzle(1, 0, &why));
    QVERIFY2(why.contains("fill is open"), qPrintable(why));
    QVERIFY(config.isConfigured(1));
    run("DELETE FROM supply_sessions");
    QVERIFY(!config.removeNozzle(5, 0, &why));                              // not added
    QVERIFY2(why.contains("was not added"), qPrintable(why));

    // removed: gone from the list, the web app is told; the same number can be added again, and the two go up in the order they were made
    QVERIFY2(config.removeNozzle(1, 0, &why), qPrintable(why));
    QVERIFY(!config.isConfigured(1));
    QCOMPARE(config.nozzles().size(), 1);
    QVERIFY(!config.storeBoardConfig(1, boardConfig("D", 8300, 120000)));  // its board is ignored now
    QCOMPARE(Uploader::pending(db).value("nozzles"), 1);
    QVERIFY(config.addNozzle(1, "D", 0, 0, &why));
    QCOMPARE(Uploader::pending(db).value("nozzles"), 2);

    server_->reply("/api/push/nozzles", 200, q("{'status':'ok','saved':['1','1'],'rejected':[]}"));
    Uploader uploader(db);
    QString message;
    QVERIFY(uploadOk(uploader, &message));
    const QJsonArray rows = QJsonDocument::fromJson(server_->requests().at(0).body).object().value("rows").toArray();
    QCOMPARE(rows.size(), 2);
    QCOMPARE(rows.at(0).toObject().value("deleted").toBool(), true);          // the removed one first
    QCOMPARE(rows.at(1).toObject().value("deleted").toBool(), false);         // then the new one with the same number
    QCOMPARE(Uploader::pending(db).value("nozzles"), 0);                      // each answer marked its own row

    // a nozzle that never had a tank never went up: removing it tells the web app nothing
    QVERIFY(config.addNozzle(4, "D", 0, 0, &why));                            // it takes the only tank of diesel...
    run("UPDATE nozzles SET tank_id = 0, synced = 0 WHERE number = 4");      // ...so it is made one without

    QVERIFY(config.removeNozzle(4, 0, &why));
    QCOMPARE(Uploader::pending(db).value("nozzles"), 0);
}

void Smart210Test::nozzlesAreAddedAndRemovedOnTheMachineConfigPage()
{
    setUpLoggedInTerminalWithAPin();                                         // nozzles 1 and 2 were added; tank T1 holds diesel
    run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (3, 'T3', 'A', 5000000, 100000)");
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    window.showPage("settings");
    window.showPage("pin");
    window.findChild<QLineEdit *>("pinField")->setText("4821");
    window.findChild<QPushButton *>("pinOkButton")->click();
    QCOMPARE(window.currentPage(), QString("machine"));

    QTableWidget *table = window.findChild<QTableWidget *>("nozzleTable");
    QComboBox *number = window.findChild<QComboBox *>("addNozzleNumber");
    QComboBox *fuel = window.findChild<QComboBox *>("addNozzleFuel");
    QComboBox *tank = window.findChild<QComboBox *>("addNozzleTank");
    QPushButton *add = window.findChild<QPushButton *>("addNozzleButton");
    QLabel *status = window.findChild<QLabel *>("machineStatus");
    QCOMPARE(table->rowCount(), 2);
    QStringList numbers;
    for (int i = 0; i < number->count(); ++i) {
        numbers << number->itemText(i);
    }
    QCOMPARE(numbers, QStringList() << "3" << "4" << "5" << "6");           // the addresses not added yet
    QCOMPARE(fuel->count(), 2);                                              // the fuel types of the last download

    // gasoline at address 4, on its only tank
    number->setCurrentIndex(number->findText("4"));
    fuel->setCurrentIndex(fuel->findData("A"));
    fuel->activated(fuel->currentIndex());
    QCOMPARE(tank->currentText(), QString("T3"));
    add->click();
    QVERIFY2(status->text().contains("Nozzle 4 was added"), qPrintable(status->text()));
    QCOMPARE(table->rowCount(), 3);
    QCOMPARE(table->item(2, 0)->text(), QString("4"));                       // by number: 1, 2, 4
    QCOMPARE(table->item(2, 7)->text(), QString("T3"));                       // the tank chosen when adding shows before the board has reported
    QCOMPARE(number->findText("4"), -1);                                     // no longer free to add
    QCOMPARE(window.findChild<QLabel *>("selectedNozzle")->text(), QString("Nozzle 4"));
    QCOMPARE(QSqlDatabase::database(connection_).exec("SELECT COUNT(*) FROM nozzles WHERE number = 4 AND tank_id = 3 AND fuel_type = 'A'").next(), true);
    QVERIFY(window.findChild<QListWidget *>("changeLog")->item(0)->text().contains("Nozzle 4: - -> added A"));

    // every row has its own Remove button; a click asks first, and No keeps the nozzle
    for (int row = 0; row < table->rowCount(); ++row) {
        QVERIFY(qobject_cast<QPushButton *>(table->cellWidget(row, 8)) != 0);
    }
    QCOMPARE(window.findChild<QPushButton *>("removeNozzleButton"), static_cast<QPushButton *>(0));   // the one button for the picked nozzle is gone
    QPushButton *remove = qobject_cast<QPushButton *>(table->cellWidget(2, 8));
    QCOMPARE(remove->text(), QString("Remove"));
    boxAnswer_ = QMessageBox::No;
    boxText_.clear();
    QTimer::singleShot(50, this, SLOT(answerTheMessageBox()));
    remove->click();
    QVERIFY2(boxText_.contains("Remove nozzle 4"), qPrintable(boxText_));
    QCOMPARE(table->rowCount(), 3);
    QVERIFY(QSqlDatabase::database(connection_).exec("SELECT 1 FROM nozzles WHERE number = 4 AND deleted = 0").next());

    // Yes removes that row's nozzle, whichever row is picked in the table
    table->selectRow(0);
    boxAnswer_ = QMessageBox::Yes;
    boxText_.clear();
    QTimer::singleShot(50, this, SLOT(answerTheMessageBox()));
    remove->click();
    QVERIFY2(boxText_.contains("Remove nozzle 4"), qPrintable(boxText_));
    QVERIFY2(status->text().contains("Nozzle 4 was removed"), qPrintable(status->text()));
    QCOMPARE(table->rowCount(), 2);
    QVERIFY(!QSqlDatabase::database(connection_).exec("SELECT 1 FROM nozzles WHERE number = 4 AND deleted = 0").next());
    QCOMPARE(window.findChild<QLabel *>("selectedNozzle")->text(), QString("Nozzle 1"));   // the picked nozzle stays picked
    QVERIFY(number->findText("4") >= 0);                                     // free to add again
    QVERIFY(window.findChild<QListWidget *>("changeLog")->item(0)->text().contains("Nozzle 4: added A -> removed"));

    // the rows that are left keep their own buttons
    QCOMPARE(table->cellWidget(1, 8)->property("nozzle").toInt(), 2);
}
// the name of a nozzle: given when the nozzle is added or later, trimmed, at most 50 characters, logged, and uploaded with the nozzle
void Smart210Test::aNozzleHasANameThatIsAddedSavedLoggedAndUploaded()
{
    configureServer();
    QSqlDatabase db = openDatabase();
    run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (1, 'T1', 'D', 5000000, 100000)");
    run("INSERT INTO fuel_types (fuel_type, name, price, density) VALUES ('D', 'Diesel', 750, 8300)");
    MachineConfig config(db);
    QString why;

    QVERIFY2(config.addNozzle(1, "D", 0, 0, &why, "  Diesel 1  "), qPrintable(why));    // the name is trimmed
    QVERIFY2(config.addNozzle(2, "D", 0, 0, &why), qPrintable(why));                      // a nozzle needs no name
    QCOMPARE(config.nozzle(1).name, QString("Diesel 1"));
    QCOMPARE(config.nozzle(1).label(), QString("Diesel 1"));
    QCOMPARE(config.nozzle(2).name, QString());
    QCOMPARE(config.nozzle(2).label(), QString("Nozzle 2"));                              // the number while it has no name
    QVERIFY(!config.addNozzle(3, "D", 0, 0, &why, QString(51, 'x')));                    // up to 50 characters
    QVERIFY2(why.contains("up to 50 characters"), qPrintable(why));
    QVERIFY(!config.isConfigured(3));

    // both went up with their names (none: null)
    QString message;
    server_->reply("/api/push/nozzles", 200, q("{'status':'ok','saved':['1','2'],'rejected':[]}"));
    Uploader uploader(db);
    QVERIFY(uploadOk(uploader, &message));
    QJsonArray rows = QJsonDocument::fromJson(server_->requests().at(0).body).object().value("rows").toArray();
    QCOMPARE(rows.size(), 2);
    QCOMPARE(rows.at(0).toObject().value("number").toInt(), 1);
    QCOMPARE(rows.at(0).toObject().value("name").toString(), QString("Diesel 1"));
    QVERIFY(rows.at(1).toObject().contains("name"));
    QVERIFY(rows.at(1).toObject().value("name").isNull());
    QCOMPARE(Uploader::pending(db).value("nozzles"), 0);

    // a name given later: saved, logged, and the nozzle goes up again with it
    QVERIFY2(config.setNozzleName(2, " Diesel 2 ", 0, &why), qPrintable(why));
    QCOMPARE(config.nozzle(2).name, QString("Diesel 2"));
    QCOMPARE(Uploader::pending(db).value("nozzles"), 1);
    QCOMPARE(one("SELECT synced FROM nozzles WHERE number = 2").toInt(), 0);
    QVERIFY(config.setNozzleName(2, "Diesel 2", 0, &why));                                // the same name again: nothing to say
    server_->reply("/api/push/nozzles", 200, q("{'status':'ok','saved':['2'],'rejected':[]}"));
    QVERIFY(uploadOk(uploader, &message));
    rows = QJsonDocument::fromJson(server_->requests().at(1).body).object().value("rows").toArray();
    QCOMPARE(rows.size(), 1);
    QCOMPARE(rows.at(0).toObject().value("name").toString(), QString("Diesel 2"));

    // the name is taken away with an empty one; a name that is too long and a nozzle that was not added are refused
    QVERIFY(config.setNozzleName(2, "   ", 0, &why));
    QCOMPARE(config.nozzle(2).label(), QString("Nozzle 2"));
    QVERIFY(!config.setNozzleName(2, QString(51, 'y'), 0, &why));
    QVERIFY2(why.contains("up to 50 characters"), qPrintable(why));
    QCOMPARE(config.nozzle(2).name, QString());
    QVERIFY(!config.setNozzleName(5, "Nowhere", 0, &why));
    QVERIFY2(why.contains("was not added"), qPrintable(why));
}

// Machine Config: a Name column, a Name field with Save name for the picked nozzle, and a Name field of Add a nozzle
void Smart210Test::theMachineConfigPageShowsTypesAndSavesTheNameOfANozzle()
{
    setUpLoggedInTerminalWithAPin();                                         // nozzles 1 and 2 (diesel) were added; tank T1 holds diesel
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    window.showPage("settings");
    window.showPage("pin");
    window.findChild<QLineEdit *>("pinField")->setText("4821");
    window.findChild<QPushButton *>("pinOkButton")->click();
    QCOMPARE(window.currentPage(), QString("machine"));

    QTableWidget *table = window.findChild<QTableWidget *>("nozzleTable");
    QLineEdit *name = window.findChild<QLineEdit *>("nozzleName");
    QPushButton *save = window.findChild<QPushButton *>("saveNozzleNameButton");
    QLabel *selected = window.findChild<QLabel *>("selectedNozzle");
    QLabel *status = window.findChild<QLabel *>("machineStatus");
    QVERIFY(table != 0 && name != 0 && save != 0 && selected != 0);
    QCOMPARE(table->horizontalHeaderItem(0)->text(), QString("Nozzle"));
    QCOMPARE(table->horizontalHeaderItem(1)->text(), QString("Name"));      // the Name column comes right after the number
    QCOMPARE(table->item(0, 1)->text(), QString());                          // no name yet
    QCOMPARE(name->maxLength(), 50);

    // the right side has two groups, the Name group (the field and Save name) above the Config group (mode, fuel type, density, pulses, Update, Read)
    QGroupBox *nameBox = window.findChild<QGroupBox *>("nozzleNameBox");
    QGroupBox *configBox = window.findChild<QGroupBox *>("nozzleConfigBox");
    QVERIFY(nameBox != 0 && configBox != 0);
    QCOMPARE(nameBox->title(), QString("Name"));
    QCOMPARE(configBox->title(), QString("Config"));
    QVERIFY(nameBox->isAncestorOf(name) && nameBox->isAncestorOf(save));
    foreach (const char *field, QList<const char *>() << "nozzleMode" << "nozzleFuel" << "nozzleDensity" << "nozzlePulses" << "updateNozzleButton" << "readNozzlesButton") {
        QVERIFY2(configBox->isAncestorOf(window.findChild<QWidget *>(field)), field);
        QVERIFY2(!nameBox->isAncestorOf(window.findChild<QWidget *>(field)), field);
    }
    QLayout *column = window.findChild<QLayout *>("formColumn");
    QVERIFY(column != 0);
    QCOMPARE(column->itemAt(1)->widget(), static_cast<QWidget *>(nameBox));      // after the label of the picked nozzle: the Name group, then the Config group
    QCOMPARE(column->itemAt(2)->widget(), static_cast<QWidget *>(configBox));
    QFormLayout *form = window.findChild<QFormLayout *>("nozzleForm");
    QVERIFY(form != 0);
    QCOMPARE(form->fieldGrowthPolicy(), QFormLayout::AllNonFixedFieldsGrow);     // the fields of the config use the width that there is
    int row = -1;
    QFormLayout::ItemRole role;
    QList<int> rows;
    foreach (const char *field, QList<const char *>() << "nozzleMode" << "nozzleFuel" << "nozzleDensity" << "nozzlePulses") {
        QWidget *edit = window.findChild<QWidget *>(field);
        QCOMPARE(edit->sizePolicy().horizontalPolicy(), QSizePolicy::Expanding);
        QVERIFY2(edit->maximumWidth() > 400, field);                                 // no narrow limit: it takes the width of the group
        form->getWidgetPosition(edit, &row, &role);
        rows << row;
    }
    QCOMPARE(rows, QList<int>() << 0 << 1 << 2 << 3);
    // pick a nozzle, type its name, Save name: the table, the label of the form and the change log show it; the web app is told
    table->selectRow(0);
    QCOMPARE(selected->text(), QString("Nozzle 1"));
    name->setText("  Diesel 1 ");
    save->click();
    QVERIFY2(status->text().contains("Nozzle 1: the name was saved"), qPrintable(status->text()));
    QCOMPARE(table->item(0, 1)->text(), QString("Diesel 1"));
    QCOMPARE(selected->text(), QString("Nozzle 1: Diesel 1"));
    QCOMPARE(name->text(), QString("Diesel 1"));
    QCOMPARE(one("SELECT name FROM nozzles WHERE number = 1").toString(), QString("Diesel 1"));
    QCOMPARE(one("SELECT synced FROM nozzles WHERE number = 1").toInt(), 0);
    QVERIFY(window.findChild<QListWidget *>("changeLog")->item(0)->text().contains("Nozzle 1: name - -> name Diesel 1"));
    QCOMPARE(table->item(1, 1)->text(), QString());                          // the other nozzle is untouched

    // an empty name takes it away
    name->clear();
    save->click();
    QCOMPARE(table->item(0, 1)->text(), QString());
    QCOMPARE(selected->text(), QString("Nozzle 1"));

    // Add a nozzle with a name
    QComboBox *number = window.findChild<QComboBox *>("addNozzleNumber");
    QLineEdit *addName = window.findChild<QLineEdit *>("addNozzleName");
    QVERIFY(addName != 0);
    QCOMPARE(addName->maxLength(), 50);
    number->setCurrentIndex(number->findText("3"));
    QComboBox *addFuel = window.findChild<QComboBox *>("addNozzleFuel");
    addFuel->setCurrentIndex(addFuel->findData("D"));
    addFuel->activated(addFuel->currentIndex());                             // the tank of diesel follows
    addName->setText("Diesel 3");
    window.findChild<QPushButton *>("addNozzleButton")->click();
    QVERIFY2(status->text().contains("Nozzle 3 was added"), qPrintable(status->text()));
    QCOMPARE(table->rowCount(), 3);
    QCOMPARE(table->item(2, 0)->text(), QString("3"));
    QCOMPARE(table->item(2, 1)->text(), QString("Diesel 3"));
    QCOMPARE(selected->text(), QString("Nozzle 3: Diesel 3"));
    QCOMPARE(addName->text(), QString());                                    // ready for the next nozzle
}

// 007: a terminal database that is at version 6 (the cards table has its phone number, the nozzles have no name) gets the column and keeps its rows
void Smart210Test::anOldDatabaseGetsTheNameOfANozzleAndKeepsItsRows()
{
    QSqlDatabase db = openDatabase(false);
    const QStringList all = Migrator::bundled();
    QCOMPARE(all.size(), 12);
    QVERIFY(all.at(6).contains("ALTER TABLE nozzles ADD COLUMN name"));

    QString error;
    QVERIFY2(Migrator::apply(db, all.mid(0, 6), &error), qPrintable(error));
    QCOMPARE(Migrator::version(db), 6);
    QVERIFY(!migratedColumnsOf(db, "nozzles").contains("name"));            // the old database
    run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (1, 'T1', 'D', 5000000, 100000)");
    run("INSERT INTO nozzles (id, number, tank_id, fuel_type, board_address, synced, deleted, mode, density, seen_at) VALUES (10, 1, 1, 'D', 1, 1, 0, 0, 8300, '2026-10-08 09:00:00')");

    QVERIFY2(Migrator::apply(db, all.mid(0, 7), &error), qPrintable(error));
    QCOMPARE(Migrator::version(db), 7);
    QVERIFY(migratedColumnsOf(db, "nozzles").contains("name"));
    QCOMPARE(one("SELECT COUNT(*) FROM nozzles WHERE number = 1 AND fuel_type = 'D' AND density = 8300 AND name IS NULL").toInt(), 1);   // the nozzle is as it was, without a name

    MachineConfig config(db);
    QCOMPARE(config.nozzle(1).label(), QString("Nozzle 1"));
    QVERIFY(config.setNozzleName(1, "Diesel 1", 0, &error));
    QCOMPARE(config.nozzle(1).label(), QString("Diesel 1"));
}

// 008: a terminal database at version 7 gets the card of a ticket and keeps its tickets
void Smart210Test::anOldDatabaseGetsTheCardOfATicketAndKeepsItsTickets()
{
    QSqlDatabase db = openDatabase(false);
    const QStringList all = Migrator::bundled();
    QVERIFY(all.at(7).contains("ALTER TABLE tickets ADD COLUMN card_id"));

    QString error;
    QVERIFY2(Migrator::apply(db, all.mid(0, 7), &error), qPrintable(error));
    QCOMPARE(Migrator::version(db), 7);
    QVERIFY(!migratedColumnsOf(db, "tickets").contains("card_id"));         // the old database
    run("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (3000000041, 'D', 0, 1500, 1, 1)");

    QVERIFY2(Migrator::apply(db, all.mid(0, 8), &error), qPrintable(error));
    QCOMPARE(Migrator::version(db), 8);
    QVERIFY(migratedColumnsOf(db, "tickets").contains("card_id"));
    QCOMPARE(one("SELECT COUNT(*) FROM tickets WHERE barcode = 3000000041 AND amount_kg = 1500 AND status = 1 AND card_id IS NULL").toInt(), 1);   // the ticket is as it was
}

void Smart210Test::aNozzleWithAFillOrQueuedTicketsIsNeitherUpdatedNorRemovedAndAnAddNeedsATank()
{
    setUpLoggedInTerminalWithAPin();                                         // nozzles 1 and 2 were added; tank T1 holds diesel
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    window.showPage("settings");
    window.showPage("pin");
    window.findChild<QLineEdit *>("pinField")->setText("4821");
    window.findChild<QPushButton *>("pinOkButton")->click();
    QCOMPARE(window.currentPage(), QString("machine"));
    QTableWidget *table = window.findChild<QTableWidget *>("nozzleTable");
    QLabel *status = window.findChild<QLabel *>("machineStatus");
    QPushButton *update = window.findChild<QPushButton *>("updateNozzleButton");
    QPushButton *remove = qobject_cast<QPushButton *>(table->cellWidget(0, 8));
    table->selectRow(0);
    window.findChild<QLineEdit *>("nozzlePulses")->setText("1500");
    boxAnswer_ = QMessageBox::Ok;

    // tickets are queued on nozzle 1: nothing is saved, nothing is removed, and the reason is shown in a box
    run("INSERT INTO supply_queue (barcode, nozzle_number, amount_kg, position, added_at) VALUES (1000000000000001, 1, 500, 1, '2026-10-24 10:00:00')");
    boxText_.clear();
    QTimer::singleShot(50, this, SLOT(answerTheMessageBox()));
    update->click();
    QVERIFY2(boxText_.contains("Tickets are queued on nozzle 1"), qPrintable(boxText_));
    QCOMPARE(table->item(0, 6)->text(), QString("1200.00"));                // not saved
    QVERIFY(status->text().contains("Tickets are queued"));
    boxText_.clear();
    QTimer::singleShot(50, this, SLOT(answerTheMessageBox()));
    remove->click();
    QVERIFY2(boxText_.contains("Tickets are queued on nozzle 1"), qPrintable(boxText_));
    QCOMPARE(table->rowCount(), 2);                                          // still there (and no question was asked)

    // a fill is open on it
    run("DELETE FROM supply_queue");
    QVERIFY(SupplyQueue::begin(QSqlDatabase::database(connection_), 1, 1000, QDateTime::currentDateTime()));
    boxText_.clear();
    QTimer::singleShot(50, this, SLOT(answerTheMessageBox()));
    update->click();
    QVERIFY2(boxText_.contains("A fill is open on nozzle 1"), qPrintable(boxText_));
    QCOMPARE(table->item(0, 6)->text(), QString("1200.00"));
    boxText_.clear();
    QTimer::singleShot(50, this, SLOT(answerTheMessageBox()));
    remove->click();
    QVERIFY2(boxText_.contains("A fill is open on nozzle 1"), qPrintable(boxText_));
    QCOMPARE(table->rowCount(), 2);

    // when the fill is over the update goes through (saved here; there is no board in this test)
    SupplyQueue::end(QSqlDatabase::database(connection_), 1);
    update->click();
    QVERIFY2(status->text().startsWith("Saved."), qPrintable(status->text()));
    QCOMPARE(table->item(0, 6)->text(), QString("1500.00"));

    // an Add needs a tank: no tank holds this fuel type, so it is refused with a box
    QComboBox *fuel = window.findChild<QComboBox *>("addNozzleFuel");
    QComboBox *number = window.findChild<QComboBox *>("addNozzleNumber");
    QComboBox *tank = window.findChild<QComboBox *>("addNozzleTank");
    fuel->setCurrentIndex(fuel->findData("A"));
    fuel->activated(fuel->currentIndex());
    QCOMPARE(tank->count(), 0);
    number->setCurrentIndex(number->findText("4"));
    boxText_.clear();
    QTimer::singleShot(50, this, SLOT(answerTheMessageBox()));
    window.findChild<QPushButton *>("addNozzleButton")->click();
    QVERIFY2(boxText_.contains("must be connected to a tank"), qPrintable(boxText_));
    QCOMPARE(table->rowCount(), 2);
}

void Smart210Test::aNozzleWithoutATankIsReportedWhenMachineConfigOpens()
{
    setUpLoggedInTerminalWithAPin();                                         // nozzles 1 and 2 have the tank T1
    run("UPDATE nozzles SET tank_id = 0 WHERE number = 2");                  // one added before a tank was required
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    window.showPage("settings");
    window.showPage("pin");
    window.findChild<QLineEdit *>("pinField")->setText("4821");
    window.findChild<QPushButton *>("pinOkButton")->click();
    QCOMPARE(window.currentPage(), QString("machine"));

    QLabel *status = window.findChild<QLabel *>("machineStatus");
    QVERIFY2(status->text().contains("Nozzle(s) 2 have no tank"), qPrintable(status->text()));
    QCOMPARE(window.findChild<QTableWidget *>("nozzleTable")->item(1, 7)->text(), QString("(no tank)"));
}

void Smart210Test::aBoardsConfigBecomesTheNozzlesConfig()
{
    QSqlDatabase db = openDatabase();
    run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (1, 'T1', 'D', 5000000, 100000)");
    run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (2, 'T2', 'A', 5000000, 100000)");
    MachineConfig config(db);
    addNozzleRows(QList<int>() << 2);

    QVERIFY(config.storeBoardConfig(2, boardConfig("D", 8300, 123450, BoardProtocol::ModeManual)));

    MachineConfig::Nozzle nozzle = config.nozzle(2);
    QVERIFY(nozzle.seen());
    QCOMPARE(nozzle.fuelType, QString("D"));
    QCOMPARE(nozzle.mode, int(BoardProtocol::ModeManual));
    QCOMPARE(nozzle.density, qlonglong(8300));
    QCOMPARE(nozzle.pulsesPerLiter, qlonglong(123450));
    QCOMPARE(nozzle.tankId, 1);          // the only tank with this fuel type
    QCOMPARE(nozzle.tank, QString("T1"));
    QVERIFY(!config.nozzle(1).seen());   // the others stay blank
    QCOMPARE(Uploader::pending(db).value("nozzles"), 1);

    // the same config again changes nothing for the web app
    run("UPDATE nozzles SET synced = 1");
    QVERIFY(config.storeBoardConfig(2, boardConfig("D", 8300, 123450, BoardProtocol::ModeManual)));
    QCOMPARE(Uploader::pending(db).value("nozzles"), 0);
    // a change of the pulses, mode or density is the terminal's business only
    QVERIFY(config.storeBoardConfig(2, boardConfig("D", 8400, 130000)));
    QCOMPARE(config.nozzle(2).pulsesPerLiter, qlonglong(130000));
    QCOMPARE(Uploader::pending(db).value("nozzles"), 0);
    QCOMPARE(countRows("nozzles"), 1);                  // a board's answer makes no nozzle: only the added one is kept
    // another fuel type: the tank follows, and the web app is told
    QVERIFY(config.storeBoardConfig(2, boardConfig("A", 7400, 130000)));
    QCOMPARE(config.nozzle(2).tankId, 2);
    QCOMPARE(Uploader::pending(db).value("nozzles"), 1);

    // a board straight from the factory: nothing set yet
    addNozzleRows(QList<int>() << 3);
    QVERIFY(config.storeBoardConfig(3, BoardProtocol::BoardConfig()));
    QVERIFY(config.nozzle(3).seen());
    QCOMPARE(config.nozzle(3).density, qlonglong(-1));
    QCOMPARE(config.nozzle(3).pulsesPerLiter, qlonglong(-1));
    QCOMPARE(config.nozzle(3).tankId, 0);

    QVERIFY(!config.storeBoardConfig(0, boardConfig("D", 8300, 100000)));
    QVERIFY(!config.storeBoardConfig(7, boardConfig("D", 8300, 100000))); // there are only six
}

void Smart210Test::theMachineConfigurationLivesInTheDeviceFileNotInTheDatabase()
{
    QSqlDatabase db = openDatabase();
    Settings settings(db);
    QVERIFY(settings.setValue(SettingKey::ServerHost, "192.168.1.5"));
    QVERIFY(settings.setInt(SettingKey::ServerPort, 8080));
    QVERIFY(settings.setValue(SettingKey::PrinterHost, "192.168.1.9"));
    QVERIFY(settings.setInt(SettingKey::ConverterPort, 4002));
    QVERIFY(settings.setValue(SettingKey::DeviceName, "POS1"));
    QVERIFY(settings.setValue(SettingKey::PurgeDays, "3")); // not machine configuration: the database keeps it

    QCOMPARE(settings.value(SettingKey::ServerHost), QString("192.168.1.5"));
    QCOMPARE(settings.intValue(SettingKey::ServerPort), 8080);
    QVERIFY(settings.contains(SettingKey::PrinterHost));
    QCOMPARE(countRows("settings"), 1);
    QCOMPARE(one("SELECT key FROM settings").toString(), QString("purge_days"));

    // a terminal made before: the database still holds a value, it is found, and saving moves it to the file
    run("INSERT INTO settings (key, value) VALUES ('printer_port', '9101')");
    QCOMPARE(settings.intValue(SettingKey::PrinterPort), 9101);
    QVERIFY(settings.setInt(SettingKey::PrinterPort, 9102));
    QCOMPARE(settings.intValue(SettingKey::PrinterPort), 9102);
    QCOMPARE(countRows("settings"), 1);

    QVERIFY(settings.remove(SettingKey::PrinterHost));
    QVERIFY(!settings.contains(SettingKey::PrinterHost));
}

void Smart210Test::aNewDatabaseGetsTheMachineConfigurationBack()
{
    {
        QSqlDatabase db = openDatabase();
        run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (1, 'T1', 'D', 5000000, 100000)");
        run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (2, 'T2', 'D', 5000000, 100000)");
        Settings settings(db);
        settings.setValue(SettingKey::ServerHost, "192.168.1.5");
        settings.setInt(SettingKey::ServerPort, 8080);
        settings.setValue(SettingKey::PrinterHost, "192.168.1.9");
        MachineConfig config(db);
        addNozzleRows(QList<int>() << 1);
        QVERIFY(config.storeBoardConfig(1, boardConfig("D", 8300, 100000)));
        QVERIFY(config.setTank(1, 2));
    }

    // the SD card is replaced: an empty database, the same device file
    Database::close(connection_);
    QVERIFY(QFile::remove(dir_->path() + "/data/smart210.db"));
    connection_ = connection_ + "_new";
    QSqlDatabase fresh = openDatabase();
    QCOMPARE(countRows("settings"), 0);
    Settings settings(fresh);
    QCOMPARE(settings.value(SettingKey::ServerHost), QString("192.168.1.5"));
    QCOMPARE(settings.intValue(SettingKey::ServerPort), 8080);
    QCOMPARE(settings.value(SettingKey::PrinterHost), QString("192.168.1.9"));

    // the tanks come with the first download, the board answers: the nozzle gets the tank it had
    run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (1, 'T1', 'D', 5000000, 100000)");
    run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (2, 'T2', 'D', 5000000, 100000)");
    MachineConfig config(fresh);
    addNozzleRows(QList<int>() << 1); // added again by hand on the new card
    QVERIFY(config.storeBoardConfig(1, boardConfig("D", 8300, 100000)));
    QCOMPARE(config.nozzle(1).tankId, 2);
}

void Smart210Test::theTankIsKeptOrPickedByFuelType()
{
    QSqlDatabase db = openDatabase();
    run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (1, 'T1', 'D', 5000000, 100000)");
    run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (2, 'T2', 'D', 5000000, 100000)");
    run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (3, 'T3', 'A', 5000000, 100000)");
    MachineConfig config(db);
    addNozzleRows(QList<int>() << 1);

    QVERIFY(config.storeBoardConfig(1, boardConfig("D", 8300, 100000)));
    QCOMPARE(config.nozzle(1).tankId, 0);                 // two tanks hold diesel: the technician picks
    QCOMPARE(Uploader::pending(db).value("nozzles"), 0);  // and without a tank nothing is uploaded
    QCOMPARE(config.tanks().size(), 3);
    QCOMPARE(config.tanks("D").size(), 2);

    QVERIFY(!config.setTank(1, 3));   // gasoline in a diesel nozzle
    QVERIFY(!config.setTank(1, 99));
    QVERIFY(!config.setTank(4, 1));   // a nozzle whose board never answered
    QVERIFY(config.setTank(1, 2));
    QCOMPARE(config.nozzle(1).tank, QString("T2"));
    QCOMPARE(Uploader::pending(db).value("nozzles"), 1);

    QVERIFY(config.storeBoardConfig(1, boardConfig("D", 8300, 110000))); // the board answers again: the pick stays
    QCOMPARE(config.nozzle(1).tankId, 2);
    QVERIFY(config.storeBoardConfig(1, boardConfig("A", 7400, 110000))); // another fuel: the only gasoline tank
    QCOMPARE(config.nozzle(1).tankId, 3);
}

void Smart210Test::theNozzleLineForTheLogIsInNoLanguage()
{
    MachineConfig::Nozzle nozzle;
    QCOMPARE(MachineConfig::describe(nozzle), QString()); // never answered: nothing to say

    nozzle.number = 1;
    nozzle.seenAt = "2026-10-08 09:00:00";
    nozzle.fuelType = "D";
    nozzle.density = 8300;
    nozzle.pulsesPerLiter = 123450;
    nozzle.tank = "T1";
    QCOMPARE(MachineConfig::describe(nozzle), QString("auto D 0.8300 1234.50 T1"));
    nozzle.mode = BoardProtocol::ModeManual;
    nozzle.tank.clear();
    nozzle.density = -1;
    QCOMPARE(MachineConfig::describe(nozzle), QString("manual D - 1234.50"));
}

void Smart210Test::whatTheBoardsReportedSurvivesAConfigDownload()
{
    QSqlDatabase db = openDatabase();
    SyncStore store(db);
    QString error;
    QVERIFY(store.applyConfig(configObject(), &error)); // brings tank 1 (diesel)
    MachineConfig config(db);
    addNozzleRows(QList<int>() << 1);
    QVERIFY(config.storeBoardConfig(1, boardConfig("D", 8300, 120000)));

    QVERIFY(store.applyConfig(configObject(), &error)); // the config download does not touch nozzles
    QVERIFY(store.applyConfig(configObject(), &error));

    QVERIFY(config.nozzle(1).seen());
    QCOMPARE(config.nozzle(1).pulsesPerLiter, qlonglong(120000));
    QCOMPARE(config.nozzle(1).tankId, 1);
}

void Smart210Test::nozzlesWithATankAreUploadedFirstAndMarkedUploaded()
{
    configureServer();
    run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (1, 'T1', 'D', 5000000, 100000)");
    addNozzleRows(QList<int>() << 2 << 5);
    MachineConfig config(openDatabase());
    QVERIFY(config.storeBoardConfig(2, boardConfig("D", 8300, 120000)));
    QVERIFY(config.storeBoardConfig(5, boardConfig("X", 8300, 120000))); // no tank holds fuel X: not uploaded
    QCOMPARE(Uploader::pending(QSqlDatabase::database(connection_)).value("nozzles"), 1);
    server_->reply("/api/push/nozzles", 200, q("{'status':'ok','saved':['2'],'rejected':[]}"));
    Uploader uploader(QSqlDatabase::database(connection_));
    QString message;

    QVERIFY(uploadOk(uploader, &message));

    QCOMPARE(server_->log(), QStringList() << "POST /api/push/nozzles");
    const QJsonArray rows = QJsonDocument::fromJson(server_->requests().at(0).body).object().value("rows").toArray();
    QCOMPARE(rows.size(), 1);
    QCOMPARE(rows.at(0).toObject().value("number").toInt(), 2);
    QCOMPARE(rows.at(0).toObject().value("tank_id").toInt(), 1);
    QCOMPARE(rows.at(0).toObject().value("board_address").toInt(), 2); // the address of the board is the number
    QCOMPARE(rows.at(0).toObject().value("deleted").toBool(), false);
    QVERIFY(!rows.at(0).toObject().contains("fuel_type"));
    QCOMPARE(Uploader::pending(QSqlDatabase::database(connection_)).value("nozzles"), 0);
}

void Smart210Test::aNozzleTheWebAppRefusedIsNotSentAgainByItselfAndDoesNotHoldBackTheRecords()
{
    configureServer();
    run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (1, 'T1', 'D', 5000000, 100000)");
    addNozzleRows(QList<int>() << 2);
    run("UPDATE nozzles SET tank_id = 1 WHERE number = 2");
    QSqlDatabase db = QSqlDatabase::database(connection_);
    server_->reply("/api/push/nozzles", 200, q("{'status':'ok','saved':[],'rejected':[{'key':'2','error':'unknown tank'}]}"));
    Uploader uploader(db);
    QSignalSpy sent(&uploader, SIGNAL(nozzlesUploaded(bool)));
    QCOMPARE(Uploader::nozzlesWaiting(db), 1);

    uploader.uploadNozzles();
    QTRY_COMPARE_WITH_TIMEOUT(sent.size(), 1, 10000);
    QCOMPARE(server_->log(), QStringList() << "POST /api/push/nozzles");
    QCOMPARE(Uploader::nozzleRefusal(db, 2), QString("unknown tank"));        // the reason is kept (Machine Config shows it)

    // refused: it waits, but the automatic upload does not count it (it cannot hold the records back) and does not send it again
    QCOMPARE(Uploader::pending(db).value("nozzles"), 1);
    QCOMPARE(Uploader::nozzlesWaiting(db), 0);
    uploader.uploadNozzles();
    QTRY_COMPARE_WITH_TIMEOUT(sent.size(), 2, 10000);
    QCOMPARE(server_->log().size(), 1);                                        // nothing went

    // something was changed on Machine Config: it is tried once more, and a good answer ends it
    Uploader::forgetNozzleRefusals(db);
    QCOMPARE(Uploader::nozzlesWaiting(db), 1);
    server_->reply("/api/push/nozzles", 200, q("{'status':'ok','saved':['2'],'rejected':[]}"));
    uploader.uploadNozzles(true);
    QTRY_COMPARE_WITH_TIMEOUT(sent.size(), 3, 10000);
    QCOMPARE(server_->log().size(), 2);
    QCOMPARE(Uploader::nozzlesWaiting(db), 0);
    QCOMPARE(Uploader::pending(db).value("nozzles"), 0);
    QCOMPARE(Uploader::nozzleRefusal(db, 2), QString());
}

// The boards of the tests: 1 to 3 answer, the others are not there.
namespace {

struct NozzleRig {
    explicit NozzleRig(QSqlDatabase db) : sync(db, &bus)
    {
        bus.setCycleMs(5);
        bus.setAnswerTimeoutMs(60);
        bus.setOfflineRetryMs(100);
        bus.setMissLimit(3);
    }

    FakeBoards boards;
    BoardBus bus;
    NozzleConfigSync sync;
};

}

void Smart210Test::aBoardAtAnAddressThatWasNotAddedIsNotAskedAndMakesNoNozzle()
{
    QSqlDatabase db = openDatabase();
    run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (1, 'T1', 'D', 5000000, 100000)");
    run("INSERT INTO fuel_types (fuel_type, name, price, density) VALUES ('D', 'Diesel', 750, 8300)");
    addNozzleRows(QList<int>() << 1);                                        // only nozzle 1 was added
    NozzleRig rig(db);
    rig.boards.add(1);
    rig.boards.add(2);                                                       // board 2 is there and answers, but nobody added nozzle 2
    QVERIFY(rig.sync.start(freePort()));
    rig.boards.connectTo(quint16(rig.bus.listenPort()));
    MachineConfig config(db);

    QTRY_VERIFY_WITH_TIMEOUT(rig.bus.isOnline(1), 5000);
    QTest::qWait(500);
    QVERIFY(!rig.bus.isOnline(2));                                           // it is not even asked
    QVERIFY(!config.isConfigured(2));
    QCOMPARE(config.nozzles().size(), 1);
    QCOMPARE(countRows("nozzles"), 1);
    QVERIFY(!rig.bus.getConfig(2));                                          // and no command goes to it
    QVERIFY(!rig.sync.read(2));

    // the technician adds nozzle 2: its board is polled from then on (its config is not read by itself)
    QString why;
    QVERIFY2(config.addNozzle(2, "D", 0, 0, &why), qPrintable(why));
    rig.sync.syncBoards();
    QTRY_VERIFY_WITH_TIMEOUT(rig.bus.isOnline(2), 5000);
    QTest::qWait(300);
    QVERIFY(!config.nozzle(2).seen());

    // removed: switched off again, offline at once
    QVERIFY(config.removeNozzle(2, 0, &why));
    rig.sync.syncBoards();
    QVERIFY(!rig.bus.isOnline(2));
    QTest::qWait(300);
    QVERIFY(!rig.bus.isOnline(2));
    QVERIFY(rig.bus.isOnline(1));
}

void Smart210Test::aNewNozzleHoldsItsFuelTypeTankAndDensityAndItsConfigIsSentLater()
{
    QSqlDatabase db = openDatabase();
    run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (1, 'T1 Gasoline', 'A', 5000000, 100000)");
    run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (2, 'T2 Diesel', 'D', 5000000, 100000)");
    run("INSERT INTO fuel_types (fuel_type, name, price, density) VALUES ('D', 'Diesel', 750, 8300)");
    run("INSERT INTO fuel_types (fuel_type, name, price, density) VALUES ('A', 'Gasoline', 800, 7400)");
    MachineConfig config(db);
    config.setLogFile(changeLog());
    QString why;
    // the technician adds nozzle 1 as Gasoline on the gasoline tank; its board still holds Diesel (a configured board: auto, D, 0.8300, 1000.00)
    QVERIFY2(config.addNozzle(1, "A", 1, 0, &why), qPrintable(why));
    NozzleRig rig(db);
    rig.boards.add(1);
    QVERIFY(rig.sync.start(freePort()));
    rig.boards.connectTo(quint16(rig.bus.listenPort()));
    QTRY_VERIFY_WITH_TIMEOUT(rig.sync.isOnline(1), 5000);
    QTest::qWait(300);

    // the nozzle holds what was chosen, the density of its fuel type, no pulses yet; nothing was read from the board and the board is untouched
    MachineConfig::Nozzle nozzle = config.nozzle(1);
    QCOMPARE(nozzle.fuelType, QString("A"));
    QCOMPARE(nozzle.tankId, 1);
    QCOMPARE(nozzle.density, qlonglong(7400));
    QCOMPARE(nozzle.pulsesPerLiter, qlonglong(-1));
    QVERIFY(!nozzle.seen());                                                     // its config is not set yet
    QCOMPARE(rig.boards.board(1).config.fuelType, QString("D"));
    QVERIFY(!rig.boards.board(1).commands.contains(int(BoardProtocol::CmdGetConfig)));
    QVERIFY(!rig.boards.board(1).commands.contains(int(BoardProtocol::CmdSetConfig)));

    // Update: the config is saved here first (the nozzle has it at once), then the board gets Gasoline and verifies it
    QSignalSpy done(&rig.sync, SIGNAL(updateDone(int)));
    BoardProtocol::BoardConfig wanted = boardConfig("A", 7400, 120000);
    QVERIFY(config.saveConfig(1, wanted, 0));
    QVERIFY(config.nozzle(1).seen());
    QCOMPARE(rig.boards.board(1).config.fuelType, QString("D"));              // the board has not been told yet
    QVERIFY(rig.sync.send(1, wanted));
    QTRY_COMPARE_WITH_TIMEOUT(done.size(), 1, 5000);
    QCOMPARE(rig.boards.board(1).config.fuelType, QString("A"));
    QCOMPARE(rig.boards.board(1).config.pulsesX100, quint32(120000));
    nozzle = config.nozzle(1);
    QVERIFY(nozzle.seen());
    QCOMPARE(nozzle.fuelType, QString("A"));
    QCOMPARE(nozzle.tankId, 1);
    QCOMPARE(nozzle.pulsesPerLiter, qlonglong(120000));
}

void Smart210Test::nothingIsReadFromABoardUntilItIsAskedFor()
{
    QSqlDatabase db = openDatabase();
    run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (1, 'T1', 'D', 5000000, 100000)");
    addNozzleRows(QList<int>() << 1 << 2 << 3);
    NozzleRig rig(db);
    rig.boards.add(1);
    rig.boards.add(2);
    rig.boards.add(3);
    QVERIFY(rig.sync.start(freePort()));
    rig.boards.connectTo(quint16(rig.bus.listenPort()));
    MachineConfig config(db);

    QTRY_VERIFY_WITH_TIMEOUT(rig.sync.isOnline(1) && rig.sync.isOnline(2) && rig.sync.isOnline(3), 5000);
    QTest::qWait(500);
    QVERIFY(rig.sync.isLinked());
    QVERIFY(!rig.sync.isOnline(4));
    for (int number = 1; number <= 3; ++number) {                                  // they are polled, and never asked for their config
        QVERIFY(!config.nozzle(number).seen());
        QVERIFY(!rig.boards.board(number).commands.contains(int(BoardProtocol::CmdGetConfig)));
    }

    // Read asks one board, and what it holds becomes the nozzle's config
    QSignalSpy done(&rig.sync, SIGNAL(readDone(int)));
    QVERIFY(rig.sync.read(1));
    QTRY_COMPARE_WITH_TIMEOUT(done.size(), 1, 5000);
    QCOMPARE(done.at(0).at(0).toInt(), 1);
    QVERIFY(config.nozzle(1).seen());
    QCOMPARE(config.nozzle(1).fuelType, QString("D"));
    QCOMPARE(config.nozzle(1).density, qlonglong(8300));
    QCOMPARE(config.nozzle(1).pulsesPerLiter, qlonglong(100000));
    QCOMPARE(config.nozzle(1).tank, QString("T1"));
    QVERIFY(!config.nozzle(2).seen());                                             // the others were not asked
    QVERIFY(!rig.boards.board(2).commands.contains(int(BoardProtocol::CmdGetConfig)));
    QVERIFY(!rig.sync.read(4));                                                    // not added
}

void Smart210Test::aBlankBoardIsNotSavedWhenItIsRead()
{
    QSqlDatabase db = openDatabase();
    addNozzleRows(QList<int>() << 1);
    NozzleRig rig(db);
    rig.boards.addBlank(1);
    QVERIFY(rig.sync.start(freePort()));
    rig.boards.connectTo(quint16(rig.bus.listenPort()));
    MachineConfig config(db);
    QTRY_VERIFY_WITH_TIMEOUT(rig.sync.isOnline(1), 5000);
    QSignalSpy done(&rig.sync, SIGNAL(readDone(int)));
    QSignalSpy failed(&rig.sync, SIGNAL(readFailed(int,QString)));

    QVERIFY(rig.sync.read(1));

    QTRY_COMPARE_WITH_TIMEOUT(failed.size(), 1, 5000);
    QCOMPARE(done.size(), 0);
    QVERIFY(failed.at(0).at(1).toString().contains("no config"));
    QVERIFY(!config.nozzle(1).seen());                                             // the terminal's own config stays as it was
    QCOMPARE(config.nozzle(1).pulsesPerLiter, qlonglong(-1));
}

void Smart210Test::aConfigChangeIsSentVerifiedAndKept()
{
    QSqlDatabase db = openDatabase();
    run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (1, 'T1', 'D', 5000000, 100000)");
    run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (2, 'T2', 'D', 5000000, 100000)");
    addUser(7, "Cash One", "secret1");
    addNozzleRows(QList<int>() << 1);
    NozzleRig rig(db);
    rig.sync.setLogFile(changeLog());
    rig.boards.add(1);
    QVERIFY(rig.sync.start(freePort()));
    rig.boards.connectTo(quint16(rig.bus.listenPort()));
    MachineConfig config(db);
    config.setLogFile(changeLog());
    QTRY_VERIFY_WITH_TIMEOUT(rig.sync.isOnline(1), 5000);
    QSignalSpy done(&rig.sync, SIGNAL(updateDone(int)));
    QSignalSpy failed(&rig.sync, SIGNAL(updateFailed(int,QString)));

    const BoardProtocol::BoardConfig wanted = boardConfig("D", 8250, 123450, BoardProtocol::ModeManual);
    QVERIFY(config.saveConfig(1, wanted, 7));
    QVERIFY(rig.sync.send(1, wanted));

    QTRY_COMPARE_WITH_TIMEOUT(done.size(), 1, 5000);
    QCOMPARE(failed.size(), 0);
    QCOMPARE(rig.boards.board(1).config.pulsesX100, quint32(123450)); // the board has it
    QCOMPARE(rig.boards.board(1).config.mode, int(BoardProtocol::ModeManual));
    const MachineConfig::Nozzle nozzle = config.nozzle(1);
    QVERIFY(nozzle.seen());
    QCOMPARE(nozzle.pulsesPerLiter, qlonglong(123450));                // and so has the terminal
    QCOMPARE(nozzle.density, qlonglong(8250));
    QCOMPARE(nozzle.mode, int(BoardProtocol::ModeManual));
    // one command: the config was set and verified by the board's own answer, nothing was asked before or after
    QTest::qWait(300);
    QCOMPARE(rig.boards.board(1).commands, QList<int>() << int(BoardProtocol::CmdSetConfig));
    // logged once, with the cashier
    const QList<MachineConfig::Change> log = config.recentChanges(10);
    QCOMPARE(log.size(), 1);
    QCOMPARE(log.at(0).what, QString("nozzle:1"));
    QCOMPARE(log.at(0).userName, QString("Cash One"));
    QCOMPARE(log.at(0).newValue, QString("manual D 0.8250 1234.50"));
}

void Smart210Test::aBoardThatDoesNotKeepTheConfigFailsTheVerification()
{
    QSqlDatabase db = openDatabase();
    addNozzleRows(QList<int>() << 1);
    NozzleRig rig(db);
    rig.sync.setLogFile(changeLog());
    rig.boards.add(1);
    QVERIFY(rig.sync.start(freePort()));
    rig.boards.connectTo(quint16(rig.bus.listenPort()));
    MachineConfig config(db);
    config.setLogFile(changeLog());
    QVERIFY(config.storeBoardConfig(1, boardConfig("D", 8300, 100000)));
    QTRY_VERIFY_WITH_TIMEOUT(rig.sync.isOnline(1), 5000);
    QSignalSpy done(&rig.sync, SIGNAL(updateDone(int)));
    QSignalSpy failed(&rig.sync, SIGNAL(updateFailed(int,QString)));

    rig.boards.board(1).keepsNoConfig = true; // it answers, but holds what it held
    const BoardProtocol::BoardConfig wanted = boardConfig("D", 8300, 150000);
    QVERIFY(config.saveConfig(1, wanted, 0));
    QVERIFY(rig.sync.send(1, wanted));

    QTRY_COMPARE_WITH_TIMEOUT(failed.size(), 1, 5000);
    QCOMPARE(done.size(), 0);
    QVERIFY2(failed.at(0).at(1).toString().contains("did not keep"), qPrintable(failed.at(0).at(1).toString()));
    QCOMPARE(config.nozzle(1).pulsesPerLiter, qlonglong(150000));      // saved here: the terminal's config is what was typed, the board is behind
    QCOMPARE(rig.boards.board(1).config.pulsesX100, quint32(100000));
    QCOMPARE(rig.sync.lastError(1), failed.at(0).at(1).toString());
}

void Smart210Test::aNozzleInManualModeIsOutOfThePollingButItsConfigCanStillBeSentAndRead()
{
    QSqlDatabase db = openDatabase();
    run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (1, 'T1', 'D', 5000000, 100000)");
    addNozzleRows(QList<int>() << 1 << 2);
    MachineConfig config(db);
    QVERIFY(config.storeBoardConfig(1, boardConfig("D", 8300, 100000, BoardProtocol::ModeManual)));   // nozzle 1: manual
    QVERIFY(config.storeBoardConfig(2, boardConfig("D", 8300, 100000, BoardProtocol::ModeAuto)));     // nozzle 2: auto
    NozzleRig rig(db);
    rig.boards.add(1);
    rig.boards.add(2);
    QVERIFY(rig.sync.start(freePort()));
    rig.boards.connectTo(quint16(rig.bus.listenPort()));

    // the auto nozzle is polled, the manual one is not asked at all: not online, no status, no POLL reaches its board
    QTRY_VERIFY_WITH_TIMEOUT(rig.sync.isOnline(2), 5000);
    QTest::qWait(500);
    QVERIFY(rig.sync.isPolled(2));
    QVERIFY(!rig.sync.isPolled(1));
    QVERIFY(!rig.sync.isOnline(1));
    QVERIFY(rig.boards.pollsOf(2) > 3);
    QCOMPARE(rig.boards.pollsOf(1), 0);
    QVERIFY(rig.sync.canTalk(1));                                                // but a command can still be sent to it

    // Read works without polling, and the board stays unpolled
    QSignalSpy read(&rig.sync, SIGNAL(readDone(int)));
    rig.boards.board(1).config.pulsesX100 = 150000;
    rig.boards.board(1).config.mode = BoardProtocol::ModeManual;                 // what the board holds is manual too
    QVERIFY(rig.sync.read(1));
    QTRY_COMPARE_WITH_TIMEOUT(read.size(), 1, 5000);
    QCOMPARE(config.nozzle(1).pulsesPerLiter, qlonglong(150000));
    QVERIFY(!rig.sync.isPolled(1));
    QCOMPARE(rig.boards.pollsOf(1), 0);
    QCOMPARE(rig.boards.board(1).commands, QList<int>() << int(BoardProtocol::CmdGetConfig));

    // Update to auto: the config goes to the unpolled board, then the board is polled
    QSignalSpy done(&rig.sync, SIGNAL(updateDone(int)));
    const BoardProtocol::BoardConfig toAuto = boardConfig("D", 8300, 150000, BoardProtocol::ModeAuto);
    QVERIFY(config.saveConfig(1, toAuto, 0));
    QVERIFY(rig.sync.send(1, toAuto));
    QTRY_COMPARE_WITH_TIMEOUT(done.size(), 1, 5000);
    QTRY_VERIFY_WITH_TIMEOUT(rig.sync.isPolled(1) && rig.sync.isOnline(1), 5000);
    QVERIFY(rig.boards.pollsOf(1) >= 1);

    // Update to manual: sent while it is polled, then it is out of the polling again and offline
    const BoardProtocol::BoardConfig toManual = boardConfig("D", 8300, 150000, BoardProtocol::ModeManual);
    QVERIFY(config.saveConfig(1, toManual, 0));
    QVERIFY(rig.sync.send(1, toManual));
    QTRY_COMPARE_WITH_TIMEOUT(done.size(), 2, 5000);
    QTRY_VERIFY_WITH_TIMEOUT(!rig.sync.isPolled(1) && !rig.sync.isOnline(1), 5000);
    const int polls = rig.boards.pollsOf(1);
    QTest::qWait(400);
    QCOMPARE(rig.boards.pollsOf(1), polls);
    QCOMPARE(rig.boards.board(1).config.mode, int(BoardProtocol::ModeManual));
}

void Smart210Test::aRefusedConfigChangeKeepsTheOldConfig()
{
    QSqlDatabase db = openDatabase();
    addNozzleRows(QList<int>() << 1);
    NozzleRig rig(db);
    rig.sync.setLogFile(changeLog());
    rig.boards.add(1);
    QVERIFY(rig.sync.start(freePort()));
    rig.boards.connectTo(quint16(rig.bus.listenPort()));
    MachineConfig config(db);
    config.setLogFile(changeLog());
    QVERIFY(config.storeBoardConfig(1, boardConfig("D", 8300, 100000)));
    QTRY_VERIFY_WITH_TIMEOUT(rig.sync.isOnline(1), 5000);
    QSignalSpy done(&rig.sync, SIGNAL(updateDone(int)));
    QSignalSpy failed(&rig.sync, SIGNAL(updateFailed(int,QString)));

    rig.boards.board(1).state = BoardProtocol::StateSelected; // a nozzle in use takes no new config
    QVERIFY(rig.sync.send(1, boardConfig("D", 8300, 999900)));

    QTRY_COMPARE_WITH_TIMEOUT(failed.size(), 1, 5000);
    QCOMPARE(done.size(), 0);
    QVERIFY(!failed.at(0).at(1).toString().isEmpty());
    QCOMPARE(config.nozzle(1).pulsesPerLiter, qlonglong(100000)); // the old config stays
    QCOMPARE(rig.boards.board(1).config.pulsesX100, quint32(100000));
    QCOMPARE(config.recentChanges(10).size(), 0);
}

void Smart210Test::aConfigIsNotSentToABoardThatDoesNotAnswerOrWithBadValues()
{
    QSqlDatabase db = openDatabase();
    addNozzleRows(QList<int>() << 1);
    NozzleRig rig(db);
    rig.boards.add(1);
    QVERIFY(rig.sync.start(freePort()));
    rig.boards.connectTo(quint16(rig.bus.listenPort()));
    QTRY_VERIFY_WITH_TIMEOUT(rig.sync.isOnline(1), 5000);

    QVERIFY(!rig.sync.send(5, boardConfig("D", 8300, 100000)));  // no board 5
    QVERIFY(!rig.sync.send(1, boardConfig("D", 0, 100000)));     // density 0
    QVERIFY(!rig.sync.send(1, boardConfig("D", 8300, 0)));       // pulses 0
    QVERIFY(!rig.sync.send(1, boardConfig("", 8300, 100000)));   // no fuel type
    QVERIFY(!rig.sync.send(1, boardConfig("D", 30000, 100000))); // density out of range
    QCOMPARE(rig.boards.board(1).executed, 0);                           // nothing reached the board
}

void Smart210Test::whatABoardChangedByItselfIsPickedUpWhenItIsRead()
{
    QSqlDatabase db = openDatabase();
    run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (1, 'T1', 'D', 5000000, 100000)");
    addNozzleRows(QList<int>() << 2);
    NozzleRig rig(db);
    rig.boards.add(2);
    QVERIFY(rig.sync.start(freePort()));
    rig.boards.connectTo(quint16(rig.bus.listenPort()));
    MachineConfig config(db);
    QVERIFY(config.storeBoardConfig(2, boardConfig("D", 8300, 100000)));
    QTRY_VERIFY_WITH_TIMEOUT(rig.sync.isOnline(2), 5000);

    rig.boards.board(2).config.pulsesX100 = 150000; // changed at the board itself
    QTest::qWait(300);
    QCOMPARE(config.nozzle(2).pulsesPerLiter, qlonglong(100000)); // not noticed by itself
    QSignalSpy done(&rig.sync, SIGNAL(readDone(int)));
    QVERIFY(rig.sync.read(2, 0));

    QTRY_COMPARE_WITH_TIMEOUT(done.size(), 1, 5000);
    QCOMPARE(config.nozzle(2).pulsesPerLiter, qlonglong(150000));
}

void Smart210Test::aReadThatWouldLeaveTheNozzleWithoutATankIsRefused()
{
    QSqlDatabase db = openDatabase();
    run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (1, 'T1', 'D', 5000000, 100000)");
    addNozzleRows(QList<int>() << 1);
    NozzleRig rig(db);
    rig.boards.add(1);
    rig.boards.board(1).config.fuelType = "A";                                  // the board holds gasoline, no tank does
    QVERIFY(rig.sync.start(freePort()));
    rig.boards.connectTo(quint16(rig.bus.listenPort()));
    MachineConfig config(db);
    QVERIFY(config.storeBoardConfig(1, boardConfig("D", 8300, 100000)));
    QTRY_VERIFY_WITH_TIMEOUT(rig.sync.isOnline(1), 5000);
    QSignalSpy failed(&rig.sync, SIGNAL(readFailed(int,QString)));
    rig.sync.setDiffers(1, true);

    QVERIFY(rig.sync.read(1));

    QTRY_COMPARE_WITH_TIMEOUT(failed.size(), 1, 5000);
    QVERIFY(failed.at(0).at(1).toString().contains("connected to a tank"));
    QCOMPARE(config.nozzle(1).fuelType, QString("D"));                          // nothing was saved
    QVERIFY(rig.sync.differs(1));                                               // and what was not confirmed stays marked
}

void Smart210Test::aBoardThatComesBackIsNotAskedForItsConfig()
{
    QSqlDatabase db = openDatabase();
    addNozzleRows(QList<int>() << 1);
    NozzleRig rig(db);
    rig.boards.add(1);
    QVERIFY(rig.sync.start(freePort()));
    rig.boards.connectTo(quint16(rig.bus.listenPort()));
    MachineConfig config(db);
    QVERIFY(config.storeBoardConfig(1, boardConfig("D", 8300, 100000)));
    QTRY_VERIFY_WITH_TIMEOUT(rig.sync.isOnline(1), 5000);
    rig.boards.board(1).silent = true;
    QTRY_VERIFY_WITH_TIMEOUT(!rig.sync.isOnline(1), 5000);
    rig.boards.board(1).config.pulsesX100 = 170000; // while it was away

    rig.boards.board(1).silent = false;

    QTRY_VERIFY_WITH_TIMEOUT(rig.sync.isOnline(1), 5000);
    QTest::qWait(500);
    QCOMPARE(config.nozzle(1).pulsesPerLiter, qlonglong(100000));      // the terminal's config is its own
    QVERIFY(!rig.boards.board(1).commands.contains(int(BoardProtocol::CmdGetConfig)));
}

// ---- the nozzle table of the Machine Config page ----

void Smart210Test::theNozzleTableShowsTheBoardsAndTheirStatus()
{
    setUpLoggedInTerminalWithAPin(); // nozzles 1 and 2 have answered before
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    window.showPage("settings");
    window.showPage("pin");
    window.findChild<QLineEdit *>("pinField")->setText("4821");
    window.findChild<QPushButton *>("pinOkButton")->click();
    QCOMPARE(window.currentPage(), QString("machine"));

    QTableWidget *table = window.findChild<QTableWidget *>("nozzleTable");
    QCOMPARE(table->rowCount(), 2);                                // the two nozzles that were added, not six boards
    QCOMPARE(table->item(0, 0)->text(), QString("1"));
    QCOMPARE(table->item(0, 2)->text(), QString("No converter")); // nothing is connected in this test
    QCOMPARE(table->item(0, 3)->text(), QString("Auto"));
    QCOMPARE(table->item(0, 4)->text(), QString("D"));
    QCOMPARE(table->item(0, 5)->text(), QString("0.8300"));
    QCOMPARE(table->item(0, 6)->text(), QString("1200.00"));
    QCOMPARE(table->item(0, 7)->text(), QString("T1"));
    QCOMPARE(table->item(1, 6)->text(), QString("1234.50"));
    QCOMPARE(table->item(1, 0)->text(), QString("2"));

    // picking a nozzle fills the form: neither the table nor the form changes its width (the text of the label above the form is not what sizes it)
    window.resize(1280, 720);
    window.show();
    window.findChild<QTabWidget *>("machineTabs")->setCurrentIndex(1);
    QTest::qWait(200);
    const int tableWidth = table->width();
    const int formWidth = window.findChild<QLineEdit *>("nozzleDensity")->width();
    table->selectRow(0);
    QTest::qWait(200);
    QCOMPARE(table->width(), tableWidth);
    QCOMPARE(window.findChild<QLineEdit *>("nozzleDensity")->width(), formWidth);
    QCOMPARE(window.findChild<QLineEdit *>("nozzleDensity")->text(), QString("0.8300"));
    QCOMPARE(window.findChild<QLineEdit *>("nozzlePulses")->text(), QString("1200.00"));
    QCOMPARE(window.findChild<QComboBox *>("nozzleFuel")->currentData().toString(), QString("D"));
    QCOMPARE(window.findChild<QComboBox *>("nozzleTank"), static_cast<QComboBox *>(0)); // the tank is the web app's: the form has no field for it
}

void Smart210Test::aBadConfigIsCheckedBeforeItIsSent()
{
    setUpLoggedInTerminalWithAPin();
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    window.showPage("settings");
    window.showPage("pin");
    window.findChild<QLineEdit *>("pinField")->setText("4821");
    window.findChild<QPushButton *>("pinOkButton")->click();
    QLabel *status = window.findChild<QLabel *>("machineStatus");
    QPushButton *send = window.findChild<QPushButton *>("updateNozzleButton");

    send->click();
    QVERIFY(status->text().contains("Pick a nozzle"));

    window.findChild<QTableWidget *>("nozzleTable")->selectRow(0);
    window.findChild<QLineEdit *>("nozzleDensity")->setText("abc");
    send->click();
    QVERIFY(status->text().contains("density"));

    window.findChild<QLineEdit *>("nozzleDensity")->setText("0.8300");
    window.findChild<QLineEdit *>("nozzlePulses")->setText("0");
    send->click();
    QVERIFY(status->text().contains("pulses"));

    window.findChild<QLineEdit *>("nozzlePulses")->setText("1234.5");
    send->click(); // good values, but no board answers: they are saved here all the same
    QVERIFY2(status->text().startsWith("Saved.") && status->text().contains("was not updated"), qPrintable(status->text()));
    QCOMPARE(window.findChild<QTableWidget *>("nozzleTable")->item(0, 6)->text(), QString("1234.50"));
    QCOMPARE(changes().recentChanges(100).size(), 1);
}

void Smart210Test::aChangeIsSentToTheBoardAndShownWhenItConfirms()
{
    setUpLoggedInTerminalWithAPin();
    FakeBoards boards;
    boards.add(1);
    boards.add(2);
    const int port = freePort();
    Settings(QSqlDatabase::database(connection_)).setInt(SettingKey::ConverterPort, port);
    AppConfig config; // the boards are listened to
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    boards.connectTo(quint16(port));
    QTest::qWait(500); // the first download of the terminal brings its tanks: a second diesel tank is added after it
    run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (3, 'T3', 'D', 5000000, 100000)");
    {   // the download also brought the web app's PIN: the test's own is put back
        Settings settings(QSqlDatabase::database(connection_));
        settings.setValue(SettingKey::PinSalt, "ps");
        settings.setValue(SettingKey::PinHash, QString::fromLatin1(QCryptographicHash::hash(QByteArray("ps4821"), QCryptographicHash::Sha256).toHex()));
    }
    window.showPage("settings");
    window.showPage("pin");
    window.findChild<QLineEdit *>("pinField")->setText("4821");
    window.findChild<QPushButton *>("pinOkButton")->click();
    QCOMPARE(window.currentPage(), QString("machine"));
    QTableWidget *table = window.findChild<QTableWidget *>("nozzleTable");
    QTRY_COMPARE_WITH_TIMEOUT(table->item(0, 2)->text(), QString("Online"), 5000);
    QTRY_COMPARE_WITH_TIMEOUT(table->item(1, 2)->text(), QString("Online"), 5000);
    QCOMPARE(table->rowCount(), 2);                            // only the nozzles that were added: a board 3 that answered would make no row
    QCOMPARE(table->item(0, 6)->text(), QString("1200.00"));  // the terminal's own value: the board (1000.00) is not read by itself

    table->selectRow(0);
    QCOMPARE(window.findChild<QLineEdit *>("nozzlePulses")->text(), QString("1200.00"));
    window.findChild<QComboBox *>("nozzleMode")->setCurrentIndex(1); // manual
    window.findChild<QLineEdit *>("nozzlePulses")->setText("1234.5");
    window.findChild<QPushButton *>("updateNozzleButton")->click();
    QVERIFY(window.findChild<QLabel *>("machineStatus")->text().contains("Waiting"));

    QTRY_COMPARE_WITH_TIMEOUT(boards.board(1).config.pulsesX100, quint32(123450), 5000);
    QCOMPARE(boards.board(1).config.mode, int(BoardProtocol::ModeManual));
    QTRY_VERIFY_WITH_TIMEOUT(window.findChild<QLabel *>("machineStatus")->text().contains("Verified"), 5000);
    QCOMPARE(table->item(0, 3)->text(), QString("Manual"));
    QCOMPARE(table->item(0, 6)->text(), QString("1234.50"));
    QCOMPARE(table->item(0, 7)->text(), QString("T1"));                // the nozzle keeps its tank
    QListWidget *log = window.findChild<QListWidget *>("changeLog");
    QVERIFY(log->count() >= 1);
    QVERIFY(log->item(0)->text().contains("Cash One"));
    QVERIFY(log->item(0)->text().contains("Nozzle 1"));
    QVERIFY(log->item(0)->text().contains("manual D 0.8300 1234.50 T1"));

    // a board that does not keep what it was sent fails the check: the terminal keeps its old config and says so
    boards.board(1).keepsNoConfig = true;
    window.findChild<QLineEdit *>("nozzlePulses")->setText("999");
    window.findChild<QPushButton *>("updateNozzleButton")->click();
    QTRY_VERIFY_WITH_TIMEOUT(window.findChild<QLabel *>("machineStatus")->text().contains("did not keep"), 5000);
    QVERIFY(window.findChild<QLabel *>("machineStatus")->text().contains("saved here"));
    QCOMPARE(table->item(0, 6)->text(), QString("999.00"));     // saved in the terminal's database all the same
    QCOMPARE(Settings(QSqlDatabase::database(connection_)).value(SettingKey::NozzlesNotOnBoard), QString("1")); // and marked: the board has not confirmed it
    boards.board(1).keepsNoConfig = false;

    // Read takes the config of the picked nozzle from its board and saves it; the form shows it
    boards.board(1).config.pulsesX100 = 150000; // changed at the board itself
    window.findChild<QPushButton *>("readNozzlesButton")->click();
    QTRY_VERIFY_WITH_TIMEOUT(window.findChild<QLabel *>("machineStatus")->text().contains("was read from its board and saved"), 5000);
    QCOMPARE(window.findChild<QLineEdit *>("nozzlePulses")->text(), QString("1500.00"));
    QCOMPARE(table->item(0, 6)->text(), QString("1500.00"));
    QCOMPARE(Settings(QSqlDatabase::database(connection_)).value(SettingKey::NozzlesNotOnBoard), QString()); // the terminal holds what the board holds again
}

void Smart210Test::theConverterPortIsSavedAndListenedOn()
{
    setUpLoggedInTerminalWithAPin();
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    window.showPage("settings");
    window.showPage("pin");
    window.findChild<QLineEdit *>("pinField")->setText("4821");
    window.findChild<QPushButton *>("pinOkButton")->click();
    QLineEdit *port = window.findChild<QLineEdit *>("converterPort");
    QCOMPARE(port->text(), QString::number(MachineConfig::DefaultConverterPort));

    const int free = freePort();
    port->setText(QString::number(free));
    window.findChild<QPushButton *>("saveConverterPortButton")->click();

    QCOMPARE(Settings(QSqlDatabase::database(connection_)).intValue(SettingKey::ConverterPort), free);
    QVERIFY(window.findChild<QListWidget *>("changeLog")->item(0)->text().contains("Converter port"));
    FakeBoards boards; // the terminal listens on the new port now
    boards.add(1);
    boards.connectTo(quint16(free));
    QTRY_VERIFY_WITH_TIMEOUT(boards.pollsOf(1) >= 1, 5000);

    window.findChild<QLineEdit *>("converterPort")->setText("0");
    window.findChild<QPushButton *>("saveConverterPortButton")->click();
    QVERIFY(window.findChild<QLabel *>("machineStatus")->text().contains("1 to 65535"));
    QCOMPARE(Settings(QSqlDatabase::database(connection_)).intValue(SettingKey::ConverterPort), free);
}

void Smart210Test::pulsesPerLitreAreReadAndWrittenAsAConfig()
{
    FakeBoards boards;
    boards.add(1);
    boards.addBlank(2);
    BoardBus bus;
    quickBus(&bus, &boards);
    bus.addBoard(1);
    bus.addBoard(2);
    QSignalSpy done(&bus, SIGNAL(commandDone(int,int,QByteArray)));
    QSignalSpy failed(&bus, SIGNAL(commandFailed(int,int,int)));
    QVERIFY(startBus(&bus, &boards));
    QTRY_VERIFY_WITH_TIMEOUT(bus.isOnline(1) && bus.isOnline(2), 3000);

    QVERIFY(bus.getConfig(1));
    QTRY_COMPARE_WITH_TIMEOUT(done.size(), 1, 3000);
    BoardProtocol::BoardConfig config;
    QVERIFY(BoardProtocol::parseBoardConfig(done.at(0).at(2).toByteArray(), &config));
    QCOMPARE(config.fuelType, QString("D"));
    QCOMPARE(config.pulsesX100, quint32(100000));

    config.pulsesX100 = 250000;
    config.mode = BoardProtocol::ModeManual;
    QVERIFY(bus.setConfig(1, config));
    QTRY_COMPARE_WITH_TIMEOUT(done.size(), 2, 3000);
    BoardProtocol::BoardConfig stored;
    QVERIFY(BoardProtocol::parseBoardConfig(done.at(1).at(2).toByteArray(), &stored));
    QVERIFY(stored == config); // the answer is what the board stored
    QCOMPARE(boards.board(1).config.pulsesX100, quint32(250000));

    // a value out of range is refused and nothing is stored
    BoardProtocol::BoardConfig bad = config;
    bad.density = 30000;
    QVERIFY(bus.setConfig(1, bad));
    QTRY_COMPARE_WITH_TIMEOUT(failed.size(), 1, 3000);
    QCOMPARE(failed.at(0).at(2).toInt(), int(BoardProtocol::ErrBadValue));
    QCOMPARE(int(boards.board(1).config.density), 8300);

    // a board from the factory answers with an empty config, and START is refused until it is configured
    QVERIFY(bus.getConfig(2));
    QTRY_COMPARE_WITH_TIMEOUT(done.size(), 3, 3000);
    QVERIFY(BoardProtocol::parseBoardConfig(done.at(2).at(2).toByteArray(), &config));
    QCOMPARE(int(config.density), 0);
    QCOMPARE(config.pulsesX100, quint32(0));
    QVERIFY(bus.select(2));
    QTRY_COMPARE_WITH_TIMEOUT(done.size(), 4, 3000);
    QVERIFY(bus.setPreset(2, 5000));
    QTRY_COMPARE_WITH_TIMEOUT(done.size(), 5, 3000);
    QVERIFY(bus.startFill(2));
    QTRY_COMPARE_WITH_TIMEOUT(failed.size(), 2, 3000);
    QCOMPARE(failed.at(1).at(2).toInt(), int(BoardProtocol::ErrNotConfigured));
}

// ---- the simulator of the nozzle boards (task 6.2a) ----

namespace {

// One request to the simulated boards and the answer (a frame with command 0 when nothing was answered).
BoardProtocol::Frame ask(BoardSimulator &sim, int address, int command, const QByteArray &data = QByteArray())
{
    static int seq = 0;
    BoardProtocol::Frame request;
    request.address = address;
    request.seq = ++seq & 0xFF;
    request.command = command;
    request.data = data;

    BoardProtocol::FrameParser parser;
    parser.feed(sim.answer(request));
    BoardProtocol::Frame reply;
    parser.next(&reply);

    return reply;
}

BoardProtocol::Status pollOf(BoardSimulator &sim, int address)
{
    BoardProtocol::Status status;
    BoardProtocol::parseStatus(ask(sim, address, BoardProtocol::CmdPoll).data, &status);

    return status;
}

}

void Smart210Test::theSimulatorRunsAnAutoFillOverTimeAndHoldsTheResult()
{
    using namespace BoardProtocol;
    BoardSimulator sim;
    sim.add(1);
    sim.board(1).flowGramsPerSecond = 500;

    QCOMPARE(int(ask(sim, 1, CmdSelect).command), int(CmdSelect | AnswerFlag));
    ask(sim, 1, CmdSetPreset, presetData(3000));
    const Frame started = ask(sim, 1, CmdStart);
    quint32 id = 0;
    QVERIFY(parseResultId(started.data, &id));
    QCOMPARE(pollOf(sim, 1).state, int(StateDispensing));

    sim.tick(2000); // 1000 g at 500 g/s
    Status status = pollOf(sim, 1);
    QCOMPARE(status.state, int(StateDispensing));
    QCOMPARE(status.dispensedG, quint32(1000));
    QCOMPARE(status.dispensedMl, quint32(1204)); // 1 kg of a 0.8300 kg/L fuel

    sim.tick(5000); // far more than is left: it stops at the preset
    status = pollOf(sim, 1);
    QCOMPARE(status.state, int(StateResult));
    QVERIFY(status.flags & FlagResult);
    QCOMPARE(status.dispensedG, quint32(3000));

    Result result;
    QVERIFY(parseResult(ask(sim, 1, CmdGetResult).data, &result));
    QCOMPARE(result.resultId, id);
    QCOMPARE(result.presetG, quint32(3000));
    QCOMPARE(result.dispensedG, quint32(3000));
    QCOMPARE(result.reason, int(ReasonPresetReached));

    QCOMPARE(int(ask(sim, 1, CmdAckResult, resultIdData(id)).command), int(CmdAckResult | AnswerFlag));
    QCOMPARE(pollOf(sim, 1).state, int(StateIdle));
}

void Smart210Test::inManualModeTheAmountIsTypedAtTheDispenser()
{
    using namespace BoardProtocol;
    BoardSimulator sim;
    sim.add(2);
    sim.board(2).config.mode = ModeManual;
    sim.board(2).flowGramsPerSecond = 1000;

    // the Smart210 cannot select, set an amount or start in manual mode
    QCOMPARE(int(ask(sim, 2, CmdSelect).command), int(NakCommand));
    QCOMPARE(int(ask(sim, 2, CmdSetPreset, presetData(5000)).command), int(NakCommand));
    const Frame refused = ask(sim, 2, CmdStart);
    QCOMPARE(int(refused.command), int(NakCommand));
    QCOMPARE(int(quint8(refused.data.at(0))), int(ErrWrongState));
    sim.pressSelect(2); // the select key is not used either
    QCOMPARE(pollOf(sim, 2).state, int(StateIdle));
    QVERIFY(!sim.typeAmount(2, 0));

    QVERIFY(sim.typeAmount(2, 5000)); // the supplier types 5 kg at the keyboard of the dispenser: the board is SET, and reports it
    QCOMPARE(pollOf(sim, 2).state, int(StateArmed));
    QCOMPARE(pollOf(sim, 2).presetG, quint32(5000));
    QVERIFY(!(pollOf(sim, 2).flags & FlagGunUp));
    sim.liftGun(2); // the gun is up: a manual board starts by itself, and reports the gun
    QCOMPARE(pollOf(sim, 2).state, int(StateDispensing));
    QVERIFY(pollOf(sim, 2).flags & FlagGunUp);
    QVERIFY(!sim.typeAmount(2, 1000)); // not while a fill runs
    sim.tick(2000);
    QCOMPARE(pollOf(sim, 2).dispensedG, quint32(2000));
    QCOMPARE(pollOf(sim, 2).presetG, quint32(5000));
    sim.tick(4000);
    QCOMPARE(pollOf(sim, 2).state, int(StateIdle)); // the end of the fill is not reported: no result is held
    QVERIFY(!(pollOf(sim, 2).flags & FlagResult));
    QCOMPARE(int(ask(sim, 2, CmdGetResult).command), int(NakCommand));

    // an auto board takes no typed amount
    sim.add(3);
    QVERIFY(!sim.typeAmount(3, 5000));
    // and a board that was never configured cannot start
    sim.addBlank(4);
    sim.board(4).config.mode = ModeManual;
    QVERIFY(!sim.typeAmount(4, 5000));
}

void Smart210Test::aStoppedFillAFaultAndAPowerCutEndWithTheRightReason()
{
    using namespace BoardProtocol;
    BoardSimulator sim;
    sim.add(1);
    sim.add(2);
    sim.add(3);
    for (int address = 1; address <= 3; ++address) {
        sim.board(address).flowGramsPerSecond = 500;
        ask(sim, address, CmdSelect);
        ask(sim, address, CmdSetPreset, presetData(10000));
        ask(sim, address, CmdStart);
    }
    sim.tick(2000);

    sim.stopFill(1); // the supplier stops it
    sim.setFault(2, 1); // the flow sensor fails in the middle of the fill
    sim.powerCut(3);

    Result result;
    QVERIFY(parseResult(ask(sim, 1, CmdGetResult).data, &result));
    QCOMPARE(result.reason, int(ReasonTankFullOrNoFlow));
    QCOMPARE(result.dispensedG, quint32(1000));
    QVERIFY(parseResult(ask(sim, 2, CmdGetResult).data, &result));
    QCOMPARE(result.reason, int(ReasonFault));
    // the board in power cut says nothing, then comes back with the interrupted fill
    QCOMPARE(int(ask(sim, 3, CmdPoll).command), 0);
    sim.tick(2000);
    QCOMPARE(int(ask(sim, 3, CmdPoll).command), 0);
    sim.tick(1500);
    QVERIFY(parseResult(ask(sim, 3, CmdGetResult).data, &result));
    QCOMPARE(result.reason, int(ReasonInterrupted));
    QCOMPARE(result.dispensedG, quint32(1000)); // what had been stored

    // after the result is acknowledged the board with the fault stays in FAULT until the fault is cleared
    Status status = pollOf(sim, 2);
    QVERIFY(status.flags & FlagFault);
    ask(sim, 2, CmdAckResult, resultIdData(status.resultId));
    QCOMPARE(pollOf(sim, 2).state, int(StateFault));
    QCOMPARE(int(ask(sim, 2, CmdSelect).command), int(NakCommand));
    sim.setFault(2, 0);
    QCOMPARE(pollOf(sim, 2).state, int(StateIdle));
}

void Smart210Test::theSimulatorKeepsTheConfigOfItsBoardsBetweenRuns()
{
    QTemporaryDir dir;
    QVERIFY(dir.isValid());
    const QString path = dir.path() + "/simulator.ini";
    {
        BoardSimulator sim;
        sim.add(1);
        sim.add(2);
        sim.board(1).config.mode = BoardProtocol::ModeManual;
        sim.board(1).config.fuelType = "A";
        sim.board(1).config.density = 7400;
        sim.board(1).config.pulsesX100 = 123450;
        sim.board(1).nextId = 250;
        sim.board(2).flowGramsPerSecond = 900;
        QSettings settings(path, QSettings::IniFormat);
        sim.save(settings);
    }

    BoardSimulator again;
    again.add(1);
    again.add(2);
    QSettings settings(path, QSettings::IniFormat);
    again.load(settings);

    QCOMPARE(again.board(1).config.mode, int(BoardProtocol::ModeManual));
    QCOMPARE(again.board(1).config.fuelType, QString("A"));
    QCOMPARE(int(again.board(1).config.density), 7400);
    QCOMPARE(again.board(1).config.pulsesX100, quint32(123450));
    QCOMPARE(again.board(1).nextId, quint32(250)); // result ids never go back
    QCOMPARE(again.board(2).flowGramsPerSecond, 900);
}


void Smart210Test::aManualFillIsSeenByTheBusAsItRuns()
{
    FakeBoards boards;
    boards.add(1);
    boards.board(1).config.mode = BoardProtocol::ModeManual;
    boards.board(1).flowGramsPerSecond = 2000;
    BoardBus bus;
    quickBus(&bus, &boards);
    bus.addBoard(1);
    QVERIFY(startBus(&bus, &boards));
    QTRY_VERIFY_WITH_TIMEOUT(bus.isOnline(1), 3000);

    // the supplier types 6 kg and takes the gun up: the poll answers show the preset, the gun and the weight as it runs
    QVERIFY(boards.simulator().typeAmount(1, 6000));
    QTRY_COMPARE_WITH_TIMEOUT(bus.lastStatus(1).presetG, quint32(6000), 3000);
    QVERIFY(boards.simulator().liftGun(1));
    QTRY_COMPARE_WITH_TIMEOUT(bus.lastStatus(1).state, int(BoardProtocol::StateDispensing), 3000);
    QVERIFY(bus.lastStatus(1).flags & BoardProtocol::FlagGunUp);
    for (int i = 0; i < 10; ++i) { // one second, in steps, while the bus keeps polling
        boards.simulator().tick(100);
        QTest::qWait(10);
    }
    QVERIFY(bus.lastStatus(1).dispensedG > 0);
    QVERIFY(bus.lastStatus(1).dispensedG < 6000);
    boards.simulator().tick(5000);
    QTRY_COMPARE_WITH_TIMEOUT(bus.lastStatus(1).state, int(BoardProtocol::StateIdle), 3000);   // the end is not reported

    // the Smart210 never worked with it: it only polled
    QTest::qWait(300);
    foreach (int command, boards.board(1).commands) {
        QVERIFY2(command != BoardProtocol::CmdStart && command != BoardProtocol::CmdGetResult && command != BoardProtocol::CmdAckResult && command != BoardProtocol::CmdSetPreset, "a manual board is only polled");
    }
}

// ---- the supplier scanner (task 6.3) ----

namespace {

int keyCodeOf(char c)
{
    for (int code = 0; code < 128; ++code) {
        if (ScanDecoder::charOf(code, false) == QChar(c)) {
            return code;
        }
    }

    return -1;
}

// What a scanner does for the text: key down and key up for each character, then Enter; 1 ms apart. Returns the barcodes it completed.
QStringList scan(ScanDecoder &decoder, const QString &text, qint64 *clock, bool enter = true)
{
    QStringList done;
    QString barcode;
    for (int i = 0; i < text.size(); ++i) {
        const int code = keyCodeOf(text.at(i).toLatin1());
        if (code < 0) {
            qFatal("no key for this character");
        }
        if (decoder.event(ScanDecoder::EvKey, code, 1, ++*clock, &barcode)) {
            done << barcode;
        }
        decoder.event(ScanDecoder::EvKey, code, 0, ++*clock, &barcode);
    }
    if (enter && decoder.event(ScanDecoder::EvKey, 28, 1, ++*clock, &barcode)) {
        done << barcode;
    }
    decoder.event(ScanDecoder::EvKey, 28, 0, ++*clock, &barcode);

    return done;
}

const char *procDevices =
    "I: Bus=0019 Vendor=0000 Product=0001 Version=0000\n"
    "N: Name=\"s3c-keypad\"\n"
    "P: Phys=s3c-keypad/input0\n"
    "S: Sysfs=/devices/platform/s3c-keypad/input/input0\n"
    "U: Uniq=\n"
    "H: Handlers=kbd event0\n"
    "B: PROP=0\n"
    "B: EV=100013\n"
    "\n"
    "I: Bus=0003 Vendor=1a86 Product=e026 Version=0110\n"
    "N: Name=\"SCANNER SCANNER\"\n"
    "P: Phys=usb-s5p-ehci-1.1/input0\n"
    "S: Sysfs=/devices/platform/s5p-ehci/usb1/1-1/1-1.1/1-1.1:1.0/input/input1\n"
    "U: Uniq=\n"
    "H: Handlers=sysrq kbd event2\n"
    "B: PROP=0\n"
    "B: EV=120013\n"
    "\n"
    "I: Bus=0003 Vendor=1a86 Product=e026 Version=0110\n"
    "N: Name=\"SCANNER SCANNER\"\n"
    "P: Phys=usb-s5p-ehci-1.1/input1\n"
    "H: Handlers=event3\n"
    "B: EV=1f\n"
    "\n"
    "I: Bus=0003 Vendor=05e0 Product=1200 Version=0110\n"
    "N: Name=\"Symbol Technologies, Inc, 2008 Symbol Bar Code Scanner\"\n"
    "P: Phys=usb-s5p-ehci-1.2/input0\n"
    "H: Handlers=sysrq kbd event4\n"
    "B: EV=120013\n"
    "\n"
    "I: Bus=0003 Vendor=093a Product=2510 Version=0111\n"
    "N: Name=\"USB Optical Mouse\"\n"
    "P: Phys=usb-s5p-ohci-1/input0\n"
    "H: Handlers=mouse0 event5\n"
    "B: EV=17\n";

}

void Smart210Test::aScannerTypesABarcodeAndEnterCompletesIt()
{
    ScanDecoder decoder;
    qint64 clock = 0;

    QCOMPARE(scan(decoder, "0000021500000001", &clock), QStringList() << "0000021500000001");
    QCOMPARE(scan(decoder, "12345", &clock), QStringList() << "12345"); // the next scan starts clean
    QCOMPARE(scan(decoder, "777", &clock, false), QStringList());      // without Enter nothing is complete yet
    QCOMPARE(scan(decoder, "", &clock), QStringList() << "777");       // and the Enter alone completes it
    QCOMPARE(scan(decoder, "", &clock), QStringList());                // an Enter with nothing before it is nothing
}

void Smart210Test::theDecoderKnowsShiftTheKeypadAndSymbols()
{
    ScanDecoder decoder;
    QString barcode;

    // Shift down, A, Shift up, a
    decoder.event(ScanDecoder::EvKey, 42, 1, 1, &barcode);
    decoder.event(ScanDecoder::EvKey, 30, 1, 2, &barcode);
    decoder.event(ScanDecoder::EvKey, 42, 0, 3, &barcode);
    decoder.event(ScanDecoder::EvKey, 30, 1, 4, &barcode);
    // Right shift, the 1 key (!), then the keypad 7, 0 and the dot
    decoder.event(ScanDecoder::EvKey, 54, 1, 5, &barcode);
    decoder.event(ScanDecoder::EvKey, 2, 1, 6, &barcode);
    decoder.event(ScanDecoder::EvKey, 54, 0, 7, &barcode);
    decoder.event(ScanDecoder::EvKey, 71, 1, 8, &barcode);
    decoder.event(ScanDecoder::EvKey, 82, 1, 9, &barcode);
    decoder.event(ScanDecoder::EvKey, 83, 1, 10, &barcode);
    QVERIFY(decoder.event(ScanDecoder::EvKey, 96, 1, 11, &barcode)); // the number pad Enter ends it too
    QCOMPARE(barcode, QString("Aa!70."));

    QCOMPARE(ScanDecoder::charOf(52, false), QChar('.')); // an ordinary dot is a character, not a gap
    QCOMPARE(ScanDecoder::charOf(12, false), QChar('-'));
    QCOMPARE(ScanDecoder::charOf(12, true), QChar('_'));
    QCOMPARE(ScanDecoder::charOf(43, false), QChar('\\'));
    QCOMPARE(ScanDecoder::charOf(57, false), QChar(' '));
    QVERIFY(ScanDecoder::charOf(29, false).isNull()); // Ctrl types nothing
    QVERIFY(ScanDecoder::charOf(200, false).isNull());
    QVERIFY(ScanDecoder::charOf(-1, false).isNull());
}

void Smart210Test::theDecoderIgnoresRepeatsStrayKeysAndLongPauses()
{
    ScanDecoder decoder;
    QString barcode;
    qint64 clock = 0;

    // other kinds of event, a key repeat (value 2) and a key that types nothing change nothing
    QVERIFY(!decoder.event(0, 0, 0, ++clock, &barcode)); // EV_SYN
    QVERIFY(!decoder.event(ScanDecoder::EvKey, 2, 2, ++clock, &barcode));
    QVERIFY(!decoder.event(ScanDecoder::EvKey, 29, 1, ++clock, &barcode));
    QCOMPARE(scan(decoder, "", &clock), QStringList()); // so Enter finds nothing

    // a stray key, then a real scan a long time later: the stray key does not stick to it
    decoder.event(ScanDecoder::EvKey, keyCodeOf('9'), 1, ++clock, &barcode);
    clock += ScanDecoder::IdleMs + 500;
    QCOMPARE(scan(decoder, "4242", &clock), QStringList() << "4242");

    // the keys of one scan come close together, so a slow scanner (a few ms a key) is fine
    ScanDecoder slow;
    qint64 slowClock = 0;
    QString text;
    foreach (QChar c, QString("55555")) {
        slow.event(ScanDecoder::EvKey, keyCodeOf(c.toLatin1()), 1, slowClock += 40, &text);
    }
    QVERIFY(slow.event(ScanDecoder::EvKey, 28, 1, slowClock += 40, &text));
    QCOMPARE(text, QString("55555"));

    // a runaway scanner cannot fill the memory
    ScanDecoder runaway;
    qint64 runawayClock = 0;
    const QStringList done = scan(runaway, QString(1000, '1'), &runawayClock);
    QCOMPARE(done.size(), 1);
    QCOMPARE(done.at(0).size(), int(ScanDecoder::MaxLength));
}

void Smart210Test::aScannerIsFoundByItsNameWhateverItsEventNumberIs()
{
    const QString text = QString::fromLatin1(procDevices);

    QCOMPARE(InputDevices::findEvent(text, "SCANNER SCANNER"), QString("/dev/input/event2")); // the kbd node, not the other interface
    QCOMPARE(InputDevices::findEvent(text, "Symbol Technologies, Inc, 2008 Symbol Bar Code Scanner"), QString("/dev/input/event4"));
    QCOMPARE(InputDevices::findEvent(text, "SCANNER SCANNER", "usb-s5p-ehci-1.1"), QString("/dev/input/event2"));
    QCOMPARE(InputDevices::findEvent(text, "SCANNER SCANNER", "usb-s5p-ehci-1.2"), QString()); // not at that port
    QCOMPARE(InputDevices::findEvent(text, "SCANNER"), QString());                              // the name is exact
    QCOMPARE(InputDevices::findEvent(text, "scanner scanner"), QString());                      // and case matters
    QCOMPARE(InputDevices::findEvent(text, "USB Optical Mouse"), QString());                    // a mouse is no keyboard
    QCOMPARE(InputDevices::findEvent(text, "No such device"), QString());
    QCOMPARE(InputDevices::findEvent(QString(), "SCANNER SCANNER"), QString());

    // after a restart the same scanner has another number: it is still found
    QString renumbered = text;
    renumbered.replace("sysrq kbd event2", "sysrq kbd event7");
    QCOMPARE(InputDevices::findEvent(renumbered, "SCANNER SCANNER"), QString("/dev/input/event7"));
    QCOMPARE(InputDevices::findEvent(renumbered, "SCANNER SCANNER", QString(), "/tmp/in"), QString("/tmp/in/event7"));

    // the last device of the file has no empty line after it, and the file may use Windows line ends
    QString crlf = text;
    crlf.replace("\n", "\r\n");
    QCOMPARE(InputDevices::findEvent(crlf, "Symbol Technologies, Inc, 2008 Symbol Bar Code Scanner"), QString("/dev/input/event4"));
    QString last = "N: Name=\"Last One\"\nP: Phys=usb-1/input0\nH: Handlers=kbd event9";
    QCOMPARE(InputDevices::findEvent(last, "Last One"), QString("/dev/input/event9"));
}

void Smart210Test::theDeviceListIsReadFromAFile()
{
    QTemporaryDir dir;
    QVERIFY(dir.isValid());
    const QString path = dir.path() + "/devices";
    QFile file(path);
    QVERIFY(file.open(QIODevice::WriteOnly));
    file.write(procDevices);
    file.close();

    QCOMPARE(InputDevices::findEventIn(path, "SCANNER SCANNER"), QString("/dev/input/event2"));
    QCOMPARE(InputDevices::findEventIn(dir.path() + "/missing", "SCANNER SCANNER"), QString()); // no list: no device
}

void Smart210Test::withoutEvdevTheSupplierScannerStaysIdle()
{
    SupplierScanner scanner;
    QSignalSpy connected(&scanner, SIGNAL(connectedChanged(bool)));

    scanner.start("SCANNER SCANNER");
    QTest::qWait(50);

#ifndef Q_OS_LINUX
    QVERIFY(!SupplierScanner::isSupported());
    QVERIFY(!scanner.isRunning());
#endif
    QVERIFY(!scanner.isConnected()); // no such scanner here either way
    QCOMPARE(connected.size(), 0);
    scanner.stop();
}

// ---- the supply screen's nozzle list and the two ways to choose a nozzle (task 6.4) ----

namespace {

// Nozzles 1 to 4 were added and have answered with their config: 1 and 2 auto, 3 manual, 4 auto (its board is not there).
void storeNozzles(MachineConfig *config, const QString &connection)
{
    // the nozzles were added by hand (a nozzle is only kept up to date from its board when it was added)
    QSqlQuery add(QSqlDatabase::database(connection));
    for (int number = 1; number <= 4; ++number) {
        add.exec("INSERT INTO nozzles (number, tank_id, fuel_type, board_address, mode, synced, deleted) VALUES (" + QString::number(number) + ", 0, '', " + QString::number(number) + ", 0, 0, 0)");
    }

    BoardProtocol::BoardConfig autoConfig;
    autoConfig.mode = BoardProtocol::ModeAuto;
    autoConfig.fuelType = "D";
    autoConfig.density = 8300;
    autoConfig.pulsesX100 = 100000;
    BoardProtocol::BoardConfig manualConfig = autoConfig;
    manualConfig.mode = BoardProtocol::ModeManual;

    config->storeBoardConfig(1, autoConfig);
    config->storeBoardConfig(2, autoConfig);
    config->storeBoardConfig(3, manualConfig);
    config->storeBoardConfig(4, autoConfig);
}

}

void Smart210Test::aSelectKeyAtTheDispenserChoosesTheNozzleAndPressingAgainLetsItGo()
{
    QSqlDatabase db = openDatabase();
    MachineConfig config(db);
    storeNozzles(&config, connection_);
    FakeBoards boards;
    boards.add(1);
    boards.add(2);
    BoardBus bus;
    quickBus(&bus, &boards);
    bus.addBoard(1);
    bus.addBoard(2);
    SupplySelection selection(&bus, &config);
    QSignalSpy changed(&selection, SIGNAL(currentChanged(int)));
    QVERIFY(startBus(&bus, &boards));
    QTRY_VERIFY_WITH_TIMEOUT(bus.isOnline(1) && bus.isOnline(2), 3000);
    QCOMPARE(selection.current(), 0);

    boards.pressSelect(1);                                              // the board only tells; the app chooses the nozzle and sends SELECT
    QTRY_COMPARE_WITH_TIMEOUT(selection.current(), 1, 3000);
    QVERIFY(selection.isSelected(1));
    QTRY_COMPARE_WITH_TIMEOUT(boards.board(1).beeps, 1, 3000);          // the board beeps...
    QCOMPARE(boards.board(1).state, int(BoardProtocol::StateIdle));     // ...and its state does not change: the choice is kept by the Smart210

    boards.pressSelect(2); // another nozzle's key: it becomes the chosen one; only one nozzle is chosen
    QTRY_COMPARE_WITH_TIMEOUT(selection.current(), 2, 3000);
    QTRY_COMPARE_WITH_TIMEOUT(boards.board(2).beeps, 1, 3000);          // only the board that was called beeps
    QCOMPARE(boards.board(1).beeps, 1);
    QVERIFY(!selection.isSelected(1));
    QVERIFY(selection.isSelected(2));
    QCOMPARE(boards.board(2).state, int(BoardProtocol::StateIdle));

    boards.pressSelect(2); // the key again lets it go (nothing is sent)
    QTRY_COMPARE_WITH_TIMEOUT(selection.current(), 0, 3000);
    QVERIFY(!selection.isSelected(2));
    QCOMPARE(boards.board(2).beeps, 1);
    QCOMPARE(changed.size(), 3);
}

void Smart210Test::tappingANozzleSelectsItOnItsBoardAndTappingAgainLetsItGo()
{
    QSqlDatabase db = openDatabase();
    MachineConfig config(db);
    storeNozzles(&config, connection_);
    FakeBoards boards;
    boards.add(1);
    boards.add(2);
    BoardBus bus;
    quickBus(&bus, &boards);
    bus.addBoard(1);
    bus.addBoard(2);
    SupplySelection selection(&bus, &config);
    QSignalSpy changed(&selection, SIGNAL(currentChanged(int)));
    QVERIFY(startBus(&bus, &boards));
    QTRY_VERIFY_WITH_TIMEOUT(bus.isOnline(1) && bus.isOnline(2), 3000);

    QVERIFY(selection.select(1));                                       // the choice is made at once, the board only beeps
    QCOMPARE(selection.current(), 1);
    QVERIFY(selection.isSelected(1));
    QTRY_COMPARE_WITH_TIMEOUT(boards.board(1).beeps, 1, 3000);
    QCOMPARE(boards.board(1).state, int(BoardProtocol::StateIdle));

    QVERIFY(selection.select(2));
    QCOMPARE(selection.current(), 2);                                   // only one nozzle is chosen
    QVERIFY(!selection.isSelected(1));
    QTRY_COMPARE_WITH_TIMEOUT(boards.board(2).beeps, 1, 3000);

    QVERIFY(selection.select(2)); // choosing the chosen one again changes nothing, and does not beep again
    QVERIFY(selection.deselect(2));
    QCOMPARE(selection.current(), 0);
    QCOMPARE(boards.board(2).beeps, 1);
    QCOMPARE(changed.size(), 3);

    // a nozzle chosen by its key at the dispenser; letting another one go changes nothing for the current one
    boards.pressSelect(2);
    QTRY_COMPARE_WITH_TIMEOUT(selection.current(), 2, 3000);
    QVERIFY(selection.deselect(1));
    QCOMPARE(selection.current(), 2);
}

void Smart210Test::aNozzleThatCannotBeChosenSaysWhy()
{
    QSqlDatabase db = openDatabase();
    MachineConfig config(db);
    storeNozzles(&config, connection_);
    FakeBoards boards;
    boards.add(1);
    boards.add(3); // its config says manual
    boards.board(3).config.mode = BoardProtocol::ModeManual;
    boards.add(5); // the board answers, but its config was never read here
    BoardBus bus;
    quickBus(&bus, &boards);
    bus.addBoard(1);
    bus.addBoard(3);
    bus.addBoard(4); // no board 4
    bus.addBoard(5);
    SupplySelection selection(&bus, &config);
    QString why;

    QVERIFY(!selection.select(1, &why)); // not started: no link
    QVERIFY(why.contains("connection"));

    QVERIFY(startBus(&bus, &boards));
    QTRY_VERIFY_WITH_TIMEOUT(bus.isOnline(1) && bus.isOnline(3) && bus.isOnline(5), 3000);

    QVERIFY(!selection.select(3, &why));
    QVERIFY2(why.contains("manual"), qPrintable(why));
    QVERIFY(!selection.select(4, &why));
    QVERIFY2(why.contains("does not answer"), qPrintable(why));
    QVERIFY(!selection.select(5, &why));
    QVERIFY2(why.contains("not been read"), qPrintable(why));
    QVERIFY(!selection.select(9, &why));
    QVERIFY(!selection.select(0, &why));

    boards.board(1).state = BoardProtocol::StateFault;
    QTRY_COMPARE_WITH_TIMEOUT(bus.lastStatus(1).state, int(BoardProtocol::StateFault), 3000);
    QVERIFY(!selection.select(1, &why));
    QVERIFY2(why.contains("fault"), qPrintable(why));

    boards.board(1).state = BoardProtocol::StateDispensing;
    QTRY_COMPARE_WITH_TIMEOUT(bus.lastStatus(1).state, int(BoardProtocol::StateDispensing), 3000);
    QVERIFY(!selection.select(1, &why));
    QVERIFY2(why.contains("busy"), qPrintable(why));
    QCOMPARE(selection.current(), 0);
}

void Smart210Test::theChosenNozzleIsLetGoWhenItsBoardIsFreeOrGone()
{
    QSqlDatabase db = openDatabase();
    MachineConfig config(db);
    storeNozzles(&config, connection_);
    FakeBoards boards;
    boards.add(1);
    boards.add(2);
    BoardBus bus;
    quickBus(&bus, &boards);
    bus.addBoard(1);
    bus.addBoard(2);
    SupplySelection selection(&bus, &config);
    QVERIFY(startBus(&bus, &boards));
    QTRY_VERIFY_WITH_TIMEOUT(bus.isOnline(1) && bus.isOnline(2), 3000);

    QVERIFY(selection.select(1));
    QTRY_COMPARE_WITH_TIMEOUT(selection.current(), 1, 3000);
    boards.board(1).state = BoardProtocol::StateFault; // the board cannot work any more
    QTRY_COMPARE_WITH_TIMEOUT(selection.current(), 0, 3000);

    QVERIFY(selection.select(2));
    QTRY_COMPARE_WITH_TIMEOUT(selection.current(), 2, 3000);
    boards.board(2).silent = true; // the board goes away
    QTRY_COMPARE_WITH_TIMEOUT(selection.current(), 0, 3000);
}

void Smart210Test::theSessionsTabListsTheNozzlesAndOnlyLooksAtThem()
{
    setUpLoggedInTerminalWithAPin();
    addNozzleRows(QList<int>() << 3 << 4); // added by hand: board 3 answers (its nozzle is set to manual here), board 4 is not there
    {
        MachineConfig seed(QSqlDatabase::database(connection_));
        QVERIFY(seed.storeBoardConfig(3, boardConfig("D", 8300, 100000, BoardProtocol::ModeManual)));
    }
    FakeBoards boards;
    boards.add(1);
    boards.add(2);
    boards.add(3);
    boards.board(3).config.mode = BoardProtocol::ModeManual;
    boards.board(3).flowGramsPerSecond = 1000;
    const int port = freePort();
    Settings(QSqlDatabase::database(connection_)).setInt(SettingKey::ConverterPort, port);
    AppConfig config; // the boards are listened to
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    boards.connectTo(quint16(port));
    window.showPage("supply");
    QCOMPARE(window.currentPage(), QString("supply"));
    QTableWidget *table = window.findChild<QTableWidget *>("supplyTable");
    QLabel *current = window.findChild<QLabel *>("supplyCurrent");
    QLabel *message = window.findChild<QLabel *>("supplyMessage");
    QCOMPARE(table->rowCount(), 4);                                      // the nozzles that were added: 1 to 4

    // the nozzles that answer show their config and "Free"; the rest "No answer"
    QTRY_COMPARE_WITH_TIMEOUT(table->item(0, 3)->text(), QString("Free"), 5000);
    QTRY_COMPARE_WITH_TIMEOUT(table->item(2, 3)->text(), QString("Not polled"), 5000);     // nozzle 3 is in manual mode: out of the polling
    QCOMPARE(table->item(0, 0)->text(), QString("1"));
    QVERIFY(table->item(0, 1)->text().startsWith("D"));
    QCOMPARE(table->item(0, 2)->text(), QString("Auto"));
    QCOMPARE(table->item(2, 2)->text(), QString("Manual"));
    QCOMPARE(table->item(3, 3)->text(), QString("No answer"));
    QCOMPARE(table->horizontalHeaderItem(4)->text(), QString("Tickets (kg - count)"));
    QCOMPARE(table->horizontalHeaderItem(5)->text(), QString("Dispensed (kg)"));
    QCOMPARE(current->text(), QString("Click a nozzle to see its tickets."));
    QVERIFY(window.findChild<QPushButton *>("supplyStartButton") == 0);   // nothing here controls the supply
    QVERIFY(window.findChild<QPushButton *>("supplyRemoveButton") == 0);
    QVERIFY(window.findChild<QPushButton *>("supplyClearButton") == 0);
    QVERIFY(window.findChild<QPushButton *>("supplyDeselectButton") == 0);

    // the supplier's select key at nozzle 1: the board and the table show it, and the tickets below follow it
    boards.pressSelect(1);
    QTRY_COMPARE_WITH_TIMEOUT(table->item(0, 3)->text(), QString("Selected"), 5000);
    QTRY_COMPARE_WITH_TIMEOUT(current->text(), QString("Tickets of nozzle 1"), 5000);
    boards.pressSelect(2);
    QTRY_COMPARE_WITH_TIMEOUT(current->text(), QString("Tickets of nozzle 2"), 5000);

    // a click only looks at a nozzle: nothing is sent to its board, nothing is said
    table->cellClicked(2, 0);
    QCOMPARE(current->text(), QString("Tickets of nozzle 3"));
    QTest::qWait(600);
    QCOMPARE(boards.board(3).state, int(BoardProtocol::StateIdle));
    QVERIFY(message->text().isEmpty());
    table->cellClicked(3, 0);
    QCOMPARE(current->text(), QString("Tickets of nozzle 4"));
    QVERIFY(message->text().isEmpty());
    QTRY_COMPARE_WITH_TIMEOUT(table->item(1, 3)->text(), QString("Selected"), 3000);         // one nozzle at a time: 2 is chosen, 1 is not
    QCOMPARE(table->item(0, 3)->text(), QString("Free"));

    // a manual fill typed at the dispenser is not seen at all: its board is not polled
    QVERIFY(boards.simulator().typeAmount(3, 8000));
    QVERIFY(boards.simulator().liftGun(3));
    boards.simulator().tick(2000);
    QTest::qWait(600);
    QCOMPARE(table->item(2, 3)->text(), QString("Not polled"));
    QCOMPARE(table->item(2, 5)->text(), QString());
    QCOMPARE(boards.pollsOf(3), 0);

    window.showPage("spot");
    QCOMPARE(window.currentPage(), QString("spot"));
}

// ---- Review: the records of a date, their comments and dates, and the upload ----

void Smart210Test::theReviewListsTheRecordsOfTheChosenDateWithTotals()
{
    QSqlDatabase db = openDatabase();
    addUser(5, "Cash Five", "pw");
    recordADay(); // on 2026-10-07: 1 spot sale (10.50 kg, 78.75), 1 supply, 1 remainder, 1 loan (20.00 kg), 1 settlement (5.00 kg)
    run("INSERT INTO sales (id, created_at, customer_id, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, unpaid_amount, "
        "receipt, usage, comment, sale_date, user_id) VALUES ('sale-t', '2026-10-07 14:00:00', NULL, 'ticket', 'D', 750, 2000, 'cash', 15000, 15000, 0, "
        "NULL, 'sale', 'from the road', '2026-10-07', 5)");
    run("INSERT INTO sales (id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id, sale_date) "
        "VALUES ('sale-next', '2026-10-08 12:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5, '2026-10-08')");
    run("INSERT INTO sales (id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id, sale_date, deleted_at) "
        "VALUES ('sale-deleted', '2026-10-07 11:00:00', 'spot', 'D', 750, 999, 'cash', 750, 750, 'sale', 5, '2026-10-07', '2026-10-07 11:01:00')");
    run("INSERT INTO ticket_loans (id, customer_id, fuel_type, amount_kg, loaned_at, user_id, date, deleted, synced) "
        "VALUES ('loan-web', 1, 'D', 100, '2026-10-07 09:30:00', NULL, '2026-10-07', 0, 1)"); // made on the web: not this terminal's

    const QList<ReviewData::Record> sales = ReviewData::sales(db, "2026-10-07");

    QCOMPARE(sales.size(), 2); // not the deleted one, not the other day's
    QCOMPARE(sales.at(0).id, QString("sale-1")); // oldest first
    QCOMPARE(sales.at(0).saleType, QString("spot"));
    QCOMPARE(sales.at(0).kgX100, qlonglong(1050));
    QCOMPARE(sales.at(0).user, QString("Cash Five"));
    QCOMPARE(sales.at(0).state, ReviewData::Waiting);
    QCOMPARE(sales.at(1).id, QString("sale-t"));
    QCOMPARE(sales.at(1).comment, QString("from the road"));
    QCOMPARE(ReviewData::sales(db, "2026-10-08").size(), 1);

    const QList<ReviewData::Record> supplies = ReviewData::supplements(db, "2026-10-07");
    QCOMPARE(supplies.size(), 1);
    QCOMPARE(supplies.at(0).litersX100, qlonglong(1265));
    QCOMPARE(supplies.at(0).manual, true);

    const QList<ReviewData::Record> debts = ReviewData::debts(db, "2026-10-07");
    QCOMPARE(debts.size(), 2); // the loan and its settlement, by time; not the loan of the web app
    QCOMPARE(int(debts.at(0).kind), int(ReviewData::Loan));
    QCOMPARE(int(debts.at(1).kind), int(ReviewData::Settlement));
    QCOMPARE(debts.at(1).ticket, QString("0000003000000011"));
    QCOMPARE(debts.at(1).fuel, QString("D")); // from its loan

    const ReviewData::Totals t = ReviewData::totals(db, "2026-10-07");
    QCOMPARE(t.spotCount, 1);
    QCOMPARE(t.spotKgX100, qlonglong(1050));
    QCOMPARE(t.spotAmountX100, qlonglong(7875));
    QCOMPARE(t.ticketCount, 1);
    QCOMPARE(t.ticketKgX100, qlonglong(2000));
    QCOMPARE(t.ticketAmountX100, qlonglong(15000));
    QCOMPARE(t.paidX100, qlonglong(22875));
    QCOMPARE(t.unpaidX100, qlonglong(0));
    QCOMPARE(t.supplements, 1);
    QCOMPARE(t.supplementKgX100, qlonglong(1050));
    QCOMPARE(t.remainders, 1);
    QCOMPARE(t.remainderKgX100, qlonglong(250));
    QCOMPARE(t.loans, 1);
    QCOMPARE(t.loanKgX100, qlonglong(2000));
    QCOMPARE(t.settlements, 1);
    QCOMPARE(t.settlementKgX100, qlonglong(500));
    QCOMPARE(t.waiting, 5);
    QCOMPARE(t.refused, 0);

    run("INSERT INTO upload_errors (table_name, row_key, message, created_at) VALUES ('sales', 'sale-t', 'customer does not exist', '2026-10-07 15:00:00')");
    run("UPDATE sales SET synced = 1 WHERE id = 'sale-1'");
    const QList<ReviewData::Record> again = ReviewData::sales(db, "2026-10-07");
    QCOMPARE(again.at(0).state, ReviewData::Uploaded);
    QCOMPARE(again.at(1).state, ReviewData::Refused);
    QCOMPARE(again.at(1).refusal, QString("customer does not exist"));
    QCOMPARE(ReviewData::totals(db, "2026-10-07").refused, 1);
}

void Smart210Test::aRecordWithoutADateShowsUnderTheBusinessDayOfItsTime()
{
    QSqlDatabase db = openDatabase();
    run("INSERT INTO sales (id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id) "
        "VALUES ('night', '2026-10-08 03:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5)");     // before 9 AM: the day before
    run("INSERT INTO sales (id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id) "
        "VALUES ('day', '2026-10-08 10:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5)");
    run("INSERT INTO sales (id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id, sale_date) "
        "VALUES ('moved', '2026-10-08 10:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5, '2026-10-05')"); // a date of its own wins

    QCOMPARE(ReviewData::sales(db, "2026-10-07").size(), 1);
    QCOMPARE(ReviewData::sales(db, "2026-10-07").at(0).id, QString("night"));
    QCOMPARE(ReviewData::sales(db, "2026-10-07").at(0).date, QString("2026-10-07"));
    QCOMPARE(ReviewData::sales(db, "2026-10-08").size(), 1);
    QCOMPARE(ReviewData::sales(db, "2026-10-08").at(0).id, QString("day"));
    QCOMPARE(ReviewData::sales(db, "2026-10-05").at(0).id, QString("moved"));
}

void Smart210Test::aRecordMadeNearNineIsFlaggedForTheDate()
{
    QCOMPARE(ReviewData::GraceMinutes, 30);
    QVERIFY(ReviewData::nearShiftLine("2026-10-07 08:30:00"));
    QVERIFY(ReviewData::nearShiftLine("2026-10-07 08:45:10"));
    QVERIFY(ReviewData::nearShiftLine("2026-10-07 09:00:00"));
    QVERIFY(ReviewData::nearShiftLine("2026-10-07 09:30:59"));
    QVERIFY(!ReviewData::nearShiftLine("2026-10-07 08:29:59"));
    QVERIFY(!ReviewData::nearShiftLine("2026-10-07 09:31:00"));
    QVERIFY(!ReviewData::nearShiftLine("2026-10-07 14:00:00"));
    QVERIFY(!ReviewData::nearShiftLine("2026-10-07 03:00:00"));
    QVERIFY(!ReviewData::nearShiftLine(QString()));

    QSqlDatabase db = openDatabase();
    recordADay(); // everything made between 09:00 and 09:20
    foreach (const ReviewData::Record &r, ReviewData::sales(db, "2026-10-07")) {
        QVERIFY(r.nearShiftLine);
    }
}

void Smart210Test::aChangedCommentMakesTheRecordWaitAgainAndIsSentAgain()
{
    QSqlDatabase db = openDatabase();
    recordADay();
    run("UPDATE sales SET synced = 1");
    run("UPDATE supplements SET synced = 1");
    run("UPDATE ticket_loans SET synced = 1");
    run("UPDATE ticket_loan_settlements SET synced = 1");
    QList<ReviewData::Ref> refs;
    refs << ReviewData::Ref(ReviewData::Sale, "sale-1") << ReviewData::Ref(ReviewData::Supplement, "sup-1")
         << ReviewData::Ref(ReviewData::Loan, "loan-1") << ReviewData::Ref(ReviewData::Settlement, "set-1");
    QCOMPARE(Uploader::pendingTotal(db), 2); // the remainder and the ticket of recordADay() still wait

    QCOMPARE(ReviewData::setComment(db, refs, "  paid by card "), 4);

    QCOMPARE(Uploader::pendingTotal(db), 6); // all four wait again
    QCOMPARE(ReviewData::sales(db, "2026-10-07").at(0).comment, QString("paid by card")); // trimmed
    QCOMPARE(ReviewData::sales(db, "2026-10-07").at(0).state, ReviewData::Waiting);
    QCOMPARE(ReviewData::setComment(db, refs, "paid by card"), 0); // the same again: nothing changes, nothing waits more
    run("UPDATE sales SET synced = 1");
    QCOMPARE(ReviewData::setComment(db, refs, "paid by card"), 0);
    QCOMPARE(ReviewData::sales(db, "2026-10-07").at(0).state, ReviewData::Uploaded);

    // it goes up again, and only what changed
    configureServer();
    server_->reply("/api/push/nozzles", 200, q("{'status':'ok','saved':['1'],'rejected':[]}"));
    server_->reply("/api/push/supplements", 200, q("{'status':'ok','saved':['sup-1'],'rejected':[]}"));
    server_->reply("/api/push/ticket_loans", 200, q("{'status':'ok','saved':['loan-1'],'rejected':[]}"));
    server_->reply("/api/push/ticket_loan_settlements", 200, q("{'status':'ok','saved':['set-1'],'rejected':[]}"));
    server_->reply("/api/push/remainders", 200, q("{'status':'ok','saved':['rem-1'],'rejected':[]}"));
    server_->reply("/api/push/tickets", 200, q("{'status':'ok','saved':['0000003000000011'],'rejected':[]}"));
    Uploader uploader(db);
    QString message;
    QVERIFY(uploadOk(uploader, &message));
    QVERIFY2(!server_->log().contains("POST /api/push/sales"), "the sale was not changed after the upload");
    const int at = server_->log().indexOf("POST /api/push/supplements");
    QVERIFY(at >= 0);
    QCOMPARE(QJsonDocument::fromJson(server_->requests().at(at).body).object().value("rows").toArray().at(0).toObject().value("comment").toString(),
             QString("paid by card"));
    const int loanAt = server_->log().indexOf("POST /api/push/ticket_loans");
    QCOMPARE(QJsonDocument::fromJson(server_->requests().at(loanAt).body).object().value("rows").toArray().at(0).toObject().value("comment").toString(),
             QString("paid by card"));
    QCOMPARE(Uploader::pendingTotal(db), 0);

    // an empty comment clears it
    QCOMPARE(ReviewData::setComment(db, refs.mid(0, 1), ""), 1);
    QVERIFY(ReviewData::sales(db, "2026-10-07").at(0).comment.isEmpty());
    QCOMPARE(Uploader::pendingTotal(db), 1);

    // a sale deleted before its upload cannot be edited
    run("UPDATE sales SET deleted_at = '2026-10-07 12:00:00'");
    QCOMPARE(ReviewData::setComment(db, refs.mid(0, 1), "too late"), 0);
}

void Smart210Test::aChangedDateMovesTheRecordAndItsRemainder()
{
    QSqlDatabase db = openDatabase();
    recordADay();
    run("UPDATE sales SET synced = 1");
    run("UPDATE supplements SET synced = 1");
    run("UPDATE remainders SET synced = 1");
    QList<ReviewData::Ref> sale;
    sale << ReviewData::Ref(ReviewData::Sale, "sale-1");
    QList<ReviewData::Ref> supply;
    supply << ReviewData::Ref(ReviewData::Supplement, "sup-1");

    QCOMPARE(ReviewData::setDate(db, sale, "2026-10-06"), 1);
    QCOMPARE(ReviewData::setDate(db, supply, "2026-10-06"), 1);

    QCOMPARE(ReviewData::sales(db, "2026-10-07").size(), 0); // gone from its old day
    QCOMPARE(ReviewData::sales(db, "2026-10-06").size(), 1);
    QCOMPARE(ReviewData::sales(db, "2026-10-06").at(0).state, ReviewData::Waiting);
    QCOMPARE(ReviewData::supplements(db, "2026-10-06").size(), 1);
    QCOMPARE(ReviewData::totals(db, "2026-10-06").remainders, 1); // the remainder followed its supplement
    QCOMPARE(ReviewData::totals(db, "2026-10-07").remainders, 0);
    QSqlQuery query(db);
    QVERIFY(query.exec("SELECT remainder_date, synced FROM remainders"));
    QVERIFY(query.next());
    QCOMPARE(query.value(0).toString(), QString("2026-10-06"));
    QCOMPARE(query.value(1).toInt(), 0); // and waits for the upload too

    QCOMPARE(ReviewData::setDate(db, sale, "2026-10-06"), 0);  // the same day: nothing changes
    QCOMPARE(ReviewData::setDate(db, sale, "2026-13-45"), 0);  // not a date
    QCOMPARE(ReviewData::setDate(db, sale, "yesterday"), 0);
    QCOMPARE(ReviewData::sales(db, "2026-10-06").size(), 1);
}

void Smart210Test::nozzlesAreNotCountedAsWaitingButGoUpWithTheNextUpload()
{
    QSqlDatabase db = openDatabase();
    run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (1, 'T1', 'D', 5000000, 100000)");
    MachineConfig config(db);
    addNozzleRows(QList<int>() << 1);
    BoardProtocol::BoardConfig board;
    board.fuelType = "D";
    board.density = 8300;
    board.pulsesX100 = 100000;
    QVERIFY(config.storeBoardConfig(1, board));

    QCOMPARE(Uploader::pending(db).value("nozzles"), 1);
    QCOMPARE(Uploader::pendingTotal(db), 0); // a nozzle is not a record: nothing waits for the cashier to upload

    run("INSERT INTO sales (id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id, sale_date) "
        "VALUES ('sale-1', '2026-10-07 14:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5, '2026-10-07')");
    QCOMPARE(Uploader::pendingTotal(db), 1);
    configureServer();
    server_->reply("/api/push/nozzles", 200, q("{'status':'ok','saved':['1'],'rejected':[]}"));
    server_->reply("/api/push/sales", 200, q("{'status':'ok','saved':['sale-1'],'rejected':[]}"));
    Uploader uploader(db);
    QString message;

    QVERIFY(uploadOk(uploader, &message));

    QCOMPARE(server_->log(), QStringList() << "POST /api/push/nozzles" << "POST /api/push/sales"); // the nozzle goes first
    QCOMPARE(message, QString("Uploaded 1 records.")); // the nozzle is not counted
    QCOMPARE(Uploader::pending(db).value("nozzles"), 0);
}

void Smart210Test::theChangesAreWrittenToATextFile()
{
    QSqlDatabase db = openDatabase();
    addUser(7, "Cash One", "pw");
    MachineConfig quiet(db); // no file: nothing is written
    quiet.logChange("server", "a:1", "b:2", 7);
    QVERIFY(!QFileInfo(changeLog()).exists());
    QVERIFY(quiet.recentChanges(10).isEmpty());

    MachineConfig config(db);
    config.setLogFile(changeLog());
    config.logChange("nozzle:1", "", "auto D 0.8300 1200.00 T1", 7);
    config.logChange("server", "127.0.0.1:80", "127.0.0.1:81", 0);

    QFile file(changeLog());
    QVERIFY(file.open(QIODevice::ReadOnly | QIODevice::Text));
    const QStringList lines = QString::fromUtf8(file.readAll()).split('\n', QString::SkipEmptyParts);
    file.close();
    QCOMPARE(lines.size(), 2);
    QVERIFY2(QRegExp("\\d{4}-\\d\\d-\\d\\d \\d\\d:\\d\\d:\\d\\d: nozzle:1: - -> auto D 0.8300 1200.00 T1 \\(Cash One\\)").exactMatch(lines.at(0)), qPrintable(lines.at(0)));
    QVERIFY2(QRegExp("\\d{4}-\\d\\d-\\d\\d \\d\\d:\\d\\d:\\d\\d: server: 127.0.0.1:80 -> 127.0.0.1:81").exactMatch(lines.at(1)), qPrintable(lines.at(1)));
    QCOMPARE(countRows("config_changes"), 0); // nothing in the database

    // an unknown line in the file is left out
    QVERIFY(file.open(QIODevice::Append | QIODevice::Text));
    file.write("something else\n");
    file.close();
    const QList<MachineConfig::Change> list = config.recentChanges(10);
    QCOMPARE(list.size(), 2);
    QCOMPARE(list.at(0).what, QString("server")); // newest first
    QCOMPARE(list.at(0).oldValue, QString("127.0.0.1:80"));
    QCOMPARE(list.at(0).userName, QString());
    QCOMPARE(list.at(1).what, QString("nozzle:1"));
    QCOMPARE(list.at(1).oldValue, QString());
    QCOMPARE(list.at(1).newValue, QString("auto D 0.8300 1200.00 T1"));
    QCOMPARE(list.at(1).userName, QString("Cash One"));
    QCOMPARE(config.recentChanges(1).size(), 1);
}

void Smart210Test::theReviewScreenChangesCommentsAndDatesOfTheSelectedRecord()
{
    setUpLoggedInTerminalWithAPin();
    recordADay();
    run("UPDATE sales SET synced = 1");
    run("UPDATE supplements SET synced = 1");
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    window.showPage("review");
    window.findChild<QDateEdit *>("reviewDate")->setDate(QDate(2026, 10, 7));
    QTableWidget *sales = window.findChild<QTableWidget *>("reviewSalesTable");
    QLineEdit *comment = window.findChild<QLineEdit *>("reviewComment");
    QLabel *status = window.findChild<QLabel *>("reviewStatus");
    QLabel *selected = window.findChild<QLabel *>("reviewSelected");
    QPushButton *saveComment = window.findChild<QPushButton *>("saveCommentButton");
    QPushButton *setDate = window.findChild<QPushButton *>("setDateButton");
    QPushButton *upload = window.findChild<QPushButton *>("uploadButton");
    QCOMPARE(sales->item(0, 8)->text(), QString("Uploaded"));
    QVERIFY(!saveComment->isEnabled()); // nothing is selected
    QVERIFY(!setDate->isEnabled());
    QVERIFY(!sales->item(0, 7)->toolTip().isEmpty()); // made at 09:00: the date is to be checked
    QCOMPARE(upload->text(), QString("Upload data (4 waiting)")); // the remainder, the loan, its settlement and the ticket

    // a comment on one record
    sales->selectRow(0);
    QCOMPARE(selected->text(), QString("1 selected"));
    QVERIFY(saveComment->isEnabled());
    comment->setText("paid by card");
    saveComment->click();

    QVERIFY2(status->text().contains("comment was saved"), qPrintable(status->text()));
    QCOMPARE(sales->item(0, 6)->text(), QString("paid by card"));
    QCOMPARE(sales->item(0, 8)->text(), QString("Waiting")); // it will go up again
    QCOMPARE(upload->text(), QString("Upload data (5 waiting)"));
    QCOMPARE(selected->text(), QString("1 selected")); // still selected
    QCOMPARE(footerWaiting(window), QString("Waiting for upload: 5"));
    saveComment->click();
    QCOMPARE(status->text(), QString("Nothing changed."));

    // another day for it
    window.findChild<QDateEdit *>("reviewSetDate")->setDate(QDate(2026, 10, 6));
    setDate->click();
    QVERIFY2(status->text().contains("moved to 2026-10-06"), qPrintable(status->text()));
    QCOMPARE(sales->rowCount(), 0);
    window.findChild<QDateEdit *>("reviewDate")->setDate(QDate(2026, 10, 6));
    QCOMPARE(sales->rowCount(), 1);
    QCOMPARE(sales->item(0, 7)->text(), QString("2026-10-06"));
    QCOMPARE(window.findChild<QLabel *>("reviewDayInfo")->text(), QString("1 waiting for upload"));

    // only one record is edited at a time: choosing another row leaves the first one
    window.findChild<QDateEdit *>("reviewDate")->setDate(QDate(2026, 10, 7));
    window.findChild<QTabWidget *>("reviewTabs")->setCurrentIndex(2);
    QTableWidget *debts = window.findChild<QTableWidget *>("reviewDebtTable");
    QCOMPARE(debts->rowCount(), 2);
    QCOMPARE(debts->selectionMode(), QAbstractItemView::SingleSelection);
    debts->selectAll(); // does nothing in a single-selection table
    QVERIFY(debts->selectionModel()->selectedRows().size() <= 1);
    debts->selectRow(0);
    debts->selectRow(1);
    QCOMPARE(debts->selectionModel()->selectedRows().size(), 1);
    QCOMPARE(selected->text(), QString("1 selected"));
    comment->setText("checked");
    saveComment->click();
    QVERIFY2(status->text().contains("comment was saved"), qPrintable(status->text()));
    QCOMPARE(debts->item(0, 6)->text(), QString(""));        // the other record is untouched
    QCOMPARE(debts->item(1, 6)->text(), QString("checked"));

    window.findChild<QTabWidget *>("reviewTabs")->setCurrentIndex(1);
    QTableWidget *supplies = window.findChild<QTableWidget *>("reviewSupplyTable");
    supplies->selectRow(0);
    comment->setText("manual fill");
    saveComment->click();
    QCOMPARE(supplies->item(0, 7)->text(), QString("manual fill"));
    QCOMPARE(supplies->item(0, 9)->text(), QString("Waiting"));
}

// a second day with a sale (and the ticket sold in it) that must stay behind when the first day goes up
static const char *otherDaySale =
    "INSERT INTO sales (id, created_at, customer_id, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, "
    "unpaid_amount, usage, sale_date, user_id) VALUES ('sale-8', '2026-10-08 10:00:00', 1, 'ticket', 'D', 750, 500, 'cash', 3750, 3750, 0, "
    "'sale', '2026-10-08', 5)";
static const char *otherDayTicket =
    "INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, sale_id, sale_date, supply_date, supplement_id, synced) "
    "VALUES (3000000022, 'D', 0, 500, 1, 'sale-8', '2026-10-08 10:00:00', NULL, NULL, 0)";

void Smart210Test::theUploaderCanBeLimitedToACheckoutDate()
{
    QSqlDatabase db = openDatabase();
    recordADay();
    run(otherDaySale);
    run(otherDayTicket);

    QCOMPARE(Uploader::pendingTotal(db), 8);
    QCOMPARE(Uploader::pendingTotal(db, "2026-10-07"), 6);
    QCOMPARE(Uploader::pendingTotal(db, "2026-10-08"), 2); // the sale and its ticket
    QCOMPARE(Uploader::pendingTotal(db, "2026-10-09"), 0);
    QCOMPARE(Uploader::pending(db, "2026-10-08").value("sales"), 1);
    QCOMPARE(Uploader::pending(db, "2026-10-08").value("tickets"), 1);
    QCOMPARE(Uploader::pending(db, "2026-10-07").value("tickets"), 1); // the ticket of the settlement
    QCOMPARE(Uploader::pending(db, "2026-10-09").value("nozzles"), 1); // the nozzles have no date
    QCOMPARE(Uploader::pendingTotal(db, "not a date"), 8);             // no date, no limit

    // a record without a date goes by the business day of its time
    run("UPDATE sales SET sale_date = NULL WHERE id = 'sale-8'");
    QCOMPARE(Uploader::pending(db, "2026-10-08").value("sales"), 1);
    run("UPDATE sales SET created_at = '2026-10-09 02:00:00' WHERE id = 'sale-8'"); // before 9 AM: the day before
    QCOMPARE(Uploader::pending(db, "2026-10-08").value("sales"), 1);
    QCOMPARE(Uploader::pending(db, "2026-10-09").value("sales"), 0);
}

void Smart210Test::theUploadOfAChosenDateSendsOnlyWhatWaitsOnThatDate()
{
    setUpLoggedInTerminalWithAPin();
    recordADay();
    run(otherDaySale);
    run(otherDayTicket);
    run("UPDATE sales SET synced = 1 WHERE id = 'sale-1'"); // uploaded before; the comment below makes it wait again
    serveUploads();
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    window.showPage("review");
    QDateEdit *date = window.findChild<QDateEdit *>("reviewDate");
    QPushButton *upload = window.findChild<QPushButton *>("uploadButton");
    QLabel *status = window.findChild<QLabel *>("uploadStatus");

    date->setDate(QDate(2026, 10, 8));
    QCOMPARE(upload->text(), QString("Upload data (2 waiting)")); // only that day
    date->setDate(QDate(2026, 10, 7));
    QCOMPARE(upload->text(), QString("Upload data (5 waiting)"));
    QCOMPARE(footerWaiting(window), QString("Waiting for upload: 7")); // the home page counts all days

    // the cashier edits one record of the 7th and uploads that day
    window.findChild<QTableWidget *>("reviewSalesTable")->selectRow(0);
    window.findChild<QLineEdit *>("reviewComment")->setText("checked");
    window.findChild<QPushButton *>("saveCommentButton")->click();
    QCOMPARE(upload->text(), QString("Upload data (6 waiting)"));
    upload->click();

    QTRY_COMPARE_WITH_TIMEOUT(status->text(), QString("Uploaded 6 records."), 10000);
    QSqlDatabase db = QSqlDatabase::database(connection_);
    QCOMPARE(Uploader::pendingTotal(db, "2026-10-07"), 0);
    QCOMPARE(Uploader::pendingTotal(db, "2026-10-08"), 2); // the other day still waits, nothing of it was sent
    QCOMPARE(Uploader::pendingTotal(db), 2);
    bool salesSent = false;
    for (int i = 0; i < server_->requests().size(); ++i) {
        const QByteArray body = server_->requests().at(i).body;
        QVERIFY2(!body.contains("3000000022") && !body.contains("sale-8"), "the other day was sent");
        if (server_->requests().at(i).path == "/api/push/sales") {
            salesSent = true;
            const QJsonArray rows = QJsonDocument::fromJson(body).object().value("rows").toArray();
            QCOMPARE(rows.size(), 1);
            QCOMPARE(rows.at(0).toObject().value("id").toString(), QString("sale-1"));
        }
    }
    QVERIFY(salesSent);
    QCOMPARE(footerWaiting(window), QString("Waiting for upload: 2"));

    // then the other day
    server_->reply("/api/push/sales", 200, q("{'status':'ok','saved':['sale-8'],'rejected':[]}"));
    server_->reply("/api/push/tickets", 200, q("{'status':'ok','saved':['0000003000000022'],'rejected':[]}"));
    date->setDate(QDate(2026, 10, 8));
    upload->click();
    QTRY_COMPARE_WITH_TIMEOUT(status->text(), QString("Uploaded 2 records."), 10000);
    QCOMPARE(Uploader::pendingTotal(db), 0);
}

// ---- the supply session (task 6.5) ----

namespace {

QString code(qlonglong key)
{
    return Barcode::fromKey(key);
}

}

QVariant Smart210Test::one(const QString &sql)
{
    QSqlQuery query(QSqlDatabase::database(connection_));
    if (!query.exec(sql) || !query.next()) {
        return QVariant();
    }

    return query.value(0);
}

// Ticket numbers, a cashier, nozzles 1 (auto), 2 (auto), 3 (manual), 4 (auto, no board) and sold tickets to fill them with.
void Smart210Test::setUpSupplying()
{
    setUpSelling();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    Settings(db).setInt(SettingKey::ManualSupplyDays, 100000);   // (the tests type in supplies of fixed days; the limit has its own test)
    addUser(7, "Cash One", "secret1");
    run("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (3000000041, 'D', 0, 1500, 1, 1)");
    run("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (3000000042, 'D', 0, 2000, 1, 1)");
    run("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (3000000043, 'D', 0, 500, 1, 1)");
    run("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (3000000044, 'A', 0, 500, 1, 1)");
    run("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (3000000045, 'D', 0, 500, 0, 1)"); // never sold
    run("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (3000000046, 'D', 0, 500, 2, 1)"); // used
    run("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (3000000047, 'D', 0, 500, 3, 1)"); // restricted
    MachineConfig config(db);
    storeNozzles(&config, connection_);
}

void Smart210Test::aTicketJoinsANozzlesQueueOnlyWhenItPassesTheChecks()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const QDateTime now = QDateTime::currentDateTime();

    QVERIFY(SupplyQueue::add(db, 1, "abc", "D", now).error.contains("not a ticket barcode"));
    QVERIFY(SupplyQueue::add(db, 1, code(3000000099), "D", now).error.contains("not known"));
    QVERIFY(SupplyQueue::add(db, 1, code(3000000045), "D", now).error.contains("never sold"));
    QVERIFY(SupplyQueue::add(db, 1, code(3000000046), "D", now).error.contains("already used"));
    QVERIFY(SupplyQueue::add(db, 1, code(3000000047), "D", now).error.contains("restricted"));
    QVERIFY(SupplyQueue::add(db, 1, code(3000000044), "D", now).error.contains("another fuel type"));
    QCOMPARE(SupplyQueue::totalX100(db, 1), qlonglong(0));

    const SupplyQueue::Scan first = SupplyQueue::add(db, 1, " " + code(3000000041) + " ", "D", now); // spaces around a scan are ignored
    QVERIFY2(first.ok, qPrintable(first.error));
    QCOMPARE(first.kgX100, qlonglong(1500));
    QVERIFY(SupplyQueue::add(db, 1, code(3000000042), "D", now).ok);
    QCOMPARE(SupplyQueue::totalX100(db, 1), qlonglong(3500));
    QCOMPARE(SupplyQueue::entries(db, 1).at(0).barcode, qlonglong(3000000041)); // in the order they came
    QCOMPARE(SupplyQueue::entries(db, 1).at(1).barcode, qlonglong(3000000042));

    // a ticket is in one queue only
    QVERIFY(SupplyQueue::add(db, 1, code(3000000041), "D", now).error.contains("already in the queue of nozzle 1"));
    QVERIFY(SupplyQueue::add(db, 2, code(3000000041), "D", now).error.contains("already in the queue of nozzle 1"));
    QCOMPARE(SupplyQueue::nozzleOf(db, 3000000041), 1);
    QCOMPARE(SupplyQueue::nozzleOf(db, 3000000043), 0);
    QVERIFY(LoanSettlement::scan(db, code(3000000041), "D").error.contains("queue of nozzle 1")); // and not for a loan either

    QVERIFY(SupplyQueue::remove(db, 1, 3000000041));
    QVERIFY(!SupplyQueue::remove(db, 1, 3000000041));
    QCOMPARE(SupplyQueue::totalX100(db, 1), qlonglong(2000));
    QVERIFY(SupplyQueue::add(db, 2, code(3000000041), "D", now).ok); // free again
    SupplyQueue::clear(db, 1);
    QCOMPARE(SupplyQueue::entries(db, 1).size(), 0);
    QCOMPARE(SupplyQueue::entries(db, 2).size(), 1);
}

void Smart210Test::anAutoFillTakesItsTicketsAndTheRestBecomesARemainder()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const QDateTime now = QDateTime(QDate(2026, 10, 9), QTime(10, 0, 0));
    QVERIFY(SupplyQueue::add(db, 1, code(3000000041), "D", now).ok);
    QVERIFY(SupplyQueue::add(db, 1, code(3000000042), "D", now).ok);
    QVERIFY(SupplyQueue::begin(db, 1, 3500, now));

    SupplyRecord::Reading reading;
    reading.resultId = 101;
    reading.dispensedX100 = 3000;   // 30.00 kg of the 35.00 that the tickets cover
    reading.litersX100 = 3614;
    reading.presetX100 = 3500;
    reading.reason = BoardProtocol::ReasonTankFullOrNoFlow;
    const SupplyRecord::Outcome outcome = SupplyRecord::record(db, 1, reading, now);

    QVERIFY2(outcome.ok, qPrintable(outcome.error));
    QCOMPARE(outcome.tickets, 2);
    QCOMPARE(outcome.remainderKgX100, qlonglong(500));
    QCOMPARE(outcome.remainderBarcode, QString("0000029500000001")); // sign 00000, the diesel remainder code 29, the first serial above the web app's
    const QString id = outcome.supplementId;
    QCOMPARE(one("SELECT out_amount_kg FROM supplements WHERE id = '" + id + "'").toInt(), 3000);
    QCOMPARE(one("SELECT preset_kg FROM supplements WHERE id = '" + id + "'").toInt(), 3500);   // what the tickets cover
    QCOMPARE(one("SELECT out_amount_lt FROM supplements WHERE id = '" + id + "'").toInt(), 3614);
    QCOMPARE(one("SELECT mode FROM supplements WHERE id = '" + id + "'").toString(), QString("auto"));
    QCOMPARE(one("SELECT board_result_id FROM supplements WHERE id = '" + id + "'").toInt(), 101);
    QCOMPARE(one("SELECT fuel_type FROM supplements WHERE id = '" + id + "'").toString(), QString("D"));
    QCOMPARE(one("SELECT synced FROM supplements WHERE id = '" + id + "'").toInt(), 0);
    QCOMPARE(one("SELECT supply_date = date(supplied_at, '-9 hours') FROM supplements WHERE id = '" + id + "'").toInt(), 1); // the business day of its time, at once

    // the two tickets are supplied under the supplement
    QCOMPARE(one("SELECT COUNT(*) FROM tickets WHERE supplement_id = '" + id + "' AND status = 2 AND synced = 0 AND supply_date = '2026-10-09 10:00:00'").toInt(), 2);
    // the rest: a remainder row, and a new ticket that is sold, belongs to no sale and has the reserved code
    QCOMPARE(one("SELECT amount_kg FROM remainders WHERE supplement_id = '" + id + "' AND ticket_barcode = 29500000001").toInt(), 500);
    QCOMPARE(one("SELECT status FROM tickets WHERE barcode = 29500000001").toInt(), int(TicketStatus::Sale));
    QVERIFY(one("SELECT sale_id FROM tickets WHERE barcode = 29500000001").isNull());
    QCOMPARE(one("SELECT sale_date FROM tickets WHERE barcode = 29500000001").toString(), QString("2026-10-09 10:00:00"));
    QCOMPARE(one("SELECT amount_kg FROM tickets WHERE barcode = 29500000001").toInt(), 500);
    QCOMPARE(one("SELECT synced FROM tickets WHERE barcode = 29500000001").toInt(), 0);

    // the queue and the session are gone; the remainder ticket is a new sold ticket and can be queued again
    QCOMPARE(SupplyQueue::entries(db, 1).size(), 0);
    QVERIFY(!SupplyQueue::session(db, 1).exists);
    QVERIFY(SupplyQueue::add(db, 1, code(29500000001), "D", now).ok);

    // all of it waits for the upload: the supplement, its remainder and the three tickets
    const QMap<QString, int> pending = Uploader::pending(db);
    QCOMPARE(pending.value("supplements"), 1);
    QCOMPARE(pending.value("remainders"), 1);
    QCOMPARE(pending.value("tickets"), 3);
}

void Smart210Test::aFillThatReachedItsPresetLeavesNoRemainder()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const QDateTime now = QDateTime::currentDateTime();

    // the board stops at the preset exactly (the firmware sees to the pulses): no remainder, and no tolerance on the terminal
    QVERIFY(SupplyQueue::add(db, 1, code(3000000041), "D", now).ok);
    QVERIFY(SupplyQueue::begin(db, 1, 1500, now));
    SupplyRecord::Reading reading;
    reading.resultId = 1;
    reading.dispensedX100 = 1500;
    reading.litersX100 = 1800;
    reading.reason = BoardProtocol::ReasonPresetReached;
    SupplyRecord::Outcome outcome = SupplyRecord::record(db, 1, reading, now);
    QVERIFY2(outcome.ok, qPrintable(outcome.error));
    QVERIFY(outcome.remainderBarcode.isEmpty());
    QCOMPARE(countRows("remainders"), 0);
    QCOMPARE(one("SELECT out_amount_kg FROM supplements").toInt(), 1500); // what came out is what is recorded

    // 0.2 kg short at the preset is a remainder; so is 0.05 kg short because the tank was full (any shortfall is one)
    QVERIFY(SupplyQueue::add(db, 1, code(3000000042), "D", now).ok);
    QVERIFY(SupplyQueue::begin(db, 1, 2000, now));
    reading.resultId = 2;
    reading.dispensedX100 = 1980;
    outcome = SupplyRecord::record(db, 1, reading, now);
    QVERIFY2(outcome.ok, qPrintable(outcome.error));
    QCOMPARE(outcome.remainderKgX100, qlonglong(20));

    QVERIFY(SupplyQueue::add(db, 1, code(3000000043), "D", now).ok);
    QVERIFY(SupplyQueue::begin(db, 1, 500, now));
    reading.resultId = 3;
    reading.dispensedX100 = 495;
    reading.reason = BoardProtocol::ReasonTankFullOrNoFlow;
    outcome = SupplyRecord::record(db, 1, reading, now);
    QCOMPARE(outcome.remainderKgX100, qlonglong(5));
    QCOMPARE(countRows("remainders"), 2);
}

void Smart210Test::aResultIsRecordedOnce()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const QDateTime now = QDateTime::currentDateTime();
    QVERIFY(SupplyQueue::add(db, 1, code(3000000041), "D", now).ok);
    QVERIFY(SupplyQueue::begin(db, 1, 1500, now));
    SupplyRecord::Reading reading;
    reading.resultId = 55;
    reading.dispensedX100 = 1000;
    reading.litersX100 = 1200;
    reading.reason = BoardProtocol::ReasonTankFullOrNoFlow;

    const SupplyRecord::Outcome first = SupplyRecord::record(db, 1, reading, now);
    const SupplyRecord::Outcome again = SupplyRecord::record(db, 1, reading, now); // the board still holds it: its answer to ACK was lost

    QVERIFY(first.ok && !first.duplicate);
    QVERIFY(again.ok && again.duplicate);
    QCOMPARE(again.supplementId, first.supplementId);
    QCOMPARE(countRows("supplements"), 1);
    QCOMPARE(countRows("remainders"), 1);                                  // not a second remainder either
    QCOMPARE(one("SELECT COUNT(*) FROM tickets WHERE amount_kg = 500 AND status = 1 AND sale_id IS NULL").toInt(), 3); // the remainder ticket, 43 and 44

    // a later fill of the nozzle has another id (here no session: it waits for tickets)
    reading.resultId = 56;
    QVERIFY(SupplyRecord::record(db, 1, reading, now).ok);
    QCOMPARE(countRows("supplements"), 2);

}


void Smart210Test::aSupplyTypedInNeedsTicketsThatCoverItAndAStartedFillUsesItsOwn()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    Session session(db);
    QVERIFY(session.login(7, "secret1"));
    MachineConfig config(db);
    FakeBoards boards; // no board answers: the converter is not even connected
    BoardBus bus;
    quickBus(&bus, &boards);
    SupplySelection selection(&bus, &config);
    SupplySession supply(db, &bus, &config, &selection, &session);
    QSignalSpy remainders(&supply, SIGNAL(remainderMade(QString)));
    QSignalSpy recorded(&supply, SIGNAL(recorded(int,QString)));
    const QDateTime when = QDateTime(QDate(2026, 10, 13), QTime(9, 15, 0));
    QList<qlonglong> tickets;
    QString why;

    // the tickets are needed, of the fuel, covering the weight
    QVERIFY(!supply.recordManual(3, 2000, 2410, tickets, when, &why));
    QVERIFY(why.contains("Enter the tickets first"));
    tickets << 3000000041;                                                // 15.00 kg
    QVERIFY(!supply.recordManual(3, 2000, 2410, tickets, when, &why));
    QVERIFY2(why.contains("must cover everything"), qPrintable(why));
    tickets << 3000000044;                                                // another fuel
    QVERIFY(!supply.recordManual(3, 2000, 2410, tickets, when, &why));
    QVERIFY(why.contains("another fuel type"));
    tickets.clear();
    QCOMPARE(countRows("supplements"), 0);                                // nothing was changed by the refusals
    QCOMPARE(one("SELECT COUNT(*) FROM tickets WHERE status = 2").toInt(), 1);   // (the used one of the test data)

    tickets.clear();
    tickets << 3000000041 << 3000000043;                                  // 15.00 + 5.00
    QVERIFY2(supply.recordManual(3, 2000, 2410, tickets, when, &why), qPrintable(why));
    QCOMPARE(recorded.size(), 1);
    QCOMPARE(countRows("supplements"), 1);
    QCOMPARE(one("SELECT mode FROM supplements").toString(), QString("manual"));
    QCOMPARE(one("SELECT supplied_at FROM supplements").toString(), QString("2026-10-13 09:15:00"));   // the time the cashier typed
    QCOMPARE(one("SELECT out_amount_kg FROM supplements").toInt(), 2000);
    QCOMPARE(one("SELECT out_amount_lt FROM supplements").toInt(), 2410);
    QCOMPARE(one("SELECT preset_kg FROM supplements").toInt(), 2000);
    QCOMPARE(one("SELECT COUNT(*) FROM tickets WHERE supplement_id IS NOT NULL AND status = 2 AND synced = 0").toInt(), 2);
    QCOMPARE(countRows("remainders"), 0);                                 // dispensed in full: no remainder
    QCOMPARE(remainders.size(), 0);
    QCOMPARE(Uploader::pending(db).value("supplements"), 1);              // it goes up as it is
    QVERIFY(!supply.recordManual(3, 2000, 2410, tickets, when, &why));    // the tickets are used now
    QVERIFY(why.contains("already used"));

    // less dispensed than the tickets cover: the rest is a remainder ticket, as with an automatic fill (any shortfall, there is no tolerance)
    run("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (3000000048, 'D', 1, 1000, 1, 1)");
    run("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (3000000049, 'D', 0, 500, 1, 1)");
    tickets.clear();
    tickets << 3000000048;                                                // 10.00 kg against 7.00 dispensed
    QVERIFY2(supply.recordManual(3, 700, 843, tickets, when, &why), qPrintable(why));
    QCOMPARE(countRows("remainders"), 1);
    QCOMPARE(remainders.size(), 1);
    QCOMPARE(one("SELECT amount_kg FROM remainders").toInt(), 300);
    QCOMPARE(one("SELECT COUNT(*) FROM tickets WHERE spec IS NULL AND sale_id IS NULL AND amount_kg = 300 AND status = 1 AND synced = 0").toInt(), 1); // the remainder ticket
    QCOMPARE(one("SELECT out_amount_kg || '/' || preset_kg FROM supplements WHERE out_amount_kg = 700").toString(), QString("700/1000"));
    tickets.clear();
    tickets << 3000000049;                                                // 5.00 kg against 4.97 dispensed
    QVERIFY2(supply.recordManual(3, 497, 598, tickets, when, &why), qPrintable(why));
    QCOMPARE(countRows("remainders"), 2);                                 // 0.03 kg: a remainder ticket too
    tickets.clear();

    // the fill of nozzle 2 was started from the app, then the board died: the reading takes its tickets, the rest is a remainder
    const QDateTime now = QDateTime::currentDateTime();
    tickets.clear();
    QVERIFY(SupplyQueue::add(db, 2, code(3000000042), "D", now).ok);
    QVERIFY(SupplyQueue::begin(db, 2, 2000, now));
    QVERIFY(supply.isLocked(2));
    QVERIFY(!supply.recordManual(2, 2100, 2530, tickets, now, &why));     // more than the tickets cover
    QVERIFY(why.contains("More than the tickets cover (20.00 kg)"));
    QVERIFY(supply.isLocked(2));
    QVERIFY2(supply.recordManual(2, 1500, 1807, tickets, now, &why), qPrintable(why));
    QCOMPARE(remainders.size(), 3);
    QVERIFY(!supply.isLocked(2));
    QCOMPARE(one("SELECT COUNT(*) FROM supplements WHERE mode = 'manual' AND out_amount_kg = 1500 AND preset_kg = 2000").toInt(), 1);
    QCOMPARE(countRows("remainders"), 3);

    // bad input
    QVERIFY(!supply.recordManual(1, 0, 100, tickets, now, &why));
    QVERIFY(why.contains("Enter the weight"));
    QVERIFY(!supply.recordManual(9, 100, 100, tickets, now, &why));
    QVERIFY(why.contains("Choose a nozzle"));
}

// The boards of the tests: nozzles 1 and 2 auto (the nozzles are in the database as storeNozzles() left them), 3 manual.
namespace {

struct SupplyBench {
    explicit SupplyBench(QSqlDatabase database)
        : db(database), config(db), session(db), selection(&bus, &config), supply(db, &bus, &config, &selection, &session)
    {
        boards.add(1);
        boards.add(2);
        boards.add(3);
        boards.board(3).config.mode = BoardProtocol::ModeManual;
        quickBus(&bus, &boards);
        bus.addBoard(1);
        bus.addBoard(2);
        bus.addBoard(3);
    }

    QSqlDatabase db;
    MachineConfig config;
    Session session;
    FakeBoards boards;
    BoardBus bus;
    SupplySelection selection;
    SupplySession supply;
};

}

void Smart210Test::theManualInputPageKeepsTheKgAndTheRemainderTogether()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    SupplyBench bench(db);
    QVERIFY(bench.session.login(7, "secret1"));
    ManualSupplyPage page(db, &bench.config, &bench.supply);
    page.reload();
    QLineEdit *code = page.findChild<QLineEdit *>("msBarcode");
    QLineEdit *kg = page.findChild<QLineEdit *>("msKgField");
    QLineEdit *remainder = page.findChild<QLineEdit *>("msRemainderField");
    QLineEdit *liters = page.findChild<QLineEdit *>("msLitersField");
    QPushButton *save = page.findChild<QPushButton *>("msSaveButton");
    QSignalSpy printed(&bench.supply, SIGNAL(remainderMade(QString)));

    // two tickets, 15.00 + 5.00: all of it is dispensed by itself, the remainder is zero
    code->setText(Barcode::fromKey(3000000041));
    QTest::keyClick(code, Qt::Key_Return);
    code->setText(Barcode::fromKey(3000000043));
    QTest::keyClick(code, Qt::Key_Return);
    QCOMPARE(kg->text(), QString("20.00"));
    QCOMPARE(remainder->text(), QString("0.00"));
    QVERIFY(save->isEnabled());

    // the kg typed: the remainder follows
    kg->clear();
    QTest::keyClicks(kg, "17.5");
    QCOMPARE(remainder->text(), QString("2.50"));
    QVERIFY(!liters->text().isEmpty());
    QVERIFY(save->isEnabled());

    // the remainder typed: the kg follows
    remainder->clear();
    QTest::keyClicks(remainder, "5");
    QCOMPARE(kg->text(), QString("15.00"));
    // more than was dispensed cannot be saved: kg above the tickets
    kg->clear();
    QTest::keyClicks(kg, "20.5");
    QVERIFY(remainder->text().isEmpty());
    QVERIFY(!save->isEnabled());

    // fully dispensed again: the remainder is zero and nothing is printed
    kg->clear();
    QTest::keyClicks(kg, "20");
    QCOMPARE(remainder->text(), QString("0.00"));
    save->click();
    QCOMPARE(countRows("supplements"), 1);
    QCOMPARE(countRows("remainders"), 0);
    QCOMPARE(printed.size(), 0);
    QVERIFY(remainder->text().isEmpty()); // the form is empty again

    // a remainder of 3.00: the supply is saved with a remainder ticket, which is to be printed
    run("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (3000000048, 'D', 1, 1000, 1, 1)");
    code->setText(Barcode::fromKey(3000000048));
    QTest::keyClick(code, Qt::Key_Return);
    remainder->clear();
    QTest::keyClicks(remainder, "3");
    QCOMPARE(kg->text(), QString("7.00"));
    save->click();
    QCOMPARE(countRows("remainders"), 1);
    QCOMPARE(one("SELECT amount_kg FROM remainders").toInt(), 300);
    QCOMPARE(printed.size(), 1);
}
void Smart210Test::theLitresOfAManualSupplyAreWorkedOutAndItsTimeIsLimited()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    SupplyBench bench(db);
    QVERIFY(bench.session.login(7, "secret1"));
    Settings(db).setInt(SettingKey::ManualSupplyDays, 3);
    ManualSupplyPage page(db, &bench.config, &bench.supply);
    page.reload();
    QLineEdit *code = page.findChild<QLineEdit *>("msBarcode");
    QLineEdit *kg = page.findChild<QLineEdit *>("msKgField");
    QLineEdit *liters = page.findChild<QLineEdit *>("msLitersField");
    QDateTimeEdit *time = page.findChild<QDateTimeEdit *>("msTimeEdit");

    // the litres are never typed: the field is read only and out of the way of the cursor, and it follows the kg and the density (0.83)
    QVERIFY(liters->isReadOnly());
    QCOMPARE(liters->focusPolicy(), Qt::NoFocus);
    code->setText(Barcode::fromKey(3000000041));
    QTest::keyClick(code, Qt::Key_Return);
    QCOMPARE(liters->text(), QString("18.07"));                         // 15.00 kg / 0.83
    kg->clear();
    QTest::keyClicks(kg, "10");
    QCOMPARE(liters->text(), QString("12.05"));
    QTest::keyClicks(liters, "99");                                     // typing in it changes nothing
    QCOMPARE(liters->text(), QString("12.05"));

    // the time cannot be earlier than the setting says: the editor stops at the start of the day 3 days back
    const QDateTime now = QDateTime::currentDateTime();
    QCOMPARE(time->minimumDateTime(), QDateTime(now.date().addDays(-3), QTime(0, 0, 0)));

    // and the supply itself is refused (a record typed in cannot be made older than that, or come from the future)
    QList<qlonglong> tickets;
    tickets << 3000000041;
    QString why;
    QVERIFY(!bench.supply.recordManual(3, 1000, 1205, tickets, now.addDays(-4), &why));
    QVERIFY2(why.contains("3 days back"), qPrintable(why));
    QVERIFY(!bench.supply.recordManual(3, 1000, 1205, tickets, now.addSecs(3600), &why));
    QVERIFY2(why.contains("future"), qPrintable(why));
    QCOMPARE(countRows("supplements"), 0);
    QVERIFY2(bench.supply.recordManual(3, 1000, 1205, tickets, QDateTime(now.date().addDays(-3), QTime(0, 5, 0)), &why), qPrintable(why));   // the limit itself is fine
    QCOMPARE(countRows("supplements"), 1);

    // 0: today only
    Settings(db).setInt(SettingKey::ManualSupplyDays, 0);
    tickets.clear();
    tickets << 3000000042;
    QVERIFY(!bench.supply.recordManual(3, 1000, 1205, tickets, now.addDays(-1), &why));
    QVERIFY2(why.contains("must be today"), qPrintable(why));
    QVERIFY2(bench.supply.recordManual(3, 1000, 1205, tickets, now, &why), qPrintable(why));
}

void Smart210Test::theDaysBackOfAManualSupplyAreSetOnMachineConfig()
{
    setUpSellingTerminal();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", db);
    window.showPage("settings");
    window.showPage("pin");
    window.findChild<QLineEdit *>("pinField")->setText("4821");
    window.findChild<QPushButton *>("pinOkButton")->click();
    QCOMPARE(window.currentPage(), QString("machine"));

    QSpinBox *days = window.findChild<QSpinBox *>("manualSupplyDaysSpin");
    QVERIFY(days != 0);
    QCOMPARE(days->value(), 3);                                          // three days unless the admin says otherwise
    days->setValue(7);
    QCOMPARE(Settings(db).value(SettingKey::ManualSupplyDays), QString()); // not saved before Save
    window.findChild<QPushButton *>("saveManualSupplyOptionsButton")->click();
    QCOMPARE(Settings(db).intValue(SettingKey::ManualSupplyDays, -1), 7);
    QVERIFY(window.findChild<QListWidget *>("changeLog")->item(0)->text().contains("-> 7"));

    window.showPage("settings");
    window.showPage("pin");
    window.findChild<QLineEdit *>("pinField")->setText("4821");
    window.findChild<QPushButton *>("pinOkButton")->click();
    QCOMPARE(window.findChild<QSpinBox *>("manualSupplyDaysSpin")->value(), 7);   // shown again when the page is reopened
}
void Smart210Test::scannedTicketsFillTheChosenNozzleAndTheResultIsRecorded()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    SupplyBench bench(db);
    QVERIFY(bench.session.login(7, "secret1"));
    QSignalSpy messages(&bench.supply, SIGNAL(message(QString,bool)));
    QSignalSpy recorded(&bench.supply, SIGNAL(recorded(int,QString)));
    QSignalSpy remainders(&bench.supply, SIGNAL(remainderMade(QString)));
    QVERIFY(startBus(&bench.bus, &bench.boards));
    QTRY_VERIFY_WITH_TIMEOUT(bench.bus.isOnline(1) && bench.bus.isOnline(2) && bench.bus.isOnline(3), 3000);

    // no nozzle is chosen: the scan has nowhere to go
    bench.supply.scanned(code(3000000041));
    QVERIFY(messages.last().at(0).toString().contains("Choose a nozzle first"));
    QVERIFY(!messages.last().at(1).toBool());
    QCOMPARE(SupplyQueue::entries(db, 1).size(), 0);

    // choose nozzle 1: the supplier's scans go to its queue
    QVERIFY(bench.selection.select(1));
    QTRY_COMPARE_WITH_TIMEOUT(bench.selection.current(), 1, 3000);
    QTRY_VERIFY_WITH_TIMEOUT(bench.selection.isSelected(1), 3000);
    bench.supply.scanned(code(3000000041));
    QVERIFY2(messages.last().at(1).toBool(), qPrintable(messages.last().at(0).toString()));
    QVERIFY(messages.last().at(0).toString().contains("15.00 kg"));
    bench.supply.scanned(code(3000000042));
    QVERIFY(messages.last().at(0).toString().contains("Together 35.00 kg"));
    bench.supply.scanned(code(3000000041));
    QVERIFY(messages.last().at(0).toString().contains("already in the queue"));
    bench.supply.scanned(code(3000000044));
    QVERIFY(messages.last().at(0).toString().contains("another fuel type"));
    QCOMPARE(SupplyQueue::totalX100(db, 1), qlonglong(3500));

    // the preset is the total weight and reaches the board by itself; the gun is taken up: the session is saved, the queue is locked
    QString why;
    QTRY_COMPARE_WITH_TIMEOUT(bench.boards.board(1).presetG, quint32(35000), 3000);
    QTRY_COMPARE_WITH_TIMEOUT(bench.boards.board(1).state, int(BoardProtocol::StateArmed), 3000);
    QVERIFY(!bench.supply.isLocked(1));
    QVERIFY(bench.boards.simulator().liftGun(1));
    QTRY_VERIFY_WITH_TIMEOUT(bench.supply.isLocked(1), 3000);
    QCOMPARE(SupplyQueue::session(db, 1).presetX100, qlonglong(3500));
    QTRY_COMPARE_WITH_TIMEOUT(bench.boards.board(1).state, int(BoardProtocol::StateDispensing), 3000);
    QTRY_VERIFY_WITH_TIMEOUT(SupplyQueue::session(db, 1).resultId != 0, 3000);
    QCOMPARE(SupplyQueue::session(db, 1).resultId, bench.boards.board(1).resultId);
    QVERIFY(!bench.supply.add(1, code(3000000043), &why));
    QVERIFY(why.contains("started"));
    QVERIFY(!bench.supply.remove(1, 3000000041, &why));
    QVERIFY(!bench.supply.clear(1, &why));
    QCOMPARE(SupplyQueue::entries(db, 1).size(), 2);

    // the fill runs to the preset: the result is read, saved, and only then acknowledged
    bench.boards.simulator().tick(80000);
    QTRY_COMPARE_WITH_TIMEOUT(recorded.size(), 1, 5000);
    QTRY_COMPARE_WITH_TIMEOUT(bench.boards.board(1).state, int(BoardProtocol::StateIdle), 3000);
    QCOMPARE(recorded.at(0).at(0).toInt(), 1);
    QCOMPARE(remainders.size(), 0);               // the fill reached the preset: no remainder
    QCOMPARE(one("SELECT out_amount_kg FROM supplements").toInt(), 3500);
    QCOMPARE(one("SELECT preset_kg FROM supplements").toInt(), 3500);
    QCOMPARE(one("SELECT out_amount_lt FROM supplements").toInt(), 4217);   // 35 kg at 0.83 kg/L
    QCOMPARE(one("SELECT COUNT(*) FROM pragma_table_info('supplements') WHERE name = 'user_id'").toInt(), 0);
    QCOMPARE(one("SELECT COUNT(*) FROM tickets WHERE status = 2 AND supplement_id IS NOT NULL").toInt(), 2);
    QVERIFY(!bench.supply.isLocked(1));
    QCOMPARE(SupplyQueue::entries(db, 1).size(), 0);
    QVERIFY(messages.last().at(0).toString().contains("35.00 kg dispensed"));
    QCOMPARE(Uploader::pending(db).value("supplements"), 1);
    QCOMPARE(Uploader::pending(db).value("tickets"), 2);
}

// A barcode that cannot be scanned is typed in: the 11 digits after the station sign, or all 16; the same checks as a scan
// The press tickets published before the go-live date have no rows, only the ranges of their publishes (TicketLegacy): a ticket with no row inside a range is
// a ticket sold on paper before go-live, made when it is scanned; one with a row follows its row; one outside the ranges is not known.
void Smart210Test::aTicketSoldOnPaperBeforeGoLiveIsMadeWhenItIsUsed()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const QDateTime now = QDateTime::currentDateTime();

    QVERIFY(SupplyQueue::add(db, 1, code(3000000150), "D", now).error.contains("not known"));        // no ranges yet

    QString error;
    QVERIFY2(TicketLegacy::replace(db, "00000", QJsonDocument::fromJson(q("[{'code':'03','from':100,'to':199,'fuel_type':'D','spec':0,'kg':'15.00','date':'2026-09-20'}]")).array(), &error),
             qPrintable(error));
    QCOMPARE(countRows("ticket_legacy"), 1);
    QVERIFY(TicketLegacy::isOld(db, 3000000150));
    QVERIFY(!TicketLegacy::isOld(db, 3000000099));
    QVERIFY(!TicketLegacy::isOld(db, 3000000200));

    // outside the ranges: still not known, and no row is made
    QVERIFY(SupplyQueue::add(db, 1, code(3000000099), "D", now).error.contains("not known"));
    QVERIFY(SupplyQueue::add(db, 1, code(3000000200), "D", now).error.contains("not known"));
    QCOMPARE(one("SELECT COUNT(*) FROM tickets WHERE barcode IN (3000000099, 3000000200)").toInt(), 0);

    // inside: its row is made, sold on the day of the publish, the weight of its spec, counted as uploaded (the web app makes the same row when the use is reported)
    QVERIFY2(SupplyQueue::add(db, 1, code(3000000150), "D", now).ok, "inside a range");
    QSqlQuery row(db);
    QVERIFY(row.exec("SELECT status, amount_kg, sale_date, synced, spec, fuel_type FROM tickets WHERE barcode = 3000000150") && row.next());
    QCOMPARE(row.value(0).toInt(), int(TicketStatus::Sale));
    QCOMPARE(row.value(1).toLongLong(), qlonglong(1500));
    QCOMPARE(row.value(2).toString(), QString("2026-09-20 00:00:00"));
    QCOMPARE(row.value(3).toInt(), 1);
    QCOMPARE(row.value(4).toInt(), 0);
    QCOMPARE(row.value(5).toString(), QString("D"));
    QCOMPARE(Uploader::pending(db).value("tickets"), 0);                                                // nothing to upload for a ticket that is only scanned

    // a ticket in a range that has a row follows its row (one still in the stock room was never sold)
    run("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (3000000160, 'D', 0, 1500, 0, 1)");
    QVERIFY(SupplyQueue::add(db, 1, code(3000000160), "D", now).error.contains("never sold"));

    // the loan screen takes it too
    const LoanSettlement::Scan scan = LoanSettlement::scan(db, code(3000000170), "D");
    QVERIFY2(scan.ok, qPrintable(scan.error));
    QCOMPARE(scan.kgX100, qlonglong(1500));

    // the fill: the ticket is supplied and waits for the upload (the web app makes its row from that report)
    QVERIFY(SupplyQueue::begin(db, 1, 1500, now));
    SupplyRecord::Reading reading;
    reading.resultId = 1;
    reading.dispensedX100 = 1500;
    reading.litersX100 = 1800;
    reading.reason = BoardProtocol::ReasonPresetReached;
    const SupplyRecord::Outcome outcome = SupplyRecord::record(db, 1, reading, now);
    QVERIFY2(outcome.ok, qPrintable(outcome.error));
    QCOMPARE(one("SELECT status FROM tickets WHERE barcode = 3000000150").toInt(), int(TicketStatus::Supply));
    QCOMPARE(one("SELECT synced FROM tickets WHERE barcode = 3000000150").toInt(), 0);
    QCOMPARE(Uploader::pending(db).value("tickets"), 1);

    // finished and uploaded long ago, the purger still keeps its row: with no row it would count as sold on paper again and be accepted a second time
    run("UPDATE tickets SET synced = 1, sale_date = '2026-09-01 09:00:00', supply_date = '2026-09-01 10:00:00' WHERE barcode = 3000000150");
    run("INSERT INTO tickets (barcode, fuel_type, amount_kg, status, sale_date, supply_date, synced) VALUES (3000000021, 'D', 500, 2, '2026-09-01 09:00:00', '2026-09-01 10:00:00', 1)");
    Purger::purge(db, 3, QDateTime(QDate(2026, 10, 7), QTime(12, 0, 0)));
    QCOMPARE(one("SELECT COUNT(*) FROM tickets WHERE barcode = 3000000150").toInt(), 1);
    QCOMPARE(one("SELECT COUNT(*) FROM tickets WHERE barcode = 3000000021").toInt(), 0);                // an ordinary finished ticket goes
    QVERIFY(SupplyQueue::add(db, 1, code(3000000150), "D", now).error.contains("already used"));
}

void Smart210Test::theConfigDownloadBringsTheRangesOfTheOldPublishes()
{
    QSqlDatabase db = openDatabase();
    QJsonObject config = configObject();
    config.insert("ticket_sign", "00007");
    config.insert("ticket_legacy", QJsonDocument::fromJson(q("[{'code':'06','from':1,'to':150,'fuel_type':'D','spec':0,'kg':'5.00','date':'2026-09-20'},"
                                                              "{'code':'02','from':1,'to':20000,'fuel_type':'D','spec':2,'kg':'15.00','date':'2026-09-21'}]")).array());
    QString error;

    QVERIFY2(SyncStore(db).applyConfig(config, &error), qPrintable(error));

    QCOMPARE(countRows("ticket_legacy"), 2);
    QVERIFY(TicketLegacy::isOld(db, 706000000001LL));       // sign 00007, code 06, serial 1
    QVERIFY(TicketLegacy::isOld(db, 706000000150LL));
    QVERIFY(!TicketLegacy::isOld(db, 706000000151LL));
    QVERIFY(TicketLegacy::isOld(db, 702000020000LL));
    QCOMPARE(one("SELECT kg FROM ticket_legacy WHERE spec = 2").toInt(), 1500);

    // a server that sends none of it leaves them; one that sends an empty list takes them away (a go-live date that was removed)
    QVERIFY(SyncStore(db).applyConfig(configObject(), &error));
    QCOMPARE(countRows("ticket_legacy"), 2);
    config.insert("ticket_legacy", QJsonArray());
    QVERIFY(SyncStore(db).applyConfig(config, &error));
    QCOMPARE(countRows("ticket_legacy"), 0);
}

void Smart210Test::aTicketTypedInGoesToTheQueueLikeAScannedOne()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    SupplyBench bench(db);
    QVERIFY(bench.session.login(7, "secret1"));
    QSignalSpy messages(&bench.supply, SIGNAL(message(QString,bool)));
    QVERIFY(startBus(&bench.bus, &bench.boards));
    QTRY_VERIFY_WITH_TIMEOUT(bench.bus.isOnline(1) && bench.bus.isOnline(2), 3000);

    // no nozzle is chosen: nowhere to go
    QVERIFY(!bench.supply.typed("03000000041"));
    QVERIFY(messages.last().at(0).toString().contains("Choose a nozzle first"));
    QVERIFY(bench.selection.select(1));
    QTRY_VERIFY_WITH_TIMEOUT(bench.selection.current() == 1 && bench.selection.isSelected(1), 3000);

    // what is not a ticket number
    foreach (const QString &bad, QStringList() << "123" << "030000000411" << "0300000004a" << "0000003000000041000") {
        QVERIFY2(!bench.supply.typed(bad), qPrintable(bad));
        QVERIFY2(messages.last().at(0).toString().contains("Type the 11 digits"), qPrintable(bad));
    }
    QCOMPARE(SupplyQueue::entries(db, 1).size(), 0);

    // the 11 digits (the code and the serial; spaces and dashes are ignored), then the 16 digits of another ticket
    QVERIFY2(bench.supply.typed(" 030 0000-0041 "), qPrintable(messages.last().at(0).toString()));
    QVERIFY(messages.last().at(1).toBool());
    QVERIFY(messages.last().at(0).toString().contains("15.00 kg"));
    QVERIFY(bench.supply.typed("0000003000000042"));
    QVERIFY(messages.last().at(0).toString().contains("Together 35.00 kg"));
    QCOMPARE(SupplyQueue::totalX100(db, 1), qlonglong(3500));

    // the checks of a scan: a ticket that is in the queue already, of another fuel type, never sold, not a ticket of this station
    QVERIFY(!bench.supply.typed("03000000041"));
    QVERIFY(messages.last().at(0).toString().contains("already in the queue"));
    QVERIFY(!bench.supply.typed("03000000044"));
    QVERIFY(messages.last().at(0).toString().contains("another fuel type"));
    QVERIFY(!bench.supply.typed("03000000045"));
    QVERIFY(messages.last().at(0).toString().contains("never sold"));
    QVERIFY(!bench.supply.typed("03000000099"));
    QVERIFY(messages.last().at(0).toString().contains("not known"));
    QCOMPARE(SupplyQueue::totalX100(db, 1), qlonglong(3500));

    // the sign of another station: the 16 digits are looked up as they are (not known here); without a sign the 11 digits cannot be completed
    QVERIFY(!bench.supply.typed("1234503000000041"));
    QVERIFY(messages.last().at(0).toString().contains("not known"));
    Settings(db).setValue(SettingKey::TicketSign, "");
    QVERIFY(!bench.supply.typed("03000000043"));
    QVERIFY(messages.last().at(0).toString().contains("sign of the station is not known"));
    QVERIFY(bench.supply.typed("0000003000000043"));
}

// the box on the Sessions tab: Enter (or Add) types the number in; it is cleared when the ticket was added and kept when it was refused
void Smart210Test::theEnterTicketBoxOfTheSessionsTabTypesTicketsIn()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    SupplyBench bench(db);
    QVERIFY(bench.session.login(7, "secret1"));
    QVERIFY(startBus(&bench.bus, &bench.boards));
    QTRY_VERIFY_WITH_TIMEOUT(bench.bus.isOnline(1), 3000);
    SupplyPage page(db, &bench.bus, &bench.config, &bench.selection, &bench.supply);
    QLineEdit *field = page.findChild<QLineEdit *>("supplyTicketField");
    QPushButton *add = page.findChild<QPushButton *>("supplyTicketButton");
    QLabel *message = page.findChild<QLabel *>("supplyMessage");
    QVERIFY(field != 0 && add != 0 && message != 0);
    QVERIFY(field->placeholderText().contains("11 or 16 digits"));

    // no nozzle chosen: refused, the digits stay to be sent again
    field->setText("03000000041");
    QTest::keyClick(field, Qt::Key_Return);
    QVERIFY(message->text().contains("Choose a nozzle first"));
    QCOMPARE(field->text(), QString("03000000041"));

    // a nozzle that is tapped in the table is the one the ticket is for: it is chosen (its board beeps) and the ticket joins its queue
    QMetaObject::invokeMethod(&page, "rowClicked", Q_ARG(int, 0));
    QTest::keyClick(field, Qt::Key_Return);                                  // Enter
    QCOMPARE(bench.selection.current(), 1);
    QVERIFY2(message->text().contains("ticket added, 15.00 kg"), qPrintable(message->text()));
    QCOMPARE(field->text(), QString());
    QCOMPARE(SupplyQueue::entries(db, 1).size(), 1);

    field->setText("03000000042");
    add->click();                                                            // the Add button does the same
    QVERIFY(message->text().contains("Together 35.00 kg"));
    QCOMPARE(SupplyQueue::entries(db, 1).size(), 2);

    // letters cannot be typed; a number that is wrong stays in the box
    QTest::keyClicks(field, "03a0b00");
    QCOMPARE(field->text(), QString("030" "00"));
    field->setText("123");
    QTest::keyClick(field, Qt::Key_Return);
    QVERIFY(message->text().contains("Type the 11 digits"));
    QCOMPARE(field->text(), QString("123"));
}

void Smart210Test::aResultIsRecordedWhileNobodyIsLoggedIn()
{
    // the fill code needs no user: a result is saved even when the session has ended meanwhile (the web disabled the cashier, for example)
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    SupplyBench bench(db);
    QVERIFY(bench.session.login(7, "secret1"));
    QSignalSpy recorded(&bench.supply, SIGNAL(recorded(int,QString)));
    QVERIFY(startBus(&bench.bus, &bench.boards));
    QTRY_VERIFY_WITH_TIMEOUT(bench.bus.isOnline(1), 3000);
    QVERIFY(bench.selection.select(1));
    QTRY_VERIFY_WITH_TIMEOUT(bench.selection.isSelected(1), 3000);
    bench.supply.scanned(code(3000000041));
    QTRY_COMPARE_WITH_TIMEOUT(bench.boards.board(1).state, int(BoardProtocol::StateArmed), 3000);
    QVERIFY(bench.boards.simulator().liftGun(1));
    QTRY_COMPARE_WITH_TIMEOUT(bench.boards.board(1).state, int(BoardProtocol::StateDispensing), 3000);
    QTRY_VERIFY_WITH_TIMEOUT(bench.supply.isLocked(1), 3000);

    bench.session.logout();
    QVERIFY(!bench.session.isLoggedIn());
    bench.boards.simulator().tick(80000);

    QTRY_COMPARE_WITH_TIMEOUT(recorded.size(), 1, 5000);
    QTRY_COMPARE_WITH_TIMEOUT(bench.boards.board(1).state, int(BoardProtocol::StateIdle), 3000);
    QCOMPARE(countRows("supplements"), 1);
}

void Smart210Test::aStoppedFillMakesARemainderTicket()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    SupplyBench bench(db);
    QVERIFY(bench.session.login(7, "secret1"));
    QSignalSpy remainders(&bench.supply, SIGNAL(remainderMade(QString)));
    QSignalSpy recorded(&bench.supply, SIGNAL(recorded(int,QString)));
    QVERIFY(startBus(&bench.bus, &bench.boards));
    QTRY_VERIFY_WITH_TIMEOUT(bench.bus.isOnline(1), 3000);
    QVERIFY(bench.selection.select(1));
    QTRY_VERIFY_WITH_TIMEOUT(bench.selection.isSelected(1), 3000);
    bench.supply.scanned(code(3000000041));
    bench.supply.scanned(code(3000000042));
    QTRY_COMPARE_WITH_TIMEOUT(bench.boards.board(1).presetG, quint32(35000), 3000);
    QTRY_COMPARE_WITH_TIMEOUT(bench.boards.board(1).state, int(BoardProtocol::StateArmed), 3000);
    QVERIFY(bench.boards.simulator().liftGun(1));
    QTRY_COMPARE_WITH_TIMEOUT(bench.boards.board(1).state, int(BoardProtocol::StateDispensing), 3000);
    QTRY_VERIFY_WITH_TIMEOUT(bench.supply.isLocked(1), 3000);

    bench.boards.simulator().tick(10000);   // 5.00 kg out of the 35.00
    bench.boards.simulator().stopFill(1);   // the supplier stops it

    QTRY_COMPARE_WITH_TIMEOUT(remainders.size(), 1, 5000);
    QCOMPARE(remainders.at(0).at(0).toString(), QString("0000029500000001"));
    QCOMPARE(one("SELECT out_amount_kg FROM supplements").toInt(), 500);
    QCOMPARE(one("SELECT preset_kg FROM supplements").toInt(), 3500);
    QCOMPARE(one("SELECT amount_kg FROM remainders").toInt(), 3000);   // 30.00 kg come back as a ticket
    QCOMPARE(one("SELECT amount_kg FROM tickets WHERE barcode = 29500000001").toInt(), 3000);
    QCOMPARE(one("SELECT COUNT(*) FROM tickets WHERE status = 2 AND supplement_id IS NOT NULL").toInt(), 2);   // both scanned tickets are used up
    QTRY_COMPARE_WITH_TIMEOUT(bench.boards.board(1).state, int(BoardProtocol::StateIdle), 3000);

    // the remainder ticket works as any sold ticket of the fuel: it can go to a queue
    QTRY_COMPARE_WITH_TIMEOUT(bench.bus.lastStatus(1).state, int(BoardProtocol::StateIdle), 3000);
    QVERIFY(bench.selection.select(1));
    QTRY_VERIFY_WITH_TIMEOUT(bench.selection.isSelected(1), 3000);
    bench.supply.scanned(remainders.at(0).at(0).toString());
    QCOMPARE(SupplyQueue::totalX100(db, 1), qlonglong(3000));
}

void Smart210Test::aResultSeenAgainAfterALostAcknowledgementIsNotRecordedTwice()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    SupplyBench bench(db);
    QVERIFY(bench.session.login(7, "secret1"));
    QSignalSpy recorded(&bench.supply, SIGNAL(recorded(int,QString)));
    QVERIFY(startBus(&bench.bus, &bench.boards));
    QTRY_VERIFY_WITH_TIMEOUT(bench.bus.isOnline(1), 3000);
    QVERIFY(bench.selection.select(1));
    QTRY_VERIFY_WITH_TIMEOUT(bench.selection.isSelected(1), 3000);
    bench.supply.scanned(code(3000000041));
    QTRY_COMPARE_WITH_TIMEOUT(bench.boards.board(1).state, int(BoardProtocol::StateArmed), 3000);
    QVERIFY(bench.boards.simulator().liftGun(1));
    QTRY_COMPARE_WITH_TIMEOUT(bench.boards.board(1).state, int(BoardProtocol::StateDispensing), 3000);
    bench.boards.simulator().tick(40000);
    QTRY_COMPARE_WITH_TIMEOUT(recorded.size(), 1, 5000);
    QTRY_COMPARE_WITH_TIMEOUT(bench.boards.board(1).state, int(BoardProtocol::StateIdle), 3000);

    // the board forgot that it was acknowledged (a restart, a lost answer): it holds the same result again
    const quint32 id = one("SELECT board_result_id FROM supplements").toUInt();
    bench.boards.board(1).state = BoardProtocol::StateResult;
    bench.boards.board(1).resultId = id;
    bench.boards.board(1).lastAckedId = 0;
    QTRY_COMPARE_WITH_TIMEOUT(bench.boards.board(1).state, int(BoardProtocol::StateIdle), 5000);   // acknowledged again, nothing recorded again

    QCOMPARE(recorded.size(), 1);
    QCOMPARE(countRows("supplements"), 1);
}



void Smart210Test::aManualModeFillIsNotWorkedWithAtAll()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    SupplyBench bench(db);
    QVERIFY(bench.session.login(7, "secret1"));
    bench.boards.board(3).flowGramsPerSecond = 1000;
    QSignalSpy messages(&bench.supply, SIGNAL(message(QString,bool)));
    QSignalSpy recorded(&bench.supply, SIGNAL(recorded(int,QString)));
    QVERIFY(startBus(&bench.bus, &bench.boards));
    QTRY_VERIFY_WITH_TIMEOUT(bench.bus.isOnline(3), 3000);

    // the supplier types 8 kg and takes the gun up; the board runs and ends it by itself, and the Smart210 only polls
    QVERIFY(bench.boards.simulator().typeAmount(3, 8000));
    QTRY_COMPARE_WITH_TIMEOUT(bench.bus.lastStatus(3).presetG, quint32(8000), 3000);
    QVERIFY(bench.boards.simulator().liftGun(3));
    QTRY_COMPARE_WITH_TIMEOUT(bench.bus.lastStatus(3).state, int(BoardProtocol::StateDispensing), 3000);
    bench.boards.simulator().tick(20000);
    QTRY_COMPARE_WITH_TIMEOUT(bench.boards.board(3).state, int(BoardProtocol::StateIdle), 3000);
    QTest::qWait(400);

    QCOMPARE(recorded.size(), 0);                                             // nothing is reported or recorded
    QCOMPARE(countRows("supplements"), 0);
    QCOMPARE(countRows("remainders"), 0);
    QCOMPARE(messages.size(), 0);
    QCOMPARE(Uploader::pendingTotal(db), 0);
    QVERIFY(!bench.boards.board(3).commands.contains(BoardProtocol::CmdGetResult));
    QVERIFY(!bench.boards.board(3).commands.contains(BoardProtocol::CmdAckResult));

    // scanning for a manual nozzle is refused with the reason
    bench.supply.scanned(code(3000000041));   // nothing is chosen
    QString why;
    QVERIFY(!bench.supply.add(3, code(3000000041), &why));
    QVERIFY(why.contains("manual mode"));
}


void Smart210Test::theSupplierWorksAtTheDispenserAndTheSessionsTabFollowsTheFill()
{
    setUpLoggedInTerminalWithAPin();
    FakeBoards boards;
    boards.add(1);
    const int port = freePort();
    Settings(QSqlDatabase::database(connection_)).setInt(SettingKey::ConverterPort, port);
    AppConfig config; // the boards are listened to
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    boards.connectTo(quint16(port));
    QTRY_VERIFY_WITH_TIMEOUT(server_->log().contains("GET /api/pull/ticket_loans?limit=200"), 10000);
    QTest::qWait(300);
    QSqlDatabase db = QSqlDatabase::database(connection_);
    Settings(db).setValue(SettingKey::TicketSign, "00000");
    {
        Settings settings(db);
        setTicketCodes(settings);
    }
    Settings(db).setValue(SettingKey::TicketSerialBase, "500000000");
    run("INSERT OR REPLACE INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (3000000041, 'D', 0, 1500, 1, 1)");
    run("INSERT OR REPLACE INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (3000000042, 'D', 0, 2000, 1, 1)");

    window.showPage("supply");
    window.findChild<SupplyPage *>()->setDoneMs(3000);
    QTableWidget *table = window.findChild<QTableWidget *>("supplyTable");
    QTableWidget *queue = window.findChild<QTableWidget *>("supplyQueueTable");
    QLabel *total = window.findChild<QLabel *>("supplyQueueTotal");
    QLabel *message = window.findChild<QLabel *>("supplyMessage");
    QLabel *current = window.findChild<QLabel *>("supplyCurrent");
    SupplierScanner *scanner = window.findChild<SupplierScanner *>();
    QVERIFY(scanner != 0);
    QTRY_COMPARE_WITH_TIMEOUT(table->item(0, 3)->text(), QString("Free"), 5000);
    QCOMPARE(queue->rowCount(), 0);

    // a scan with no nozzle chosen says so
    QMetaObject::invokeMethod(scanner, "barcodeScanned", Q_ARG(QString, code(3000000041)));
    QVERIFY(message->text().contains("Choose a nozzle first"));

    // the supplier chooses the nozzle with its key and scans: the tickets are listed (barcode, fuel type, amount)
    boards.pressSelect(1);
    QTRY_COMPARE_WITH_TIMEOUT(current->text(), QString("Tickets of nozzle 1"), 5000);
    QMetaObject::invokeMethod(scanner, "barcodeScanned", Q_ARG(QString, code(3000000041)));
    QMetaObject::invokeMethod(scanner, "barcodeScanned", Q_ARG(QString, code(3000000042)));
    QTRY_COMPARE_WITH_TIMEOUT(queue->rowCount(), 2, 3000);
    QCOMPARE(queue->item(0, 0)->text(), code(3000000041));
    QCOMPARE(queue->item(0, 1)->text(), QString("D"));
    QCOMPARE(queue->item(1, 2)->text(), QString("20.00"));
    QTRY_VERIFY_WITH_TIMEOUT(total->text().contains("2 tickets, together 35.00 kg"), 3000);

    // the preset reaches the board by itself (the dispenser shows it): the session is Set
    QTRY_COMPARE_WITH_TIMEOUT(boards.board(1).state, int(BoardProtocol::StateArmed), 5000);
    QCOMPARE(boards.board(1).presetG, quint32(35000));
    QTRY_COMPARE_WITH_TIMEOUT(table->item(0, 3)->text(), QString("Set"), 3000);
    QCOMPARE(table->item(0, 4)->text(), QString("35.00 - 2"));

    // the supplier takes the gun up: the fill starts, the tickets are locked
    QVERIFY(boards.simulator().liftGun(1));
    QTRY_COMPARE_WITH_TIMEOUT(boards.board(1).state, int(BoardProtocol::StateDispensing), 5000);
    QTRY_VERIFY_WITH_TIMEOUT(total->text().contains("Started"), 3000);
    QTRY_COMPARE_WITH_TIMEOUT(table->item(0, 3)->text(), QString("Working"), 3000);
    QMetaObject::invokeMethod(scanner, "barcodeScanned", Q_ARG(QString, code(3000000043)));
    QVERIFY(message->text().contains("started"));
    QCOMPARE(queue->rowCount(), 2);

    // the supplier stops the fill after 5 kg: the screen says what was done and that the remainder ticket did not print (no printer set up)
    boards.simulator().tick(10000);
    QTRY_COMPARE_WITH_TIMEOUT(table->item(0, 5)->text(), QString("5.00"), 3000);
    boards.simulator().stopFill(1);
    QTRY_VERIFY_WITH_TIMEOUT(message->text().contains("was not printed"), 5000);
    QVERIFY(message->text().contains("Remainders screen"));
    QCOMPARE(SupplyRecord::unprintedRemainders(db), 1);
    QCOMPARE(one("SELECT out_amount_kg FROM supplements").toInt(), 500);
    QCOMPARE(one("SELECT amount_kg FROM remainders").toInt(), 3000);

    // the finished session stays as Done for a while, with its tickets, and then goes
    QTRY_COMPARE_WITH_TIMEOUT(table->item(0, 3)->text(), QString("Done"), 2000);
    QCOMPARE(table->item(0, 5)->text(), QString("5.00"));
    QCOMPARE(table->item(0, 4)->text(), QString("35.00 - 2"));
    QCOMPARE(queue->rowCount(), 2);
    QVERIFY(total->text().contains("Done"));
    QTRY_COMPARE_WITH_TIMEOUT(table->item(0, 3)->text(), QString("Selected"), 8000);          // the nozzle stays the chosen one
    QCOMPARE(queue->rowCount(), 0);
    QCOMPARE(footerWaiting(window), QString("Waiting for upload: 5")); // supplement, remainder, 3 tickets
}

void Smart210Test::theSessionSetsThePresetOnEveryScanStartsWithTheGunAndClearsWithTheClearKey()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    SupplyBench bench(db);
    QVERIFY(bench.session.login(7, "secret1"));
    QSignalSpy messages(&bench.supply, SIGNAL(message(QString,bool)));
    QSignalSpy recorded(&bench.supply, SIGNAL(recorded(int,QString)));
    QVERIFY(startBus(&bench.bus, &bench.boards));
    QTRY_VERIFY_WITH_TIMEOUT(bench.bus.isOnline(1), 3000);

    // the supplier's select key, then a scan: the preset is the queue's weight, and it follows every change of the queue
    bench.boards.simulator().pressSelect(1);
    QTRY_VERIFY_WITH_TIMEOUT(bench.selection.current() == 1, 3000);
    bench.supply.scanned(code(3000000043));                               // 5.00 kg
    QTRY_COMPARE_WITH_TIMEOUT(bench.boards.board(1).state, int(BoardProtocol::StateArmed), 3000);
    QCOMPARE(bench.boards.board(1).presetG, quint32(5000));
    bench.supply.scanned(code(3000000041));                               // + 15.00 kg
    QTRY_COMPARE_WITH_TIMEOUT(bench.boards.board(1).presetG, quint32(20000), 3000);
    QVERIFY(!bench.supply.isLocked(1));                                   // not started: nobody has taken the gun up

    // the clear key of the dispenser drops the preset and the queue
    bench.boards.simulator().pressClearKey(1);
    QTRY_COMPARE_WITH_TIMEOUT(SupplyQueue::entries(db, 1).size(), 0, 3000);
    QTRY_COMPARE_WITH_TIMEOUT(bench.boards.board(1).presetG, quint32(0), 3000);   // the app sent SELECT again: the board forgot its preset
    QVERIFY(messages.last().at(0).toString().contains("cleared at the dispenser"));
    QTest::qWait(400);
    QCOMPARE(bench.boards.board(1).state, int(BoardProtocol::StateIdle));   // SET_PRESET zero: the board has no preset, nothing is armed again

    // new tickets, a new preset; the gun is lifted: the fill starts by itself, the app saves the session and locks the queue
    bench.supply.scanned(code(3000000041));
    bench.supply.scanned(code(3000000043));
    QTRY_COMPARE_WITH_TIMEOUT(bench.boards.board(1).presetG, quint32(20000), 3000);
    QTRY_COMPARE_WITH_TIMEOUT(bench.boards.board(1).state, int(BoardProtocol::StateArmed), 3000);
    QVERIFY(bench.boards.simulator().liftGun(1));
    QTRY_VERIFY_WITH_TIMEOUT(bench.supply.isLocked(1), 3000);
    QCOMPARE(SupplyQueue::session(db, 1).presetX100, qlonglong(2000));
    QTRY_VERIFY_WITH_TIMEOUT(SupplyQueue::session(db, 1).resultId != 0, 3000);
    QCOMPARE(bench.boards.board(1).commands.count(BoardProtocol::CmdStart), 1);   // the gun was reported, the Smart210 sent START once
    QString why;
    QVERIFY(!bench.supply.add(1, code(3000000042), &why));
    QVERIFY(why.contains("started"));
    bench.supply.scanned(code(3000000042));                               // the supplier's scan after the start is refused as well
    QCOMPARE(SupplyQueue::entries(db, 1).size(), 2);
    bench.boards.simulator().pressClearKey(1);                            // the clear key does nothing to a fill that runs
    QTest::qWait(400);
    QCOMPARE(SupplyQueue::entries(db, 1).size(), 2);

    // the fill runs to the preset: recorded with the tickets as always
    bench.boards.simulator().tick(80000);
    QTRY_COMPARE_WITH_TIMEOUT(recorded.size(), 1, 5000);
    QCOMPARE(one("SELECT COUNT(*) FROM tickets WHERE supplement_id IS NOT NULL").toInt(), 2);
    QCOMPARE(one("SELECT out_amount_kg FROM supplements").toInt(), 2000);
    QCOMPARE(countRows("remainders"), 0);
}



void Smart210Test::theManualInputScreenRecordsASupplyWithItsTickets()
{
    setUpLoggedInTerminalWithAPin();
    setUpSelling();
    Settings(QSqlDatabase::database(connection_)).setInt(SettingKey::ManualSupplyDays, 100000);   // (this test types in a supply of a fixed day)
    run("UPDATE nozzles SET mode = 1, density = 8300 WHERE number = 2");     // nozzle 2 works in manual mode
    run("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (3000000041, 'D', 0, 1500, 1, 1)");
    run("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (3000000043, 'D', 0, 500, 1, 1)");
    run("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (3000000044, 'A', 0, 500, 1, 1)");
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));

    window.showPage("manual");
    QCOMPARE(window.currentPage(), QString("manual"));
    QVERIFY(window.findChild<QPushButton *>("navSupplies")->isChecked());
    QTabWidget *tabs = 0;
    foreach (QTabWidget *candidate, window.findChildren<QTabWidget *>()) {
        if (candidate->count() == 2 && candidate->tabText(0) == "Sessions") {
            tabs = candidate;
        }
    }
    QVERIFY(tabs != 0);
    QCOMPARE(tabs->tabText(1), QString("Manual input"));              // two tabs: no tickets to assign, no reading by hand, no remainders

    QComboBox *nozzle = window.findChild<QComboBox *>("msNozzleCombo");
    QDateTimeEdit *time = window.findChild<QDateTimeEdit *>("msTimeEdit");
    QLineEdit *density = window.findChild<QLineEdit *>("msDensityField");
    QLineEdit *preset = window.findChild<QLineEdit *>("msPresetField");
    QLineEdit *kg = window.findChild<QLineEdit *>("msKgField");
    QLineEdit *liters = window.findChild<QLineEdit *>("msLitersField");
    QLineEdit *barcode = window.findChild<QLineEdit *>("msBarcode");
    QTableWidget *tickets = window.findChild<QTableWidget *>("msTickets");
    QLabel *summary = window.findChild<QLabel *>("msSummary");
    QLabel *status = window.findChild<QLabel *>("msStatus");
    QPushButton *save = window.findChild<QPushButton *>("msSaveButton");
    QCOMPARE(nozzle->count(), 1);                                      // only the nozzle in manual mode can be chosen
    QCOMPARE(nozzle->itemText(0), QString("Nozzle 2 (D)"));
    QCOMPARE(density->text(), QString("0.8300"));                      // the density of the board is shown
    QVERIFY(density->isReadOnly());
    QVERIFY(!save->isEnabled());
    QVERIFY(summary->text().contains("Type the ticket barcodes"));

    time->setDateTime(QDateTime(QDate(2026, 10, 9), QTime(9, 30, 0)));
    barcode->setText(code(3000000044));                                // another fuel
    barcode->returnPressed();
    QVERIFY(status->text().contains("another fuel type"));
    barcode->setText(code(3000000041));                                // typed with the keyboard, Enter adds it
    barcode->returnPressed();
    QCOMPARE(tickets->rowCount(), 1);
    QVERIFY(barcode->text().isEmpty());
    QCOMPARE(preset->text(), QString("15.00"));                        // the preset is the tickets' total...
    QCOMPARE(kg->text(), QString("15.00"));                            // ...the dispensed kg is set to it by itself...
    QCOMPARE(liters->text(), QString("18.07"));                        // ...and the litres follow from the density (15 / 0.83)
    QVERIFY(save->isEnabled());
    barcode->setText(code(3000000041));                                // the same again
    barcode->returnPressed();
    QVERIFY(status->text().contains("in the list already"));
    barcode->setText(code(3000000043));
    barcode->returnPressed();
    QCOMPARE(tickets->rowCount(), 2);
    QCOMPARE(preset->text(), QString("20.00"));
    QCOMPARE(kg->text(), QString("20.00"));
    QCOMPARE(liters->text(), QString("24.10"));
    QVERIFY(save->isEnabled());

    // the kg is free to edit: the litres and the remainder follow, and it must stay within the tickets
    QLineEdit *remainder = window.findChild<QLineEdit *>("msRemainderField");
    QCOMPARE(remainder->text(), QString("0.00"));                      // dispensed in full: no remainder
    kg->selectAll();
    QTest::keyClicks(kg, "19.98");
    QCOMPARE(liters->text(), QString("24.07"));
    QCOMPARE(remainder->text(), QString("0.02"));
    QVERIFY(summary->text().contains("remainder ticket of 0.02 kg"));  // 0.02 kg: a remainder ticket (there is no tolerance)
    QVERIFY(save->isEnabled());
    kg->selectAll();
    QTest::keyClicks(kg, "19.90");                                     // 0.10 kg less than the tickets: a remainder ticket
    QCOMPARE(remainder->text(), QString("0.10"));
    QVERIFY(summary->text().contains("remainder ticket of 0.10 kg"));
    QVERIFY(save->isEnabled());
    remainder->selectAll();                                            // the remainder typed: the kg and the litres follow
    QTest::keyClicks(remainder, "4.5");
    QCOMPARE(kg->text(), QString("15.50"));
    QCOMPARE(liters->text(), QString("18.67"));
    QVERIFY(save->isEnabled());
    kg->selectAll();
    QTest::keyClicks(kg, "20.50");                                     // more than the tickets cover
    QVERIFY(remainder->text().isEmpty());
    QVERIFY(!save->isEnabled());
    kg->selectAll();
    QTest::keyClicks(kg, "20.00");
    QVERIFY(save->isEnabled());

    QLineEdit *comment = window.findChild<QLineEdit *>("msCommentField");
    QVERIFY(comment != 0);
    QTest::keyClicks(comment, "  paid in cash at the counter ");
    save->click();

    QVERIFY2(status->text().contains("recorded with 2 tickets"), qPrintable(status->text()));
    QCOMPARE(one("SELECT comment FROM supplements").toString(), QString("paid in cash at the counter"));   // kept with the supply, trimmed
    QVERIFY(comment->text().isEmpty());                                                                    // the form is empty again
    QVERIFY(kg->text().isEmpty());
    QVERIFY(preset->text().isEmpty());
    QCOMPARE(tickets->rowCount(), 0);
    QSqlDatabase db = QSqlDatabase::database(connection_);
    QCOMPARE(one("SELECT out_amount_kg FROM supplements").toInt(), 2000);
    QCOMPARE(one("SELECT out_amount_lt FROM supplements").toInt(), 2410);
    QCOMPARE(one("SELECT preset_kg FROM supplements").toInt(), 2000);   // what the tickets cover
    QCOMPARE(one("SELECT mode FROM supplements").toString(), QString("manual"));
    QCOMPARE(one("SELECT nozzle_id FROM supplements").toInt(), 20);   // nozzle 2
    QCOMPARE(one("SELECT supplied_at FROM supplements").toString(), QString("2026-10-09 09:30:00"));
    QCOMPARE(Uploader::pendingTotal(db), 3);                           // the supply and its two tickets are ready for the upload
    QCOMPARE(footerWaiting(window), QString("Waiting for upload: 3"));
    window.showPage("review");
    window.findChild<QDateEdit *>("reviewDate")->setDate(QDate(2026, 10, 9));
    QTableWidget *reviewSupplies = window.findChild<QTableWidget *>("reviewSupplyTable");
    QCOMPARE(reviewSupplies->rowCount(), 1);
    QCOMPARE(reviewSupplies->item(0, reviewSupplies->columnCount() - 1)->text(), QString("Waiting"));
}


void Smart210Test::theRemainderTicketsScreenPrintsATicketAgain()
{
    setUpLoggedInTerminalWithAPin();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    run("INSERT INTO supplements (id, fuel_type, nozzle_id, out_amount_lt, out_amount_kg, preset_kg, mode, supplied_at, synced) "
        "VALUES ('sup-r', 'D', 20, 600, 500, 3500, 'auto', '2026-10-09 09:30:00', 0)");
    run("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, sale_date, synced) VALUES (29500000001, 'D', NULL, 3000, 1, '2026-10-09 09:30:00', 0)");
    run("INSERT INTO remainders (id, supplement_id, ticket_barcode, fuel_type, amount_kg, created_at, synced) "
        "VALUES ('rem-r', 'sup-r', 29500000001, 'D', 3000, '2026-10-09 09:30:00', 0)");
    run("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, sale_date, synced) VALUES (29500000002, 'D', NULL, 500, 2, '2026-10-09 09:00:00', 0)"); // used already
    run("INSERT INTO remainders (id, supplement_id, ticket_barcode, fuel_type, amount_kg, created_at, synced) "
        "VALUES ('rem-u', 'sup-r', 29500000002, 'D', 500, '2026-10-09 09:00:00', 0)");
    FakePrinter printer;
    QVERIFY(printer.listen());
    printer.goodStatus();
    Settings(db).setValue(SettingKey::PrinterHost, "127.0.0.1");
    Settings(db).setInt(SettingKey::PrinterPort, printer.port());
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", db);

    window.showPage("remainders");
    QCOMPARE(window.currentPage(), QString("remainders"));
    QVERIFY(window.findChild<QPushButton *>("navRemainders")->isChecked());            // a part of the program of its own, not a tab
    QVERIFY(window.findChild<QPushButton *>("navRemainders")->property("alert").toBool());   // one is not printed (the used one needs no print)
    QCOMPARE(window.findChild<QPushButton *>("navRemainders")->toolTip(), QString("1 not printed"));
    QTableWidget *table = window.findChild<QTableWidget *>("remainderTable");
    QPushButton *print = window.findChild<QPushButton *>("remainderPrintButton");
    QLabel *status = window.findChild<QLabel *>("remainderStatus");
    QCOMPARE(table->rowCount(), 2);
    QCOMPARE(table->item(0, 4)->text(), QString("0000029500000001"));      // newest first
    QCOMPARE(table->item(0, 3)->text(), QString("30.00"));
    QCOMPARE(table->item(0, 2)->text(), QString("D"));
    QCOMPARE(table->item(0, 1)->text(), QString("2"));
    QCOMPARE(table->item(0, 5)->text(), QString("Not printed"));
    QCOMPARE(table->item(1, 5)->text(), QString("Used"));
    QVERIFY(!print->isEnabled());

    // print it
    table->selectRow(0);
    QVERIFY(print->isEnabled());
    print->click();
    QTRY_COMPARE_WITH_TIMEOUT(printer.jobs().size(), 1, 5000);
    QVERIFY(printer.jobs().at(0).startsWith(QByteArray("\x1B\x40", 2)));
    QVERIFY(printer.jobs().at(0).count(QByteArray("\x1D\x76\x30", 3)) >= 3);      // the title, the details and the barcode are bitmaps
    QTRY_VERIFY_WITH_TIMEOUT(status->text().contains("was sent to the printer"), 5000);
    QCOMPARE(table->item(0, 5)->text(), QString("Printed"));
    QVERIFY(!one("SELECT printed_at FROM remainders WHERE id = 'rem-r'").isNull());
    QVERIFY(!window.findChild<QPushButton *>("navRemainders")->property("alert").toBool());

    // as often as needed
    table->selectRow(0);
    print->click();
    QTRY_COMPARE_WITH_TIMEOUT(printer.jobs().size(), 2, 5000);

    // a printer that is off: nothing is marked, the screen says so
    run("UPDATE remainders SET printed_at = NULL WHERE id = 'rem-r'");
    printer.close();
    window.showPage("remainders");
    table->selectRow(0);
    print->click();
    QTRY_VERIFY_WITH_TIMEOUT(status->text().contains("was not printed"), 8000);
    QVERIFY(one("SELECT printed_at FROM remainders WHERE id = 'rem-r'").isNull());
    QCOMPARE(table->item(0, 5)->text(), QString("Not printed"));
}

void Smart210Test::anUploadNotesWhenARecordWasSent()
{
    QSqlDatabase db = openDatabase();
    recordADay();
    configureServer();
    serveUploads();
    QCOMPARE(one("SELECT COUNT(*) FROM sales WHERE uploaded_at IS NOT NULL").toInt(), 0);

    Uploader uploader(db);
    QString message;
    QVERIFY(uploadOk(uploader, &message));

    const QString stamp = QDateTime::currentDateTime().toString("yyyy-MM-dd HH:mm");
    QVERIFY(one("SELECT uploaded_at FROM sales WHERE id = 'sale-1'").toString().startsWith(stamp.left(13)));
    QVERIFY(!one("SELECT uploaded_at FROM supplements").isNull());
    QVERIFY(!one("SELECT uploaded_at FROM remainders").isNull());
    QVERIFY(!one("SELECT uploaded_at FROM ticket_loans").isNull());
    QVERIFY(!one("SELECT uploaded_at FROM ticket_loan_settlements").isNull());
    QVERIFY(!one("SELECT uploaded_at FROM tickets WHERE barcode = 3000000011").isNull());
}

void Smart210Test::aRestoreOfTheServersDatabaseMakesTheTerminalSendAgainWhatItUploadedAfterTheDump()
{
    QSqlDatabase db = openDatabase();
    recordADay();
    // what was uploaded before and after the dump (taken at 12:00); the margin for clocks is 5 minutes
    run("UPDATE sales SET synced = 1, uploaded_at = '2026-10-07 10:00:00' WHERE id = 'sale-1'");                       // before: the dump holds it
    run("INSERT INTO sales (id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id, "
        "synced, uploaded_at) VALUES ('sale-near', '2026-10-07 11:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5, 1, '2026-10-07 11:56:00')");   // 4 minutes before: resent
    run("INSERT INTO sales (id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id, "
        "synced, uploaded_at) VALUES ('sale-early', '2026-10-07 11:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5, 1, '2026-10-07 11:50:00')"); // 10 minutes before: not
    run("INSERT INTO sales (id, created_at, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, usage, user_id, "
        "synced, uploaded_at) VALUES ('sale-after', '2026-10-07 09:00:00', 'spot', 'D', 750, 100, 'cash', 750, 750, 'sale', 5, 1, '2026-10-07 15:00:00')"); // made in the morning, uploaded after the dump
    run("UPDATE supplements SET synced = 1, uploaded_at = '2026-10-07 13:00:00'");
    run("UPDATE remainders SET synced = 1, uploaded_at = '2026-10-07 13:00:00'");
    run("UPDATE ticket_loans SET synced = 1, uploaded_at = '2026-10-07 13:00:00'");
    run("UPDATE ticket_loan_settlements SET synced = 1, uploaded_at = '2026-10-07 13:00:00'");
    run("UPDATE tickets SET synced = 1, uploaded_at = '2026-10-07 13:00:00' WHERE barcode = 3000000011");
    run("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (3000000012, 'D', 0, 500, 1, 1)"); // pulled from the web app: never uploaded
    run("INSERT INTO nozzles (id, number, tank_id, fuel_type, board_address, synced, deleted) VALUES (7, 7, 1, 'D', 7, 1, 0)");
    QCOMPARE(Uploader::pendingTotal(db), 0);

    QJsonObject config = configObject();
    QJsonObject restore;
    restore.insert("restored_at", "2026-10-08 09:00:00");
    restore.insert("since", "2026-10-07 12:00:00");
    config.insert("restore", restore);
    SyncStore store(db);
    QString error;
    QVERIFY2(store.applyConfig(config, &error), qPrintable(error));

    QCOMPARE(store.restoreResent(), 7); // sale-near, sale-after, the supplement, remainder, loan, settlement and the ticket
    QCOMPARE(one("SELECT synced FROM sales WHERE id = 'sale-1'").toInt(), 1);
    QCOMPARE(one("SELECT synced FROM sales WHERE id = 'sale-early'").toInt(), 1);
    QCOMPARE(one("SELECT synced FROM sales WHERE id = 'sale-near'").toInt(), 0);
    QCOMPARE(one("SELECT synced FROM sales WHERE id = 'sale-after'").toInt(), 0);
    QVERIFY(one("SELECT uploaded_at FROM sales WHERE id = 'sale-after'").isNull());
    QCOMPARE(one("SELECT synced FROM supplements").toInt(), 0);
    QCOMPARE(one("SELECT synced FROM remainders").toInt(), 0);
    QCOMPARE(one("SELECT synced FROM ticket_loans").toInt(), 0);
    QCOMPARE(one("SELECT synced FROM ticket_loan_settlements").toInt(), 0);
    QCOMPARE(one("SELECT synced FROM tickets WHERE barcode = 3000000011").toInt(), 0);
    QCOMPARE(one("SELECT synced FROM tickets WHERE barcode = 3000000012").toInt(), 1); // what came from the web app stays as it is
    QCOMPARE(one("SELECT synced FROM nozzles WHERE id = 7").toInt(), 0);               // every nozzle goes again
    QCOMPARE(Uploader::pendingTotal(db), 7);
    QCOMPARE(Settings(db).value(SettingKey::RestoreHandled), QString("2026-10-08 09:00:00"));
}

void Smart210Test::aRestoreIsActedOnOnceAndTheDownloadSaysSo()
{
    QSqlDatabase db = openDatabase();
    recordADay();
    run("UPDATE sales SET synced = 1, uploaded_at = '2026-10-07 13:00:00'");
    configureServer();
    serveEverything();
    QJsonObject config = configObject();
    QJsonObject restore;
    restore.insert("restored_at", "2026-10-08 09:00:00");
    restore.insert("since", "2026-10-07 12:00:00");
    config.insert("restore", restore);
    server_->reply("/api/pull/config", 200, QJsonDocument(config).toJson(QJsonDocument::Compact));

    SyncClient client(db);
    QSignalSpy spy(&client, SIGNAL(downloadFinished(bool,QString)));
    client.download();
    QVERIFY(spy.wait(10000));
    QVERIFY2(spy.at(0).at(0).toBool(), qPrintable(spy.at(0).at(1).toString()));
    QVERIFY2(spy.at(0).at(1).toString().contains("was restored: 1 records will be sent again"), qPrintable(spy.at(0).at(1).toString()));
    QCOMPARE(one("SELECT synced FROM sales WHERE id = 'sale-1'").toInt(), 0);

    // the same restore is not acted on again: what was sent since stays sent
    run("UPDATE sales SET synced = 1, uploaded_at = '2026-10-09 10:00:00'");
    spy.clear();
    client.download();
    QVERIFY(spy.wait(10000));
    QVERIFY(!spy.at(0).at(1).toString().contains("restored"));
    QCOMPARE(one("SELECT synced FROM sales WHERE id = 'sale-1'").toInt(), 1);

    // a later restore is
    restore.insert("restored_at", "2026-10-12 09:00:00");
    restore.insert("since", "2026-10-09 00:00:00");
    config.insert("restore", restore);
    server_->reply("/api/pull/config", 200, QJsonDocument(config).toJson(QJsonDocument::Compact));
    spy.clear();
    client.download();
    QVERIFY(spy.wait(10000));
    QVERIFY(spy.at(0).at(1).toString().contains("was restored: 1 records"));
    QCOMPARE(one("SELECT synced FROM sales WHERE id = 'sale-1'").toInt(), 0);
}

void Smart210Test::theTimeOfABoardIsSevenBytesOfTheStationsLocalTime()
{
    const QDateTime time(QDate(2026, 10, 10), QTime(9, 30, 15));
    const QByteArray data = BoardProtocol::timeData(time);

    QCOMPARE(data, QByteArray("\xEA\x07\x0A\x0A\x09\x1E\x0F", 7)); // 2026 (little endian), 10, 10, 09:30:15
    QDateTime back;
    QVERIFY(BoardProtocol::parseTime(data, &back));
    QCOMPARE(back, time);

    QVERIFY(!BoardProtocol::parseTime(data.left(6), &back));                          // too short
    QVERIFY(!BoardProtocol::parseTime(QByteArray("\xEA\x07\x0D\x0A\x09\x1E\x0F", 7), &back)); // month 13
    QVERIFY(!BoardProtocol::parseTime(QByteArray("\xEA\x07\x02\x1E\x09\x1E\x0F", 7), &back)); // 30 February
    QVERIFY(!BoardProtocol::parseTime(QByteArray("\xEA\x07\x0A\x0A\x18\x1E\x0F", 7), &back)); // hour 24
}

namespace {

int timeCommands(const FakeBoards::Board &board)
{
    int n = 0;
    for (int i = 0; i < board.commands.size(); ++i) {
        n += board.commands.at(i) == BoardProtocol::CmdSetTime ? 1 : 0;
    }

    return n;
}

}

void Smart210Test::theBoardsGetTheTimeOfTheTerminalAndKeepItRight()
{
    FakeBoards boards;
    boards.add(1);
    boards.add(2);
    boards.add(3);
    boards.board(2).supportsTime = false; // an older board
    BoardBus bus;
    quickBus(&bus, &boards);
    bus.addBoard(1);
    bus.addBoard(2);
    bus.addBoard(3);
    BoardClock clock(&bus);
    clock.setTickMs(20);
    clock.setIntervalMs(400);
    QCOMPARE(boards.simulator().clockOf(1).date().year(), 2000); // a new board starts at 2000-01-01
    QVERIFY(startBus(&bus, &boards));

    // every board that knows the command gets the terminal's time as soon as it answers
    QTRY_VERIFY_WITH_TIMEOUT(clock.isSet(1) && clock.isSet(3), 5000);
    QVERIFY(qAbs(boards.simulator().clockOf(1).secsTo(QDateTime::currentDateTime())) <= 2);
    QVERIFY(qAbs(boards.simulator().clockOf(3).secsTo(QDateTime::currentDateTime())) <= 2);

    // the older board says BAD_COMMAND once and is left alone
    QTRY_VERIFY_WITH_TIMEOUT(clock.isUnsupported(2), 5000);
    QVERIFY(!clock.isSet(2));
    QTest::qWait(900);
    QCOMPARE(timeCommands(boards.board(2)), 1);
    QCOMPARE(boards.simulator().clockOf(2).date().year(), 2000);

    // it is set again from time to time
    QTRY_VERIFY_WITH_TIMEOUT(timeCommands(boards.board(1)) >= 3, 5000);

    // a board that lost its clock in a power cut gets the time again when it comes back
    boards.board(1).clockOffsetSecs = QDateTime::currentDateTime().secsTo(QDateTime(QDate(2000, 1, 1), QTime(0, 0, 0)));
    boards.board(1).silent = true;
    QTRY_VERIFY_WITH_TIMEOUT(!bus.isOnline(1), 5000);
    boards.board(1).silent = false;
    QTRY_VERIFY_WITH_TIMEOUT(qAbs(boards.simulator().clockOf(1).secsTo(QDateTime::currentDateTime())) <= 2, 5000);

    // and at once for every board when the terminal's own clock was corrected
    clock.setIntervalMs(3600 * 1000);
    QTest::qWait(300);
    const int before = timeCommands(boards.board(3));
    clock.syncAll();
    QTRY_VERIFY_WITH_TIMEOUT(timeCommands(boards.board(3)) > before, 5000);
}

// ---- the RFID cards ----

namespace {

QStringList columnsOf(QSqlDatabase db, const QString &table)
{
    QStringList names;
    QSqlQuery query(db);
    query.exec("PRAGMA table_info(" + table + ")");
    while (query.next()) {
        names << query.value(1).toString();
    }

    return names;
}

// A registered card (PIN 1234) with a balance, for the tests that need one.
QString makeCard(QSqlDatabase db, const QString &uid = "AA01", qlonglong balanceX100 = 0, const QString &fuel = "D")
{
    const Cards::Registration registration = Cards::registerCard(db, uid, "Li Wei", fuel, "1234");
    if (registration.ok && balanceX100 > 0) {
        Cards::topUp(db, registration.cardId, balanceX100, 800, 7, QDateTime::currentDateTime());
    }

    return registration.cardId;
}

}

void Smart210Test::theCardTablesAreMadeByTheMigration()
{
    QSqlDatabase db = openDatabase();

    // PRAGMA table_info works in every SQLite (the table-valued pragma_table_info() needs 3.16)
    QCOMPARE(columnsOf(db, "cards").size(), 14);
    QVERIFY(columnsOf(db, "cards").contains("holder_phone"));
    QVERIFY(columnsOf(db, "sales").contains("card_id"));
    QVERIFY(columnsOf(db, "supplements").contains("card_id"));
    QVERIFY(columnsOf(db, "supply_sessions").contains("card_id"));
    // the supplements and remainders follow the names of the web app
    foreach (const QString &name, QStringList() << "out_amount_lt" << "out_amount_kg" << "density" << "mode" << "supply_date") {
        QVERIFY2(columnsOf(db, "supplements").contains(name), qPrintable(name));
    }
    foreach (const QString &name, QStringList() << "liters_dispensed" << "amount_kg" << "manual_reading" << "date") {
        QVERIFY2(!columnsOf(db, "supplements").contains(name), qPrintable(name));
    }
    QVERIFY(columnsOf(db, "remainders").contains("created_at") && columnsOf(db, "remainders").contains("remainder_date"));
    QVERIFY(!columnsOf(db, "remainders").contains("remainder_at") && !columnsOf(db, "remainders").contains("date"));

    run("INSERT INTO cards (id, uid, holder_name, fuel_type, pin_salt, pin_hash) VALUES ('c1', 'AA01', 'Li Wei', 'D', 's', 'h')");
    QSqlQuery duplicate(QSqlDatabase::database(connection_));
    QVERIFY(!duplicate.exec("INSERT INTO cards (id, uid, holder_name, fuel_type, pin_salt, pin_hash) VALUES ('c2', 'AA01', 'Wang', 'D', 's', 'h')")); // a card id is used once
    QCOMPARE(one("SELECT status || '/' || failed_attempts || '/' || balance_kg || '/' || deleted FROM cards").toString(), QString("active/0/0/0"));
}

void Smart210Test::aCardIsRegisteredWithASaltedPinAndWaitsForTheUpload()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);

    const Cards::Registration result = Cards::registerCard(db, "  aa 01 ", "  Li Wei ", "D", "1234");
    QVERIFY2(result.ok, qPrintable(result.error));

    const Cards::Card card = Cards::find(db, "AA01");
    QVERIFY(card.exists);
    QCOMPARE(card.id, result.cardId);
    QCOMPARE(card.uid, QString("AA01"));                  // spaces dropped, capital letters
    QCOMPARE(card.holderName, QString("Li Wei"));
    QCOMPARE(card.fuelType, QString("D"));
    QCOMPARE(card.status, QString("active"));
    QCOMPARE(card.failedAttempts, 0);
    QCOMPARE(card.balanceX100, qlonglong(0));
    QVERIFY(!card.synced);
    QVERIFY(card.isActive());
    // the PIN is kept as the salted SHA-256 the web app and the terminal check, never as typed
    QCOMPARE(card.pinHash.size(), 64);
    QCOMPARE(card.pinHash, QString::fromLatin1(QCryptographicHash::hash((card.pinSalt + "1234").toUtf8(), QCryptographicHash::Sha256).toHex()));
    QVERIFY(PosHash::matches(card.pinSalt, "1234", card.pinHash));
    QVERIFY(!PosHash::matches(card.pinSalt, "4321", card.pinHash));
    QCOMPARE(one("SELECT COUNT(*) FROM cards WHERE pin_salt LIKE '%1234%' OR pin_hash LIKE '%1234%'").toInt(), 0);

    // a second card has another salt
    const Cards::Registration other = Cards::registerCard(db, "BB02", "Wang Fang", "D", "1234");
    QVERIFY(other.ok);
    QVERIFY(Cards::findById(db, other.cardId).pinSalt != card.pinSalt);

    QCOMPARE(Uploader::pending(db).value("cards"), 2);
    QVERIFY(!Cards::find(db, "CC03").exists);
}

void Smart210Test::aCardCannotBeRegisteredWithBadInput()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);

    QVERIFY(Cards::registerCard(db, "", "Li Wei", "D", "1234").error.contains("Read the card"));
    QVERIFY(Cards::registerCard(db, QString(40, 'A'), "Li Wei", "D", "1234").error.contains("Read the card"));    // longer than a card id
    QVERIFY(Cards::registerCard(db, "AA01", "   ", "D", "1234").error.contains("name"));
    QVERIFY(Cards::registerCard(db, "AA01", QString(101, 'x'), "D", "1234").error.contains("name"));
    foreach (const QString &pin, QStringList() << "" << "123" << "123456789" << "12a4" << "12 4") {
        QVERIFY2(Cards::registerCard(db, "AA01", "Li Wei", "D", pin).error.contains("4 to 8 digits"), qPrintable(pin));
    }
    QVERIFY(Cards::registerCard(db, "AA01", "Li Wei", "X", "1234").error.contains("fuel type"));
    QCOMPARE(countRows("cards"), 0);

    QVERIFY(Cards::registerCard(db, "AA01", "Li Wei", "D", "12345678").ok);        // 8 digits are fine
    QVERIFY(Cards::registerCard(db, "aa01", "Someone else", "D", "1234").error.contains("registered already"));
    QCOMPARE(countRows("cards"), 1);
    QVERIFY(Cards::validPin("0000") && Cards::validPin("98765432"));
    QVERIFY(!Cards::validPin(QString(4, QChar(0x0663))));                          // ASCII digits only
}

void Smart210Test::aWrongPinCountsAndTheFifthLocksTheCard()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const QString id = makeCard(db);
    run("UPDATE cards SET synced = 1");

    // four wrong PINs count, the fifth locks
    for (int wrong = 1; wrong <= 4; ++wrong) {
        const Cards::PinCheck check = Cards::checkPin(db, id, "9999");
        QCOMPARE(int(check.result), int(Cards::PinWrong));
        QCOMPARE(check.triesLeft, 5 - wrong);
        QVERIFY(check.error.contains(QString("%1 tries left").arg(5 - wrong)));
        QCOMPARE(Cards::findById(db, id).failedAttempts, wrong);
        QCOMPARE(Cards::findById(db, id).status, QString("active"));
    }
    const Cards::PinCheck fifth = Cards::checkPin(db, id, "9999");
    QCOMPARE(int(fifth.result), int(Cards::PinNowLocked));
    QVERIFY(fifth.error.contains("locked"));
    const Cards::Card card = Cards::findById(db, id);
    QCOMPARE(card.status, QString("locked"));
    QCOMPARE(card.failedAttempts, 5);
    QVERIFY(!card.synced);                                   // the lock waits for the upload
    QVERIFY(!card.isActive());

    // a locked card is not checked: the right PIN does not help and nothing is counted; the web app unlocks it
    const Cards::PinCheck refused = Cards::checkPin(db, id, "1234");
    QCOMPARE(int(refused.result), int(Cards::PinRefused));
    QVERIFY(refused.error.contains("unlocks it on the web app"));
    QCOMPARE(Cards::findById(db, id).failedAttempts, 5);

    // a blocked (lost) card, an unknown one and a PIN that is not 4 to 8 digits are refused and not counted
    run("UPDATE cards SET status = 'blocked', failed_attempts = 0");
    QVERIFY(Cards::checkPin(db, id, "1234").error.contains("blocked"));
    run("UPDATE cards SET status = 'active'");
    QVERIFY(Cards::checkPin(db, id, "12").error.contains("4 to 8 digits"));
    QCOMPARE(Cards::findById(db, id).failedAttempts, 0);
    QVERIFY(Cards::checkPin(db, "no-such-card", "1234").error.contains("not known"));
}

void Smart210Test::aRightPinCountsTheWrongOnesFromZeroAgain()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const QString id = makeCard(db);
    run("UPDATE cards SET synced = 1");

    QCOMPARE(int(Cards::checkPin(db, id, "9999").result), int(Cards::PinWrong));
    QCOMPARE(int(Cards::checkPin(db, id, "9999").result), int(Cards::PinWrong));
    QCOMPARE(Cards::findById(db, id).failedAttempts, 2);
    run("UPDATE cards SET synced = 1");

    QCOMPARE(int(Cards::checkPin(db, id, "1234").result), int(Cards::PinRight));
    QCOMPARE(Cards::findById(db, id).failedAttempts, 0);
    QVERIFY(!Cards::findById(db, id).synced);                // the web app is told that the tries are zero again

    // a right PIN with nothing to reset changes nothing (and sends nothing)
    run("UPDATE cards SET synced = 1");
    QCOMPARE(int(Cards::checkPin(db, id, "1234").result), int(Cards::PinRight));
    QVERIFY(Cards::findById(db, id).synced);

    // so four wrong ones, a right one, four wrong ones do not lock the card
    for (int i = 0; i < 4; ++i) {
        Cards::checkPin(db, id, "9999");
    }
    Cards::checkPin(db, id, "1234");
    for (int i = 0; i < 4; ++i) {
        Cards::checkPin(db, id, "9999");
    }
    QCOMPARE(Cards::findById(db, id).status, QString("active"));
}

void Smart210Test::aTopUpIsACashCardSaleAndRaisesTheBalance()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const QString id = makeCard(db);
    const QDateTime now = QDateTime::fromString("2026-10-13 10:30:00", "yyyy-MM-dd HH:mm:ss");

    const Cards::TopUpResult first = Cards::topUp(db, id, 4000, 810, 7, now);        // 40.00 kg at 8.10: the price is typed, not the fuel's
    QVERIFY2(first.ok, qPrintable(first.error));
    QCOMPARE(first.totalX100, qlonglong(32400));
    QCOMPARE(first.balanceX100, qlonglong(4000));
    QCOMPARE(Cards::findById(db, id).balanceX100, qlonglong(4000));

    QSqlQuery sale(db);
    QVERIFY(sale.exec("SELECT id, created_at, customer_id, sale_type, fuel_type, price, amount_kg, payment_method, total_amount, paid_amount, "
                      "unpaid_amount, receipt, usage, sale_date, user_id, deleted_at, synced, card_id FROM sales"));
    QVERIFY(sale.next());
    QCOMPARE(sale.value("id").toString(), first.saleId);
    QCOMPARE(sale.value("created_at").toString(), QString("2026-10-13 10:30:00"));
    QVERIFY(sale.value("customer_id").isNull());                // the card has the name: no customer
    QCOMPARE(sale.value("sale_type").toString(), QString("card"));
    QCOMPARE(sale.value("fuel_type").toString(), QString("D"));   // the card's
    QCOMPARE(sale.value("price").toInt(), 810);
    QCOMPARE(sale.value("amount_kg").toInt(), 4000);
    QCOMPARE(sale.value("payment_method").toString(), QString("cash"));   // a card is paid in cash
    QCOMPARE(sale.value("total_amount").toInt(), 32400);
    QCOMPARE(sale.value("paid_amount").toInt(), 32400);          // in full
    QCOMPARE(sale.value("unpaid_amount").toInt(), 0);
    QVERIFY(sale.value("receipt").isNull());
    QCOMPARE(sale.value("usage").toString(), QString("sale"));
    QCOMPARE(sale.value("sale_date").toString(), QString("2026-10-13")); // the business day of 10:30, at once
    QCOMPARE(sale.value("user_id").toInt(), 7);
    QVERIFY(sale.value("deleted_at").isNull());
    QCOMPARE(sale.value("synced").toInt(), 0);
    QCOMPARE(sale.value("card_id").toString(), id);
    QVERIFY(!sale.next());
    QCOMPARE(countRows("sale_items"), 0);                        // no ticket is made for it
    QVERIFY(SaleDeletion::blocker(db, first.saleId, 7).contains("Only a spot sale"));   // a top-up is never deleted on the terminal

    // top-ups add up; a locked card can be topped up too
    run("UPDATE cards SET status = 'locked'");
    const Cards::TopUpResult second = Cards::topUp(db, id, 1500, 800, 7, now);
    QVERIFY2(second.ok, qPrintable(second.error));
    QCOMPARE(second.balanceX100, qlonglong(5500));
    QCOMPARE(Uploader::pending(db).value("sales"), 2);
    QCOMPARE(one("SELECT SUM(amount_kg) FROM sales WHERE card_id IS NOT NULL").toInt(), 5500);
}

void Smart210Test::aTopUpIsRefusedForABlockedCardAndForBadInput()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const QString id = makeCard(db);
    const QDateTime now = QDateTime::currentDateTime();

    QVERIFY(Cards::topUp(db, id, 1000, 800, 0, now).error.contains("Log in"));
    QVERIFY(Cards::topUp(db, id, 0, 800, 7, now).error.contains("weight"));
    QVERIFY(Cards::topUp(db, id, SpotSale::MaxKgX100 + 1, 800, 7, now).error.contains("weight"));
    QVERIFY(Cards::topUp(db, id, 1000, 0, 7, now).error.contains("price"));
    QVERIFY(Cards::topUp(db, "no-such-card", 1000, 800, 7, now).error.contains("not known"));
    run("UPDATE cards SET status = 'blocked'");
    QVERIFY(Cards::topUp(db, id, 1000, 800, 7, now).error.contains("blocked"));
    QCOMPARE(countRows("sales"), 0);
    QCOMPARE(Cards::findById(db, id).balanceX100, qlonglong(0));
}

void Smart210Test::theCardReaderCutsItsBytesIntoIds()
{
    CardFrameParser line;
    line.setFormat(CardFrameParser::Line, 0, CardFrameParser::Ascii);
    QCOMPARE(line.feed("04a1b2c3\r\n"), QStringList() << "04A1B2C3");
    // the bytes may come in pieces, with CR, LF or both, and several ids in one go
    QCOMPARE(line.feed("04A1"), QStringList());
    QCOMPARE(line.feed("B2C3\r"), QStringList() << "04A1B2C3");
    QCOMPARE(line.feed("\nAAAA\nBBBB\r\n"), QStringList() << "AAAA" << "BBBB");
    QCOMPARE(line.feed("\r\n\r\n"), QStringList());                  // empty lines are nothing
    // STX / ETX and spaces around an id are dropped
    QCOMPARE(line.feed(QByteArray("\x02 0006C7D2 \x03\r\n")), QStringList() << "0006C7D2");
    // noise that never ends is forgotten, and the reader is fine afterwards
    QCOMPARE(line.feed(QByteArray(129, 'x')), QStringList());
    QCOMPARE(line.feed("OK\r"), QStringList() << "OK");

    CardFrameParser fixed;
    fixed.setFormat(CardFrameParser::Fixed, 8, CardFrameParser::Ascii);
    QCOMPARE(fixed.feed("12345678"), QStringList() << "12345678");
    QCOMPARE(fixed.feed("ABCD"), QStringList());
    QCOMPARE(fixed.feed("EFGH1234"), QStringList() << "ABCDEFGH");     // the rest waits for its other half
    QCOMPARE(fixed.feed("5678"), QStringList() << "12345678");
    fixed.feed("AB");
    fixed.reset();                                                   // a frame that stopped short is forgotten
    QCOMPARE(fixed.feed("12345678"), QStringList() << "12345678");

    // the bytes as hex digits: a fixed frame of 4 bytes, or a line
    CardFrameParser hex;
    hex.setFormat(CardFrameParser::Fixed, 4, CardFrameParser::Hex);
    QCOMPARE(hex.feed(QByteArray::fromHex("04a1b2c3")), QStringList() << "04A1B2C3");
    hex.setFormat(CardFrameParser::Line, 0, CardFrameParser::Hex);
    QCOMPARE(hex.feed(QByteArray::fromHex("04a1b2c3") + QByteArray("\n")), QStringList() << "04A1B2C3");

    // the station's Arduino reader: "CARD: <id>" for a card, other lines (it says "Ready" when it starts) are not cards
    CardFrameParser arduino;
    arduino.setFormat(CardFrameParser::Line, 0, CardFrameParser::Ascii);
    arduino.setPrefix("CARD:");
    QCOMPARE(arduino.feed("Ready\r\n"), QStringList());
    QCOMPARE(arduino.feed("CARD: 04 A1 B2 C3\r\n"), QStringList() << "04A1B2C3");
    QCOMPARE(arduino.feed("card:04a1\n"), QStringList() << "04A1");
    QCOMPARE(arduino.feed("CAR"), QStringList());                           // in pieces
    QCOMPARE(arduino.feed("D: ab12\r\n"), QStringList() << "AB12");
    QCOMPARE(arduino.feed("CARD:\r\nXCARD: 1\r\nCARD 5\r\n"), QStringList());   // no id, another prefix, no colon
    QCOMPARE(arduino.feed("CARD: 04A1B2C3\r\nCARD: 04A1B2C3\r\n"), QStringList() << "04A1B2C3" << "04A1B2C3");   // the reader repeats while the card lies on it
    arduino.setPrefix("");                                                  // no prefix: the whole line is the id again
    QCOMPARE(arduino.feed("CARD: 1\r\n"), QStringList() << "CARD:1");

    QCOMPARE(int(CardFrameParser::formatOf("fixed")), int(CardFrameParser::Fixed));
    QCOMPARE(int(CardFrameParser::formatOf("line")), int(CardFrameParser::Line));
    QCOMPARE(int(CardFrameParser::formatOf("anything")), int(CardFrameParser::Line));
    QCOMPARE(int(CardFrameParser::encodingOf("hex")), int(CardFrameParser::Hex));
    QCOMPARE(int(CardFrameParser::encodingOf("ascii")), int(CardFrameParser::Ascii));
}

void Smart210Test::theCardReaderEmitsTheIdsItReads()
{
    QCOMPARE(CardReader::defaultPort(), QString("/dev/ttyUSB0"));

    CardReader reader;
    QSignalSpy read(&reader, SIGNAL(cardRead(QString)));
    reader.feed("aa01\r\n");                                          // a reader that was not started reads lines as text
    QCOMPARE(read.size(), 1);
    QCOMPARE(read.at(0).at(0).toString(), QString("AA01"));
    reader.feed(QByteArray());
    QCOMPARE(read.size(), 1);

    CardReader::Config config;
    config.port = "/dev/this-port-is-not-there";
    config.baud = 0;
    config.format = "fixed";
    config.length = 6;
    config.encoding = "ascii";
    reader.start(config);
    QVERIFY(reader.isRunning());
    QVERIFY(!reader.isOpen());                                        // the reader is unplugged: it tries again later
    QCOMPARE(reader.config().port, QString("/dev/this-port-is-not-there"));
    QCOMPARE(reader.config().baud, 9600);                             // no speed given: the usual one

    read.clear();
    reader.feed("ABC");
    QCOMPARE(read.size(), 0);
    reader.feed("DEF");
    QCOMPARE(read.size(), 1);
    QCOMPARE(read.at(0).at(0).toString(), QString("ABCDEF"));

    // the half of a frame is forgotten after a pause: the next bytes start a new frame
    reader.feed("AB");
    QTest::qWait(500);
    reader.feed("CDEFGH");
    QCOMPARE(read.size(), 2);
    QCOMPARE(read.at(1).at(0).toString(), QString("CDEFGH"));

    reader.stop();
    QVERIFY(!reader.isRunning());

    // the Arduino reader of the station
    QCOMPARE(CardReader::defaultPrefix(), QString("CARD:"));
    CardReader::Config arduino;
    arduino.port = "/dev/this-port-is-not-there";
    arduino.format = "line";
    arduino.encoding = "ascii";
    arduino.prefix = CardReader::defaultPrefix();
    reader.start(arduino);
    read.clear();
    reader.feed("Ready\r\nCARD: 04 A1 B2 C3\r\n");
    QCOMPARE(read.size(), 1);
    QCOMPARE(read.at(0).at(0).toString(), QString("04A1B2C3"));
    reader.stop();
}

void Smart210Test::aCardFillTypedInIsRecordedWithTheCard()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const QString card = makeCard(db, "AA01", 4000);
    Session session(db);
    QVERIFY(session.login(7, "secret1"));
    MachineConfig config(db);
    FakeBoards boards; // no board answers: the converter is not even connected
    BoardBus bus;
    quickBus(&bus, &boards);
    SupplySelection selection(&bus, &config);
    SupplySession supply(db, &bus, &config, &selection, &session);
    QSignalSpy remainders(&supply, SIGNAL(remainderMade(QString)));
    QString why;

    // the fill of nozzle 2 was started with the card, then the board died: the cashier types what the dispenser shows
    QVERIFY(SupplyQueue::begin(db, 2, 3500, QDateTime::currentDateTime(), card));
    QVERIFY(supply.isLocked(2));
    QVERIFY(!supply.recordManual(2, 3600, 4000, QList<qlonglong>(), QDateTime::currentDateTime(), &why));   // more than was asked for
    QVERIFY(why.contains("More than was asked for (35.00 kg)"));
    QVERIFY(supply.isLocked(2));
    QCOMPARE(countRows("supplements"), 0);

    QVERIFY2(supply.recordManual(2, 3000, 3614, QList<qlonglong>(), QDateTime::currentDateTime(), &why), qPrintable(why));
    QCOMPARE(countRows("supplements"), 1);
    QCOMPARE(one("SELECT card_id FROM supplements").toString(), card);
    QCOMPARE(one("SELECT mode FROM supplements").toString(), QString("manual"));
    QCOMPARE(one("SELECT out_amount_kg FROM supplements").toInt(), 3000);
    QCOMPARE(remainders.size(), 0);
    QCOMPARE(countRows("remainders"), 0);
    QVERIFY(!supply.isLocked(2));
    QCOMPARE(Cards::findById(db, card).balanceX100, qlonglong(1000));
}

void Smart210Test::aSupplyRecordsTheModeAndTheDensity()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const QDateTime now = QDateTime(QDate(2026, 10, 9), QTime(10, 0, 0));

    // an auto fill: mode auto, the density of the nozzle
    QVERIFY(SupplyQueue::add(db, 1, code(3000000041), "D", now).ok);
    QVERIFY(SupplyQueue::begin(db, 1, 1500, now));
    SupplyRecord::Reading reading;
    reading.resultId = 11;
    reading.dispensedX100 = 1500;
    reading.litersX100 = 1807;
    reading.presetX100 = 1500;
    reading.reason = BoardProtocol::ReasonTankFullOrNoFlow;
    const SupplyRecord::Outcome autoFill = SupplyRecord::record(db, 1, reading, now);
    QVERIFY2(autoFill.ok, qPrintable(autoFill.error));
    QCOMPARE(one("SELECT mode FROM supplements WHERE id = '" + autoFill.supplementId + "'").toString(), QString("auto"));
    QCOMPARE(one("SELECT density FROM supplements WHERE id = '" + autoFill.supplementId + "'").toInt(), 8300);

    // a nozzle in manual mode: mode manual
    reading.resultId = 12;
    reading.dispensedX100 = 800;
    reading.litersX100 = 964;
    reading.presetX100 = 800;
    const SupplyRecord::Outcome manualNozzle = SupplyRecord::record(db, 3, reading, now);
    QVERIFY2(manualNozzle.ok, qPrintable(manualNozzle.error));
    QCOMPARE(one("SELECT mode FROM supplements WHERE id = '" + manualNozzle.supplementId + "'").toString(), QString("manual"));
    QCOMPARE(one("SELECT density FROM supplements WHERE id = '" + manualNozzle.supplementId + "'").toInt(), 8300);

    // a reading typed by hand on an auto nozzle: manual too
    reading.resultId = 0;
    reading.byHand = true;
    reading.dispensedX100 = 1000;
    reading.litersX100 = 1205;
    const SupplyRecord::Outcome byHand = SupplyRecord::record(db, 2, reading, now);
    QVERIFY2(byHand.ok, qPrintable(byHand.error));
    QCOMPARE(one("SELECT mode FROM supplements WHERE id = '" + byHand.supplementId + "'").toString(), QString("manual"));
    QCOMPARE(one("SELECT COUNT(*) FROM supplements WHERE card_id IS NOT NULL").toInt(), 0);
}

// tickets.print_count / last_printed_at: one more for every ticket the printer took (the first print and each Print again), none for a print that failed
void Smart210Test::everyPrintOfATicketIsCounted()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    FakePrinter printer;
    QVERIFY(printer.listen());
    Settings(db).setValue(SettingKey::PrinterHost, "127.0.0.1");
    Settings(db).setInt(SettingKey::PrinterPort, printer.port());
    PrintService service(db, PrintOptions());
    service.setStatusWaitMs(200);
    QSignalSpy spy(&service, SIGNAL(finished(bool,QString)));

    // a spot sale's ticket: nothing before it is printed, then one for each print
    const SpotSale::Result sale = SpotSale::create(db, spotInput(1050), 7, QDateTime::currentDateTime());
    const qlonglong key = Barcode::toKey(one("SELECT printf('%016d', barcode) FROM tickets WHERE sale_id = '" + sale.saleId + "'").toString());
    const QString where = " FROM tickets WHERE barcode = " + QString::number(key);
    QCOMPARE(one("SELECT print_count" + where).toInt(), 0);
    QVERIFY(one("SELECT last_printed_at" + where).isNull());
    run("UPDATE tickets SET synced = 1 WHERE barcode = " + QString::number(key));      // (it was uploaded already)

    service.printSaleTicket(sale.saleId);
    QTRY_COMPARE_WITH_TIMEOUT(spy.size(), 1, 5000);
    QVERIFY(spy.at(0).at(0).toBool());
    QCOMPARE(one("SELECT print_count" + where).toInt(), 1);
    QVERIFY(!one("SELECT last_printed_at" + where).toString().isEmpty());
    QCOMPARE(one("SELECT synced" + where).toInt(), 0);                               // it goes up again with its count
    service.printSaleTicket(sale.saleId);
    QTRY_COMPARE_WITH_TIMEOUT(spy.size(), 2, 5000);
    QCOMPARE(one("SELECT print_count" + where).toInt(), 2);

    // a print that fails (nothing listens) counts nothing
    const quint16 port = printer.port();
    printer.close();
    Settings(db).setInt(SettingKey::PrinterPort, port);
    service.printSaleTicket(sale.saleId);
    QTRY_COMPARE_WITH_TIMEOUT(spy.size(), 3, 10000);
    QVERIFY(!spy.at(2).at(0).toBool());
    QCOMPARE(one("SELECT print_count" + where).toInt(), 2);

    // a ticket made for a card, and a remainder ticket, count their prints the same way
    FakePrinter second;
    QVERIFY(second.listen());
    Settings(db).setInt(SettingKey::PrinterPort, second.port());
    const QString card = makeCard(db, "AA01", 4000);
    const Cards::TicketResult made = Cards::issueTicket(db, card, 1500, 7, QDateTime::currentDateTime());
    QVERIFY(made.ok);
    service.printCardTicket(made.barcode);
    QTRY_COMPARE_WITH_TIMEOUT(spy.size(), 4, 5000);
    QCOMPARE(one("SELECT print_count FROM tickets WHERE barcode = " + QString::number(Barcode::toKey(made.barcode))).toInt(), 1);
    service.printRemainderTicket(code(3000000041));
    QTRY_COMPARE_WITH_TIMEOUT(spy.size(), 5, 5000);
    QCOMPARE(one("SELECT print_count FROM tickets WHERE barcode = 3000000041").toInt(), 1);
}

// Cards::issueTicket: the card pays at once and a ticket for the kg is made; Cards::cancelTicket takes an unused one back
void Smart210Test::aFillIsATicketMadeForTheCardAndItIsCancelledWhileItWaitsForTheUpload()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const QString card = makeCard(db, "AA01", 4000);
    const QDateTime now = QDateTime::currentDateTime();

    // what it refuses
    QVERIFY(Cards::issueTicket(db, card, 0, 7, now).error.contains("Enter the weight"));
    QVERIFY(Cards::issueTicket(db, card, 4001, 7, now).error.contains("holds only 40.00 kg"));
    QVERIFY(Cards::issueTicket(db, card, 1000, 0, now).error.contains("Log in"));
    QVERIFY(Cards::issueTicket(db, "no-such-card", 1000, 7, now).error.contains("not known"));
    run("UPDATE cards SET status = 'locked' WHERE id = '" + card + "'");
    QVERIFY(Cards::issueTicket(db, card, 1000, 7, now).error.contains("locked"));
    run("UPDATE cards SET status = 'blocked' WHERE id = '" + card + "'");
    QVERIFY(Cards::issueTicket(db, card, 1000, 7, now).error.contains("blocked"));
    run("UPDATE cards SET status = 'active' WHERE id = '" + card + "'");
    QCOMPARE(one("SELECT COUNT(*) FROM tickets WHERE card_id IS NOT NULL").toInt(), 0);
    QCOMPARE(Cards::findById(db, card).balanceX100, qlonglong(4000));
    QVERIFY(Cards::lastTicket(db, card).isEmpty());

    // the ticket: the card pays at once; it is sold, has no sale and waits for the upload
    const Cards::TicketResult made = Cards::issueTicket(db, card, 1500, 7, now);
    QVERIFY2(made.ok, qPrintable(made.error));
    QCOMPARE(made.kgX100, qlonglong(1500));
    QCOMPARE(made.balanceX100, qlonglong(2500));
    QCOMPARE(made.barcode.size(), 16);
    QCOMPARE(made.barcode.mid(5, 2), QString("25"));                        // a card code: the first digit is the fuel type (diesel 2), the second the kind (card 5)
    QCOMPARE(one("SELECT COUNT(*) FROM tickets WHERE card_id = '" + card + "' AND amount_kg = 1500 AND status = 1 AND sale_id IS NULL AND fuel_type = 'D' AND synced = 0").toInt(), 1);
    QCOMPARE(Cards::findById(db, card).balanceX100, qlonglong(2500));
    QCOMPARE(Cards::lastTicket(db, card), made.barcode);
    const Cards::TicketResult second = Cards::issueTicket(db, card, 1000, 7, now.addSecs(1));
    QVERIFY(second.ok);
    QVERIFY(second.barcode != made.barcode);
    QCOMPARE(Cards::lastTicket(db, card), second.barcode);
    QCOMPARE(Cards::findById(db, card).balanceX100, qlonglong(1500));

    // it is uploaded with the card, but the kg are already taken from the balance
    QCOMPARE(Uploader::pending(db).value("tickets"), 2);

    // what cannot be cancelled: a ticket in the queue of a nozzle, a ticket that is used, one that is uploaded, one that is not a card's
    QString why;
    QVERIFY2(SupplyQueue::add(db, 1, second.barcode, "D", now).ok, "the supplier scans the ticket");
    QVERIFY(!Cards::cancelTicket(db, second.barcode, &why));
    QVERIFY2(why.contains("queue of a nozzle"), qPrintable(why));
    SupplyQueue::clear(db, 1);
    run("UPDATE tickets SET status = 2 WHERE barcode = " + QString::number(Barcode::toKey(second.barcode)));
    QVERIFY(!Cards::cancelTicket(db, second.barcode, &why));
    QVERIFY2(why.contains("used already"), qPrintable(why));
    run("UPDATE tickets SET status = 1, synced = 1 WHERE barcode = " + QString::number(Barcode::toKey(second.barcode)));
    QVERIFY(!Cards::cancelTicket(db, second.barcode, &why));
    QVERIFY2(why.contains("uploaded already"), qPrintable(why));
    QVERIFY(!Cards::cancelTicket(db, code(3000000041), &why));
    QVERIFY2(why.contains("not a ticket made for a card"), qPrintable(why));
    QVERIFY(!Cards::cancelTicket(db, "abc", &why));
    QCOMPARE(Cards::findById(db, card).balanceX100, qlonglong(1500));
    QCOMPARE(Cards::lastTicket(db, card), second.barcode);                  // (the uploaded one is still sold)

    // the one that waits for the upload is cancelled: the ticket goes and the kg come back
    QVERIFY2(Cards::cancelTicket(db, made.barcode, &why), qPrintable(why));
    QCOMPARE(one("SELECT COUNT(*) FROM tickets WHERE card_id = '" + card + "'").toInt(), 1);
    QCOMPARE(Cards::findById(db, card).balanceX100, qlonglong(3000));
    QVERIFY(!Cards::cancelTicket(db, made.barcode, &why));                  // gone
}

// the upload sends the ticket with its card; the balance of a downloaded card keeps the tickets the web app has not counted yet
void Smart210Test::aTicketMadeForACardIsUploadedWithItsCardAndTheDownloadedBalanceKeepsIt()
{
    configureServer();
    setUpSelling();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    SyncStore store(db);
    const QString id = "22222222-2222-2222-2222-222222222222";
    const QString row = "[{'id':'" + id + "','uid':'AA01','holder_name':'Li Wei','holder_phone':null,'fuel_type':'D','pin_salt':'ab12','pin_hash':'hh',"
                        "'failed_attempts':0,'status':'active','balance_kg':'40.00','deleted':false}]";
    QVERIFY(store.applyPage("cards", rowsOf(row)).ok);
    QCOMPARE(Cards::findById(db, id).balanceX100, qlonglong(4000));

    const Cards::TicketResult made = Cards::issueTicket(db, id, 1500, 7, QDateTime::currentDateTime());
    QVERIFY2(made.ok, qPrintable(made.error));
    run("UPDATE tickets SET print_count = 2, last_printed_at = '2026-10-26 10:05:00' WHERE barcode = " + QString::number(Barcode::toKey(made.barcode)));
    QCOMPARE(Uploader::pending(db).value("tickets"), 1);

    // the web app does not count the ticket yet: the balance it sends keeps the 15.00 kg off
    QVERIFY(store.applyPage("cards", rowsOf(row)).ok);
    QCOMPARE(Cards::findById(db, id).balanceX100, qlonglong(2500));

    server_->reply("/api/push/tickets", 200, q("{'status':'ok','saved':['") + made.barcode.toUtf8() + q("'],'rejected':[]}"));
    Uploader uploader(db);
    QString message;
    QVERIFY(uploadOk(uploader, &message));
    QCOMPARE(message, QString("Uploaded 1 records."));
    QCOMPARE(server_->log(), QStringList() << "POST /api/push/tickets");
    const QJsonObject ticket = QJsonDocument::fromJson(server_->requests().at(0).body).object().value("rows").toArray().at(0).toObject();
    QCOMPARE(ticket.value("barcode").toString(), made.barcode);
    QCOMPARE(ticket.value("card_id").toString(), id);
    QVERIFY(ticket.value("sale_id").isNull());
    QCOMPARE(ticket.value("status").toInt(), 1);
    QCOMPARE(ticket.value("amount_kg").toString(), QString("15.00"));
    QCOMPARE(ticket.value("fuel_type").toString(), QString("D"));
    QCOMPARE(ticket.value("print_count").toInt(), 2);                        // how many times it was printed, and when last
    QCOMPARE(ticket.value("last_printed_at").toString(), QString("2026-10-26 10:05:00"));
    QCOMPARE(Uploader::pendingTotal(db), 0);

    // the web app has it now: its figure includes the ticket
    QVERIFY(store.applyPage("cards", rowsOf(QString(row).replace("'40.00'", "'25.00'"))).ok);
    QCOMPARE(Cards::findById(db, id).balanceX100, qlonglong(2500));

    // the ticket comes back in the download with its card (supplied now)
    const SyncStore::PageResult page = store.applyPage("tickets", rowsOf("[{'barcode':'" + made.barcode + "','fuel_type':'D','spec':null,'amount_kg':'15.00','status':2,'sale_id':null,"
                                                                    "'sale_date':'2026-10-26 10:00:00','card_id':'" + id + "','print_count':3,'last_printed_at':'2026-10-26 10:30:00'}]"));
    QVERIFY2(page.ok, qPrintable(page.error));
    const QString where = " WHERE barcode = " + QString::number(Barcode::toKey(made.barcode));
    QCOMPARE(one("SELECT card_id FROM tickets" + where).toString(), id);
    QCOMPARE(one("SELECT status FROM tickets" + where).toInt(), 2);
    QCOMPARE(one("SELECT print_count FROM tickets" + where).toInt(), 3);          // the larger count wins: the web app counted 3, this terminal 2
    QCOMPARE(one("SELECT last_printed_at FROM tickets" + where).toString(), QString("2026-10-26 10:30:00"));
}

// the card paid for its ticket when it was made, so what a fill leaves of it goes back to the card; a supply of other tickets, or of tickets of several kinds, makes
// a remainder ticket as before (the web app does the same: CardLedger::refund)
void Smart210Test::theUnusedPartOfACardsTicketGoesBackToTheCard()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const QDateTime now = QDateTime::currentDateTime();
    const QString card = makeCard(db, "AA01", 4000);
    const Cards::TicketResult made = Cards::issueTicket(db, card, 1500, 7, now);
    QVERIFY(made.ok);
    QCOMPARE(Cards::findById(db, card).balanceX100, qlonglong(2500));

    QVERIFY(SupplyQueue::add(db, 1, made.barcode, "D", now).ok);
    QVERIFY(SupplyQueue::begin(db, 1, 1500, now));
    SupplyRecord::Reading reading;
    reading.resultId = 1;
    reading.dispensedX100 = 1000;
    reading.litersX100 = 1200;
    reading.reason = BoardProtocol::ReasonTankFullOrNoFlow;
    const SupplyRecord::Outcome outcome = SupplyRecord::record(db, 1, reading, now);
    QVERIFY2(outcome.ok, qPrintable(outcome.error));
    QCOMPARE(outcome.refundCardId, card);
    QCOMPARE(outcome.refundKgX100, qlonglong(500));
    QVERIFY(outcome.remainderBarcode.isEmpty());
    QCOMPARE(countRows("remainders"), 0);                                   // no paper ticket
    QCOMPARE(Cards::findById(db, card).balanceX100, qlonglong(3000));       // 25.00 + the unused 5.00 kg

    // the history shows it until the web app has the entry
    int refunds = 0;
    foreach (const CardLedger::Entry &entry, CardLedger::entries(db, card, 10)) {
        if (entry.type == "refund") {
            ++refunds;
            QCOMPARE(entry.kgX100, qlonglong(500));
            QVERIFY(entry.provisional);
        }
    }
    QCOMPARE(refunds, 1);

    // a supply of a card ticket and an ordinary ticket: not all of them are the card's, so the rest is a remainder ticket
    const Cards::TicketResult small = Cards::issueTicket(db, card, 500, 7, now.addSecs(1));
    QVERIFY(small.ok);
    QVERIFY(SupplyQueue::add(db, 1, small.barcode, "D", now).ok);
    QVERIFY(SupplyQueue::add(db, 1, code(3000000043), "D", now).ok);        // 5.00 kg
    QVERIFY(SupplyQueue::begin(db, 1, 1000, now));
    reading.resultId = 2;
    reading.dispensedX100 = 800;
    const SupplyRecord::Outcome mixed = SupplyRecord::record(db, 1, reading, now);
    QVERIFY2(mixed.ok, qPrintable(mixed.error));
    QVERIFY(mixed.refundCardId.isEmpty());
    QCOMPARE(mixed.refundKgX100, qlonglong(0));
    QCOMPARE(mixed.remainderKgX100, qlonglong(200));
    QCOMPARE(countRows("remainders"), 1);
    QCOMPARE(Cards::findById(db, card).balanceX100, qlonglong(2500));       // 30.00 less the 5.00 kg of the new ticket, nothing back
}

// the history of a card lists the tickets made for it as fills until the web app has an entry for them
void Smart210Test::theHistoryOfACardShowsTheFillsOfItsTickets()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const QString card = makeCard(db, "AA01", 4000);
    QVERIFY(Cards::issueTicket(db, card, 1500, 7, QDateTime::currentDateTime()).ok);

    const QList<CardLedger::Entry> entries = CardLedger::entries(db, card, 10);
    int fills = 0;
    foreach (const CardLedger::Entry &entry, entries) {
        if (entry.type == "fill") {
            ++fills;
            QCOMPARE(entry.kgX100, qlonglong(-1500));
            QCOMPARE(entry.balanceX100, qlonglong(2500));
            QVERIFY(entry.provisional);
        }
    }
    QCOMPARE(fills, 1);
    QCOMPARE(entries.size(), 2);                                            // the top-up and the fill

    // the web app has an entry for the ticket (its source is the 16 digits): the fill is that entry now, not made up here again
    run("INSERT INTO card_transactions (id, card_id, type, kg, balance_kg, source_id, occurred_at, seq) VALUES ('e1', '" + card + "', 'fill', -1500, 2500, '" + Cards::lastTicket(db, card) + "', '2026-10-26 10:00:00', 1)");
    const QList<CardLedger::Entry> confirmed = CardLedger::entries(db, card, 10);
    int provisionalFills = 0;
    foreach (const CardLedger::Entry &entry, confirmed) {
        if (entry.type == "fill" && entry.provisional) {
            ++provisionalFills;
        }
    }
    QCOMPARE(provisionalFills, 0);
}
void Smart210Test::theUploadSendsCardsCardSalesAndCardSupplements()
{
    configureServer();
    setUpSelling();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (1, 'T1', 'D', 5000000, 100000)");
    run("INSERT INTO nozzles (id, number, tank_id, fuel_type, board_address, synced, deleted) VALUES (2, 1, 1, 'D', 1, 1, 0)");
    const QString card = makeCard(db, "aa 01", 4000);                    // a card and its top-up
    run("UPDATE cards SET holder_phone = '138 0000 1111' WHERE id = '" + card + "'");
    run("INSERT INTO supplements (id, fuel_type, nozzle_id, out_amount_lt, out_amount_kg, preset_kg, density, mode, supplied_at, card_id) "
        "VALUES ('sup-card', 'D', 2, 3614, 3000, 3500, 8300, 'auto', '2026-10-13 11:00:00', '" + card + "')");
    QCOMPARE(Uploader::pending(db).value("cards"), 1);
    QCOMPARE(Uploader::pending(db).value("sales"), 1);
    QCOMPARE(Uploader::pending(db).value("supplements"), 1);
    QCOMPARE(Uploader::pendingTotal(db), 3);

    server_->reply("/api/push/cards", 200, q("{'status':'ok','saved':['") + card.toUtf8() + q("'],'rejected':[]}"));
    server_->reply("/api/push/sales", 200, q("{'status':'ok','saved':['") + one("SELECT id FROM sales").toString().toUtf8() + q("'],'rejected':[]}"));
    server_->reply("/api/push/supplements", 200, q("{'status':'ok','saved':['sup-card'],'rejected':[]}"));
    Uploader uploader(db);
    QString message;
    QVERIFY(uploadOk(uploader, &message));

    QCOMPARE(message, QString("Uploaded 3 records."));
    QStringList expected;
    expected << "POST /api/push/cards" << "POST /api/push/sales" << "POST /api/push/supplements";   // the card goes before what names it
    QCOMPARE(server_->log(), expected);
    QCOMPARE(Uploader::pendingTotal(db), 0);
    QVERIFY(Cards::findById(db, card).synced);

    const QJsonObject cardRow = QJsonDocument::fromJson(server_->requests().at(0).body).object().value("rows").toArray().at(0).toObject();
    QCOMPARE(cardRow.value("id").toString(), card);
    QCOMPARE(cardRow.value("uid").toString(), QString("AA01"));
    QCOMPARE(cardRow.value("holder_name").toString(), QString("Li Wei"));
    QCOMPARE(cardRow.value("holder_phone").toString(), QString("138 0000 1111"));     // the phone goes up with the card
    QCOMPARE(cardRow.value("fuel_type").toString(), QString("D"));
    QCOMPARE(cardRow.value("failed_attempts").toInt(), 0);
    QCOMPARE(cardRow.value("status").toString(), QString("active"));
    QCOMPARE(cardRow.value("pin_hash").toString().size(), 64);
    QCOMPARE(cardRow.value("pin_salt").toString(), Cards::findById(db, card).pinSalt);
    QVERIFY(!cardRow.contains("pin"));
    QVERIFY(!cardRow.contains("balance_kg"));                             // the web app works the balance out

    const QJsonObject sale = QJsonDocument::fromJson(server_->requests().at(1).body).object().value("rows").toArray().at(0).toObject();
    QCOMPARE(sale.value("card_id").toString(), card);
    QCOMPARE(sale.value("sale_type").toString(), QString("card"));
    QCOMPARE(sale.value("payment_method").toString(), QString("cash"));
    QVERIFY(sale.value("customer_id").isNull());
    QCOMPARE(sale.value("amount_kg").toString(), QString("40.00"));
    QCOMPARE(sale.value("price").toString(), QString("8.00"));
    QCOMPARE(sale.value("total_amount").toString(), QString("320.00"));
    QCOMPARE(sale.value("paid_amount").toString(), QString("320.00"));
    QCOMPARE(sale.value("items").toArray().size(), 0);

    const QJsonObject supplement = QJsonDocument::fromJson(server_->requests().at(2).body).object().value("rows").toArray().at(0).toObject();
    QCOMPARE(supplement.value("card_id").toString(), card);
    QCOMPARE(supplement.value("out_amount_kg").toString(), QString("30.00"));
    QCOMPARE(supplement.value("out_amount_lt").toString(), QString("36.14"));
    QCOMPARE(supplement.value("mode").toString(), QString("auto"));
    QCOMPARE(supplement.value("density").toString(), QString("0.8300"));
    QCOMPARE(supplement.value("nozzle_number").toInt(), 1);
}

void Smart210Test::aDownloadStoresCardsWithTheBalanceAndKeepsUnuploadedOnes()
{
    setUpSelling();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    run("INSERT INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (1, 'T1', 'D', 5000000, 100000)");
    run("INSERT INTO nozzles (id, number, tank_id, fuel_type, board_address, synced, deleted) VALUES (2, 1, 1, 'D', 1, 1, 0)");
    SyncStore store(db);
    const QString id = "22222222-2222-2222-2222-222222222222";
    const QString row = "[{'id':'" + id + "','uid':'AA01','holder_name':'Li Wei','holder_phone':'138 0000 1111','fuel_type':'D','pin_salt':'ab12','pin_hash':'hh',"
                        "'failed_attempts':0,'status':'active','balance_kg':'40.00','deleted':false}]";

    SyncStore::PageResult page = store.applyPage("cards", rowsOf(row));
    QVERIFY2(page.ok, qPrintable(page.error));
    QCOMPARE(page.keys.size(), 1);
    Cards::Card card = Cards::findById(db, id);
    QVERIFY(card.exists);
    QCOMPARE(card.uid, QString("AA01"));
    QCOMPARE(card.balanceX100, qlonglong(4000));
    QCOMPARE(card.status, QString("active"));
    QVERIFY(card.synced);
    QCOMPARE(card.pinSalt, QString("ab12"));
    QCOMPARE(card.holderPhone, QString("138 0000 1111"));                // the phone comes down with the card

    // a top-up and a supply made here that the web app does not know yet: the balance keeps them
    QVERIFY(Cards::topUp(db, id, 1000, 800, 7, QDateTime::currentDateTime()).ok);                   // +10.00
    run("INSERT INTO supplements (id, fuel_type, nozzle_id, out_amount_lt, out_amount_kg, preset_kg, mode, supplied_at, card_id, synced) "
        "VALUES ('sup-card', 'D', 2, 361, 300, 300, 'auto', '2026-10-13 11:00:00', '" + id + "', 0)");  // -3.00 (written by hand: the balance is not moved)
    page = store.applyPage("cards", rowsOf(row));
    QCOMPARE(page.keys.size(), 1);
    QCOMPARE(Cards::findById(db, id).balanceX100, qlonglong(4000 + 1000 - 300));

    // the web app has them now (its figure includes both): the balance is its figure, and its lock arrives
    run("UPDATE sales SET synced = 1");
    run("UPDATE supplements SET synced = 1");
    page = store.applyPage("cards", rowsOf(QString(row).replace("'40.00'", "'47.00'").replace("'active'", "'locked'").replace("'failed_attempts':0", "'failed_attempts':5")));
    card = Cards::findById(db, id);
    QCOMPARE(card.balanceX100, qlonglong(4700));
    QCOMPARE(card.status, QString("locked"));
    QCOMPARE(card.failedAttempts, 5);

    // a card with wrong PINs counted here that the web app has not heard of is left alone and not acknowledged
    run("UPDATE cards SET status = 'active', failed_attempts = 2, synced = 0");
    page = store.applyPage("cards", rowsOf(QString(row).replace("'40.00'", "'99.00'")));
    QCOMPARE(page.skipped, 1);
    QCOMPARE(page.keys.size(), 0);
    card = Cards::findById(db, id);
    QCOMPARE(card.failedAttempts, 2);
    QCOMPARE(card.balanceX100, qlonglong(4700));
    QVERIFY(!card.synced);

    // a card the web app deleted
    run("UPDATE cards SET synced = 1");
    store.applyPage("cards", rowsOf(QString(row).replace("'deleted':false", "'deleted':true")));
    QVERIFY(Cards::findById(db, id).deleted);
}

// the registered card of the next tests: Li Wei with 50 kg of Diesel
static const char *CardId = "A2425234";

// The Cards screen of a registered card shows the Fill page; the Top Up button is above History and shows the Top Up page (and then says Fill)
void Smart210Test::theCardScreenStartsOnTheFillPageWithTheTopUpButtonAboveHistory()
{
    setUpLoggedInTerminalWithAPin();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const Cards::Registration li = Cards::registerCard(db, "04A1B2", "Li Wei", "D", "1234", "");
    QVERIFY(li.ok);
    QVERIFY(Cards::topUp(db, li.cardId, 5000, 750, 7, QDateTime::currentDateTime().addSecs(-60)).ok);
    QVERIFY(Cards::registerCard(db, "04A1B3", "Wang Fang", "D", "4321", "").ok);

    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", db);
    window.showPage("cards");
    CardPage *cards = window.findChild<CardPage *>();
    QStackedWidget *panels = window.findChild<QStackedWidget *>("cardPanels");
    QStackedWidget *pages = window.findChild<QStackedWidget *>("cardActionPages");
    QPushButton *switchButton = window.findChild<QPushButton *>("cardTopUpButton");
    QPushButton *history = window.findChild<QPushButton *>("cardHistoryButton");
    QVERIFY(cards != 0 && panels != 0 && pages != 0 && switchButton != 0 && history != 0);

    // nothing to do with no card, or with a card that is not registered
    QVERIFY(!switchButton->isEnabled() && !history->isEnabled());
    cards->showCard("99 FF 00");
    QCOMPARE(panels->currentIndex(), 1);
    QVERIFY(!switchButton->isEnabled() && !history->isEnabled());
    cards->showCard("04 a1 b2");
    QCOMPARE(panels->currentIndex(), 2);
    QCOMPARE(window.findChild<QLabel *>("cardHolder")->text(), QString("Li Wei"));
    QCOMPARE(window.findChild<QLabel *>("cardBalance")->text(), QString("50.00"));
    QVERIFY(switchButton->isEnabled() && history->isEnabled());

    // the Top Up button is right above History, in the box of the card
    QLayout *column = window.findChild<QLayout *>("cardHistoryColumn");
    QVERIFY(column != 0);
    QCOMPARE(column->indexOf(history), column->indexOf(switchButton) + 1);
    QCOMPARE(switchButton->text(), QString("Top Up"));

    // the Fill page shows first (no nozzle to choose, no Fill Auto / Fill Manual), the cursor in its weight
    QCOMPARE(pages->currentWidget()->objectName(), QString("cardActionFill"));
    QVERIFY(window.findChild<QComboBox *>("fillNozzleCombo") == 0);
    QVERIFY(window.findChild<QPushButton *>("cardFillAutoButton") == 0 && window.findChild<QPushButton *>("cardFillManualButton") == 0);
    QCOMPARE(window.focusWidget(), window.findChild<QWidget *>("fillKgField"));
    QVERIFY(!window.findChild<QWidget *>("ticketBox")->isVisibleTo(cards));            // no ticket yet

    // the Fill form and the last ticket are side by side, in one row of a grid so that their borders line up (the screen is not high enough for one under the other)
    QGridLayout *fillRow = qobject_cast<QGridLayout *>(window.findChild<QWidget *>("cardActionFill")->layout());
    QVERIFY(fillRow != 0);
    QCOMPARE(fillRow->itemAtPosition(0, 0)->widget(), window.findChild<QWidget *>("fillBox"));
    QCOMPARE(fillRow->itemAtPosition(0, 1)->widget(), window.findChild<QWidget *>("ticketBox"));

    // the button shows the Top Up page and then says Fill, which comes back
    switchButton->click();
    QCOMPARE(pages->currentWidget()->objectName(), QString("cardActionTopUp"));
    QCOMPARE(switchButton->text(), QString("Fill"));
    switchButton->click();
    QCOMPARE(pages->currentWidget()->objectName(), QString("cardActionFill"));
    QCOMPARE(switchButton->text(), QString("Top Up"));

    // the same card read again keeps the page; another card starts on the Fill page again
    switchButton->click();
    cards->showCard("04A1B2");
    QCOMPARE(pages->currentWidget()->objectName(), QString("cardActionTopUp"));
    cards->showCard("04A1B3");
    QCOMPARE(window.findChild<QLabel *>("cardHolder")->text(), QString("Wang Fang"));
    QCOMPARE(pages->currentWidget()->objectName(), QString("cardActionFill"));
    QCOMPARE(switchButton->text(), QString("Top Up"));
}

// OK on the Fill page: the checks, the PIN, then the card pays and a ticket is made, printed (here: no printer), and can be printed again or cancelled
void Smart210Test::theFillPageMakesATicketForTheCardAndPrintsIt()
{
    setUpSellingTerminal();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const Cards::Registration li = Cards::registerCard(db, "04A1B2", "Li Wei", "D", "1234", "");
    QVERIFY(li.ok);
    QVERIFY(Cards::topUp(db, li.cardId, 5000, 750, 7, QDateTime::currentDateTime().addSecs(-60)).ok);

    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", db);
    QTRY_VERIFY_WITH_TIMEOUT(server_->log().contains("GET /api/pull/ticket_loans?limit=200"), 10000);
    QTest::qWait(300); // the start-up download has finished
    window.showPage("cards");
    window.findChild<CardPage *>()->showCard("04A1B2");
    QLineEdit *kgField = window.findChild<QLineEdit *>("fillKgField");
    QLineEdit *pin = window.findChild<QLineEdit *>("fillPinField");
    QPushButton *ok = window.findChild<QPushButton *>("fillOkButton");
    QPushButton *printAgain = window.findChild<QPushButton *>("ticketPrintButton");
    QPushButton *cancelTicket = window.findChild<QPushButton *>("ticketCancelButton");
    QWidget *ticketBox = window.findChild<QWidget *>("ticketBox");
    QLabel *message = window.findChild<QLabel *>("cardMessage");
    QVERIFY(kgField != 0 && pin != 0 && ok != 0 && printAgain != 0 && cancelTicket != 0 && ticketBox != 0 && message != 0);
    const QString cardTickets = "SELECT COUNT(*) FROM tickets WHERE card_id = '" + li.cardId + "'";

    // no weight, more than the card holds (before the PIN is asked), a wrong PIN (it counts)
    ok->click();
    QVERIFY(message->text().contains("Enter the weight"));
    kgField->setText("60");
    pin->setText("9999");
    ok->click();
    QVERIFY(message->text().contains("holds only 50.00 kg"));
    QCOMPARE(Cards::find(db, "04A1B2").failedAttempts, 0);
    kgField->setText("10");
    pin->setText("9999");
    ok->click();
    QVERIFY(message->text().contains("Wrong PIN. 4 tries left"));
    QCOMPARE(Cards::find(db, "04A1B2").failedAttempts, 1);
    QCOMPARE(pin->text(), QString());
    QCOMPARE(one(cardTickets).toInt(), 0);
    QCOMPARE(Cards::find(db, "04A1B2").balanceX100, qlonglong(5000));

    // the right PIN: the card pays, a ticket of the direct kind is made (sold, no sale behind it, waiting for the upload), the fields are empty again
    kgField->setText("10");
    pin->setText("1234");
    ok->click();
    QVERIFY2(message->text().contains("is made. The card holds 40.00 kg."), qPrintable(message->text()));
    QCOMPARE(one(cardTickets).toInt(), 1);
    QCOMPARE(one("SELECT COUNT(*) FROM tickets WHERE card_id = '" + li.cardId + "' AND amount_kg = 1000 AND status = 1 AND sale_id IS NULL AND synced = 0 AND fuel_type = 'D' "
                 "AND substr(printf('%016d', barcode), 6, 2) = '25'").toInt(), 1);
    QCOMPARE(Cards::find(db, "04A1B2").balanceX100, qlonglong(4000));
    QCOMPARE(Cards::find(db, "04A1B2").failedAttempts, 0);
    QCOMPARE(window.findChild<QLabel *>("cardBalance")->text(), QString("40.00"));
    QCOMPARE(kgField->text(), QString());
    QCOMPARE(pin->text(), QString());
    const QString barcode = Cards::lastTicket(db, li.cardId);
    QCOMPARE(barcode.size(), 16);
    QVERIFY(message->text().contains(barcode));
    QVERIFY(ticketBox->isVisibleTo(window.findChild<CardPage *>()));
    QVERIFY(window.findChild<QLabel *>("ticketLabel")->text().contains(barcode));
    QVERIFY(window.findChild<QLabel *>("ticketLabel")->text().contains("10.00 kg"));
    QCOMPARE(countRows("supplements"), 0);                                  // nothing was supplied yet: the supplier scans the ticket
    QCOMPARE(countRows("remainders"), 0);

    // there is no printer address: the ticket is made, it only says so; Print again tries once more
    QVERIFY(message->text().contains("It was not printed"));
    QVERIFY(message->text().contains("No printer is set up"));
    QVERIFY(printAgain->isEnabled());
    message->clear();
    printAgain->click();
    QVERIFY(message->text().contains("No printer is set up"));

    // Cancel ticket asks first; Cancel does nothing, OK takes the ticket away and gives the kg back
    ConfirmDialog::setAutoAnswer(ConfirmDialog::No);
    cancelTicket->click();
    QVERIFY(ConfirmDialog::lastQuestion().contains(barcode) && ConfirmDialog::lastQuestion().contains("10.00 kg"));
    QCOMPARE(one(cardTickets).toInt(), 1);
    ConfirmDialog::setAutoAnswer(ConfirmDialog::Yes);
    cancelTicket->click();
    QVERIFY2(message->text().contains("is cancelled: 10.00 kg are back on the card. The card holds 50.00 kg."), qPrintable(message->text()));
    QCOMPARE(one(cardTickets).toInt(), 0);
    QCOMPARE(Cards::find(db, "04A1B2").balanceX100, qlonglong(5000));
    QCOMPARE(window.findChild<QLabel *>("cardBalance")->text(), QString("50.00"));
    QVERIFY(!ticketBox->isVisibleTo(window.findChild<CardPage *>()));

    // a ticket the web app has already is not cancelled here
    kgField->setText("5");
    pin->setText("1234");
    ok->click();
    QCOMPARE(one(cardTickets).toInt(), 1);
    run("UPDATE tickets SET synced = 1");
    cancelTicket->click();
    QVERIFY2(message->text().contains("uploaded already"), qPrintable(message->text()));
    QCOMPARE(one(cardTickets).toInt(), 1);
    QCOMPARE(Cards::find(db, "04A1B2").balanceX100, qlonglong(4500));
}

// the Fill page: the cursor is in the weight, Enter moves on field by field (the PIN is the last) and Enter on the button presses it; the Top Up page the same
void Smart210Test::theCardActionsPutTheCursorInTheirFirstFieldAndEnterMovesOnWithThePinLast()
{
    setUpSellingTerminal();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const Cards::Registration li = Cards::registerCard(db, CardId, "TEST CARD1", "D", "1234", "");
    QVERIFY(Cards::topUp(db, li.cardId, 5000, 750, 7, QDateTime::currentDateTime().addSecs(-60)).ok);

    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", db);
    QTRY_VERIFY_WITH_TIMEOUT(server_->log().contains("GET /api/pull/ticket_loans?limit=200"), 10000);
    QTest::qWait(300);
    window.showPage("cards");
    window.findChild<CardPage *>()->showCard(CardId);

    // the order of the fields of the Fill page: the weight, the Result box, then the PIN (the last)
    QFormLayout *form = window.findChild<QFormLayout *>("fillForm");
    QVERIFY(form != 0);
    int row = -1;
    QFormLayout::ItemRole role;
    QList<int> rows;
    foreach (const char *name, QList<const char *>() << "fillKgField" << "fillResultCombo" << "fillPinField") {
        form->getWidgetPosition(window.findChild<QWidget *>(name), &row, &role);
        rows << row;
    }
    QCOMPARE(rows, QList<int>() << 0 << 1 << 2);

    // Fill: the cursor is in the weight; Enter goes to the Result box, to the PIN, then to OK
    QCOMPARE(window.focusWidget(), window.findChild<QWidget *>("fillKgField"));
    QTest::keyClick(window.focusWidget(), Qt::Key_Return);
    QCOMPARE(window.focusWidget(), window.findChild<QWidget *>("fillResultCombo"));
    QTest::keyClick(window.focusWidget(), Qt::Key_Return);
    QCOMPARE(window.focusWidget(), window.findChild<QWidget *>("fillPinField"));
    QTest::keyClick(window.focusWidget(), Qt::Key_Return);
    QCOMPARE(window.focusWidget(), window.findChild<QWidget *>("fillOkButton"));

    // Top Up: the cursor is in the weight; Enter goes to the price, the total, then the button
    window.findChild<QPushButton *>("cardTopUpButton")->click();
    QCOMPARE(window.focusWidget(), window.findChild<QWidget *>("topUpKgField"));
    QTest::keyClick(window.focusWidget(), Qt::Key_Return);
    QCOMPARE(window.focusWidget(), window.findChild<QWidget *>("topUpPriceField"));
    QTest::keyClick(window.focusWidget(), Qt::Key_Return);
    QCOMPARE(window.focusWidget(), window.findChild<QWidget *>("topUpTotal"));
    QTest::keyClick(window.focusWidget(), Qt::Key_Return);
    QCOMPARE(window.focusWidget(), window.findChild<QWidget *>("topUpButton"));

    // the cursor goes to the first field of the page whenever the page is shown again
    window.findChild<QPushButton *>("cardTopUpButton")->click();
    QCOMPARE(window.focusWidget(), window.findChild<QWidget *>("fillKgField"));
    window.findChild<QPushButton *>("cardTopUpButton")->click();
    QCOMPARE(window.focusWidget(), window.findChild<QWidget *>("topUpKgField"));

    // Enter on the button presses it: Top Up asks first (the answer is OK here) and tops up
    QLineEdit *kg = window.findChild<QLineEdit *>("topUpKgField");
    QTest::keyClicks(kg, "10");
    QTest::keyClick(kg, Qt::Key_Return);
    QTest::keyClick(window.focusWidget(), Qt::Key_Return);
    QTest::keyClick(window.focusWidget(), Qt::Key_Return);
    QCOMPARE(window.focusWidget(), window.findChild<QWidget *>("topUpButton"));
    QTest::keyClick(window.focusWidget(), Qt::Key_Return);
    QCOMPARE(Cards::find(db, CardId).balanceX100, qlonglong(6000));
    QVERIFY(ConfirmDialog::lastQuestion().contains("10.00 kg"));

    // Fill with the keyboard alone: weight, Enter, Enter on the Result box (Print ticket), PIN, Enter, Enter on OK
    window.findChild<QPushButton *>("cardTopUpButton")->click();
    QTest::keyClicks(window.focusWidget(), "20");
    QTest::keyClick(window.focusWidget(), Qt::Key_Return);
    QTest::keyClick(window.focusWidget(), Qt::Key_Return);
    QTest::keyClicks(window.focusWidget(), "1234");
    QTest::keyClick(window.focusWidget(), Qt::Key_Return);
    QCOMPARE(window.focusWidget(), window.findChild<QWidget *>("fillOkButton"));
    QTest::keyClick(window.focusWidget(), Qt::Key_Return);
    QCOMPARE(Cards::find(db, CardId).balanceX100, qlonglong(4000));
    QCOMPARE(one("SELECT COUNT(*) FROM tickets WHERE card_id = '" + li.cardId + "' AND amount_kg = 2000").toInt(), 1);
}

// Top Up and OK on the Fill page ask OK / Cancel; Cancel does nothing at all, OK does it
void Smart210Test::theCardActionsAskForAnOkBeforeTheyDoIt()
{
    setUpSellingTerminal();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const Cards::Registration li = Cards::registerCard(db, CardId, "TEST CARD1", "D", "1234", "");
    QVERIFY(Cards::topUp(db, li.cardId, 5000, 750, 7, QDateTime::currentDateTime().addSecs(-60)).ok);

    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", db);
    QTRY_VERIFY_WITH_TIMEOUT(server_->log().contains("GET /api/pull/ticket_loans?limit=200"), 10000);
    QTest::qWait(300);
    window.showPage("cards");
    window.findChild<CardPage *>()->showCard(CardId);
    QLabel *message = window.findChild<QLabel *>("cardMessage");
    ConfirmDialog::setAutoAnswer(ConfirmDialog::No);                        // the cashier presses Cancel

    // Top Up: asks with the weight, the card, the price and the total; Cancel: nothing is sold
    window.findChild<QPushButton *>("cardTopUpButton")->click();
    QLineEdit *topKg = window.findChild<QLineEdit *>("topUpKgField");
    QTest::keyClicks(topKg, "40");
    window.findChild<QPushButton *>("topUpButton")->click();
    const QString topQuestion = ConfirmDialog::lastQuestion();
    QVERIFY2(topQuestion.contains("40.00 kg") && topQuestion.contains("TEST CARD1") && topQuestion.contains("7.50") && topQuestion.contains("300.00"), qPrintable(topQuestion));
    QCOMPARE(one("SELECT COUNT(*) FROM sales WHERE sale_type = 'card'").toInt(), 1);   // (the first top-up of the set-up only)
    QCOMPARE(Cards::find(db, CardId).balanceX100, qlonglong(5000));
    QCOMPARE(topKg->text(), QString("40"));                                 // what was typed stays
    ConfirmDialog::setAutoAnswer(ConfirmDialog::Yes);                       // OK
    window.findChild<QPushButton *>("topUpButton")->click();
    QCOMPARE(one("SELECT COUNT(*) FROM sales WHERE sale_type = 'card'").toInt(), 2);
    QCOMPARE(Cards::find(db, CardId).balanceX100, qlonglong(9000));

    // nothing is asked when a check fails first (no weight)
    const QString before = ConfirmDialog::lastQuestion();
    window.findChild<QPushButton *>("topUpButton")->click();
    QVERIFY(message->text().contains("Enter the weight"));
    QCOMPARE(ConfirmDialog::lastQuestion(), before);

    // Fill: asks before the PIN is looked at, so Cancel does not count a wrong PIN and makes no ticket
    window.findChild<QPushButton *>("cardTopUpButton")->click();
    QLineEdit *fillKg = window.findChild<QLineEdit *>("fillKgField");
    QLineEdit *fillPin = window.findChild<QLineEdit *>("fillPinField");
    fillKg->setText("10");
    fillPin->setText("9999");
    ConfirmDialog::setAutoAnswer(ConfirmDialog::No);
    window.findChild<QPushButton *>("fillOkButton")->click();
    QVERIFY2(ConfirmDialog::lastQuestion().contains("Fill 10.00 kg for the card of TEST CARD1") && ConfirmDialog::lastQuestion().contains("90.00 kg"), qPrintable(ConfirmDialog::lastQuestion()));
    QCOMPARE(Cards::find(db, CardId).failedAttempts, 0);
    QCOMPARE(fillPin->text(), QString("9999"));
    QCOMPARE(one("SELECT COUNT(*) FROM tickets WHERE card_id IS NOT NULL").toInt(), 0);
    ConfirmDialog::setAutoAnswer(ConfirmDialog::Yes);
    window.findChild<QPushButton *>("fillOkButton")->click();
    QCOMPARE(Cards::find(db, CardId).failedAttempts, 1);                    // now the PIN was looked at (and it is wrong)
    QCOMPARE(one("SELECT COUNT(*) FROM tickets WHERE card_id IS NOT NULL").toInt(), 0);

    // OK with the right PIN: the ticket is made
    fillPin->setText("1234");
    window.findChild<QPushButton *>("fillOkButton")->click();
    QCOMPARE(Cards::find(db, CardId).failedAttempts, 0);
    QCOMPARE(one("SELECT COUNT(*) FROM tickets WHERE card_id IS NOT NULL").toInt(), 1);
    QCOMPARE(Cards::find(db, CardId).balanceX100, qlonglong(8000));
}
// the window itself: a question, Cancel and OK (OK is the default button, so Enter confirms)
void Smart210Test::theConfirmDialogHasOkAsItsDefaultAndCancel()
{
    ConfirmDialog ok("Confirm fill", "Fill 10.00 kg at nozzle 1?");
    QCOMPARE(ok.findChild<QLabel *>("confirmTitle")->text(), QString("Confirm fill"));
    QCOMPARE(ok.findChild<QLabel *>("confirmQuestion")->text(), QString("Fill 10.00 kg at nozzle 1?"));
    QPushButton *okButton = ok.findChild<QPushButton *>("confirmOkButton");
    QPushButton *cancelButton = ok.findChild<QPushButton *>("confirmCancelButton");
    QVERIFY(okButton != 0 && cancelButton != 0);
    QCOMPARE(okButton->text(), QString("OK"));
    QCOMPARE(cancelButton->text(), QString("Cancel"));
    QVERIFY(okButton->isDefault());
    okButton->click();
    QCOMPARE(ok.result(), int(QDialog::Accepted));

    ConfirmDialog cancel("Confirm top up", "Top up 40.00 kg?");
    cancel.findChild<QPushButton *>("confirmCancelButton")->click();
    QCOMPARE(cancel.result(), int(QDialog::Rejected));

    // `ask` answers by itself while the tests say so, and keeps what was asked
    ConfirmDialog::setAutoAnswer(ConfirmDialog::No);
    QVERIFY(!ConfirmDialog::ask(0, "Confirm", "Question one?"));
    QCOMPARE(ConfirmDialog::lastQuestion(), QString("Question one?"));
    ConfirmDialog::setAutoAnswer(ConfirmDialog::Yes);
    QVERIFY(ConfirmDialog::ask(0, "Confirm", "Question two?"));
    QCOMPARE(ConfirmDialog::lastQuestion(), QString("Question two?"));
}
void Smart210Test::theCardScreenRegistersTopsUpAndChecksThePin()
{
    setUpSellingTerminal();
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    QSqlDatabase db = QSqlDatabase::database(connection_);
    QTRY_VERIFY_WITH_TIMEOUT(server_->log().contains("GET /api/pull/ticket_loans?limit=200"), 10000);
    QTest::qWait(300); // the start-up download has finished
    window.showPage("cards");
    QCOMPARE(window.currentPage(), QString("cards"));

    QLineEdit *id = window.findChild<QLineEdit *>("cardIdField");
    QStackedWidget *panels = window.findChild<QStackedWidget *>("cardPanels");
    QLabel *message = window.findChild<QLabel *>("cardMessage");
    QVERIFY(id != 0 && panels != 0 && message != 0);
    QCOMPARE(panels->currentIndex(), 0);                                    // nothing until a card is read

    // a card that is not known is registered
    QCOMPARE(id->isReadOnly(), CardReader::supported());                    // with a reader the id is only read; on a PC (no reader) it is typed
    QVERIFY(window.findChild<QPushButton *>("cardLookupButton") == 0);
    QVERIFY(!window.findChild<QPushButton *>("cardHistoryButton")->isEnabled());
    if (!CardReader::supported()) {                                         // a PC: the id typed in the box and Enter read it like the reader would
        QVERIFY(id->focusPolicy() != Qt::NoFocus);
        QVERIFY(id->placeholderText().contains("Type the card id"));
        id->setText(" 77 ee ");
        QMetaObject::invokeMethod(id, "returnPressed");
        QCOMPARE(id->text(), QString("77EE"));
        QCOMPARE(panels->currentIndex(), 1);                                // not registered: the form
        QVERIFY(message->text().contains("not registered"));
    } else {
        QVERIFY(id->focusPolicy() == Qt::NoFocus);
    }
    window.findChild<CardPage *>()->showCard("04 a1 b2");
    QCOMPARE(id->text(), QString("04A1B2"));
    QCOMPARE(panels->currentIndex(), 1);
    QVERIFY(message->text().contains("not registered"));
    QLineEdit *name = window.findChild<QLineEdit *>("registerNameField");
    QComboBox *fuel = window.findChild<QComboBox *>("registerFuelCombo");
    QLineEdit *pin = window.findChild<QLineEdit *>("registerPinField");
    QLineEdit *again = window.findChild<QLineEdit *>("registerPinAgainField");
    QPushButton *registerButton = window.findChild<QPushButton *>("registerButton");
    name->setText("Li Wei");
    CardPage *cards = window.findChild<CardPage *>();
    QVERIFY(cards != 0);
    cards->showCard("04A1B2");                                              // the reader repeats the id while the card lies on it
    QCOMPARE(name->text(), QString("Li Wei"));
    QCOMPARE(panels->currentIndex(), 1);
    fuel->setCurrentIndex(fuel->findData("D"));
    pin->setText("1234");
    again->setText("1235");
    registerButton->click();
    QVERIFY(message->text().contains("not the same"));
    QVERIFY(!Cards::find(db, "04A1B2").exists);
    again->setText("1234");
    window.findChild<QLineEdit *>("registerPhoneField")->setText(" 138 0000 1111 ");   // the name and the phone number are typed at the registration
    registerButton->click();
    QVERIFY2(Cards::find(db, "04A1B2").exists, qPrintable(message->text()));
    QCOMPARE(Cards::find(db, "04A1B2").holderName, QString("Li Wei"));
    QCOMPARE(Cards::find(db, "04A1B2").holderPhone, QString("138 0000 1111"));
    QCOMPARE(window.findChild<QLabel *>("cardPhone")->text(), QString("138 0000 1111"));
    QCOMPARE(one("SELECT synced FROM cards WHERE uid = '04A1B2'").toInt(), 0);       // it goes up with the phone
    QCOMPARE(panels->currentIndex(), 2);                                    // the card's panel
    QCOMPARE(window.findChild<QLabel *>("cardHolder")->text(), QString("Li Wei"));
    QVERIFY(window.findChild<QLabel *>("cardFuel")->text().startsWith("D"));
    QCOMPARE(window.findChild<QLabel *>("cardBalance")->text(), QString("0.00"));
    QCOMPARE(window.findChild<QLabel *>("cardState")->text(), QString("Active"));
    QVERIFY(message->text().contains("registered"));

    // the top-up (the Top Up button above History shows its page): the price of the fuel is filled in, the total follows the weight
    window.findChild<QPushButton *>("cardTopUpButton")->click();
    QCOMPARE(window.findChild<QStackedWidget *>("cardActionPages")->currentWidget()->objectName(), QString("cardActionTopUp"));
    QLineEdit *kg = window.findChild<QLineEdit *>("topUpKgField");
    QLineEdit *price = window.findChild<QLineEdit *>("topUpPriceField");
    QCOMPARE(price->text(), QString("7.50"));
    QTest::keyClicks(kg, "40");
    QCOMPARE(window.findChild<QLineEdit *>("topUpTotal")->text(), QString("300.00"));
    window.findChild<QPushButton *>("topUpButton")->click();
    QCOMPARE(window.findChild<QLabel *>("cardBalance")->text(), QString("40.00"));
    QVERIFY(message->text().contains("Topped up 40.00 kg for 300.00"));
    QCOMPARE(one("SELECT COUNT(*) FROM sales WHERE sale_type = 'card' AND amount_kg = 4000 AND total_amount = 30000 AND payment_method = 'cash'").toInt(), 1);
    QCOMPARE(kg->text(), QString());

    // a top-up without a weight says so
    window.findChild<QPushButton *>("topUpButton")->click();
    QVERIFY(message->text().contains("Enter the weight"));

    // the total payment can be typed: the kg follows, and the typed total is what is paid (even when it is not kg x price)
    QLineEdit *total = window.findChild<QLineEdit *>("topUpTotal");
    QTest::keyClicks(total, "100");
    QCOMPARE(kg->text(), QString("13.33"));
    window.findChild<QPushButton *>("topUpButton")->click();
    QVERIFY(message->text().contains("Topped up 13.33 kg for 100.00"));
    QCOMPARE(one("SELECT COUNT(*) FROM sales WHERE sale_type = 'card' AND amount_kg = 1333 AND total_amount = 10000").toInt(), 1);
    QCOMPARE(Cards::find(db, "04A1B2").balanceX100, qlonglong(5333));
    QCOMPARE(total->text(), QString());
    QCOMPARE(kg->text(), QString());
    QTest::keyClicks(kg, "40");                                         // typing the kg again: the total is kg x price
    QCOMPARE(total->text(), QString("300.00"));
    kg->clear();
    total->clear();

    // the fill (the same button, now saying Fill, brings its page back): more than the card holds is refused before the PIN is asked
    window.findChild<QPushButton *>("cardTopUpButton")->click();
    QCOMPARE(window.findChild<QStackedWidget *>("cardActionPages")->currentWidget()->objectName(), QString("cardActionFill"));
    QLineEdit *fillPin = window.findChild<QLineEdit *>("fillPinField");
    QLineEdit *fillKg = window.findChild<QLineEdit *>("fillKgField");
    QPushButton *ok = window.findChild<QPushButton *>("fillOkButton");
    fillKg->setText("12");
    cards->showCard("04 a1 b2");                                            // the same card read again: what was typed stays
    QCOMPARE(fillKg->text(), QString("12"));
    QCOMPARE(panels->currentIndex(), 2);
    fillKg->setText("60");
    fillPin->setText("9999");
    ok->click();
    QVERIFY(message->text().contains("holds only 53.33 kg"));
    QCOMPARE(Cards::find(db, "04A1B2").failedAttempts, 0);

    // a wrong PIN counts
    fillKg->setText("10");
    fillPin->setText("9999");
    ok->click();
    QVERIFY(message->text().contains("Wrong PIN. 4 tries left"));
    QCOMPARE(Cards::find(db, "04A1B2").failedAttempts, 1);
    QVERIFY(window.findChild<QLabel *>("cardState")->text().contains("1 wrong PINs"));
    QCOMPARE(fillPin->text(), QString());

    // the right PIN is accepted and counts the wrong ones from zero; the ticket for the 10.00 kg is made
    fillPin->setText("1234");
    ok->click();
    QVERIFY(message->text().contains("is made. The card holds 43.33 kg."));
    QCOMPARE(Cards::find(db, "04A1B2").failedAttempts, 0);
    QCOMPARE(Cards::find(db, "04A1B2").balanceX100, qlonglong(4333));
    QCOMPARE(one("SELECT COUNT(*) FROM tickets WHERE card_id IS NOT NULL AND amount_kg = 1000").toInt(), 1);

    // five wrong PINs lock the card; it still shows, and can still be topped up
    for (int i = 0; i < 5; ++i) {
        fillKg->setText("5");
        fillPin->setText("9999");
        ok->click();
    }
    QCOMPARE(Cards::find(db, "04A1B2").status, QString("locked"));
    QVERIFY(message->text().contains("locked"));
    QCOMPARE(window.findChild<QLabel *>("cardState")->text(), QString("Locked (wrong PINs)"));
    fillKg->setText("5");
    fillPin->setText("1234");
    ok->click();
    QVERIFY(message->text().contains("locked"));
    QCOMPARE(one("SELECT COUNT(*) FROM tickets WHERE card_id IS NOT NULL").toInt(), 1);
    window.findChild<QPushButton *>("cardTopUpButton")->click();
    QTest::keyClicks(kg, "5");
    window.findChild<QPushButton *>("topUpButton")->click();
    QCOMPARE(window.findChild<QLabel *>("cardBalance")->text(), QString("48.33"));
}

void Smart210Test::theMachineConfigSavesTheCardReader()
{
    setUpLoggedInTerminalWithAPin();
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    window.showPage("settings");
    window.showPage("pin");
    window.findChild<QLineEdit *>("pinField")->setText("4821");
    window.findChild<QPushButton *>("pinOkButton")->click();
    QCOMPARE(window.currentPage(), QString("machine"));

    QLineEdit *port = window.findChild<QLineEdit *>("cardReaderPort");
    QComboBox *baud = window.findChild<QComboBox *>("cardReaderBaud");
    QComboBox *format = window.findChild<QComboBox *>("cardReaderFormat");
    QLineEdit *length = window.findChild<QLineEdit *>("cardReaderLength");
    QComboBox *encoding = window.findChild<QComboBox *>("cardReaderEncoding");
    QLabel *status = window.findChild<QLabel *>("machineStatus");
    QVERIFY(port != 0 && baud != 0 && format != 0 && length != 0 && encoding != 0);

    // the usual values
    QCOMPARE(port->text(), QString("/dev/ttyUSB0"));
    QCOMPARE(baud->currentText(), QString("9600"));
    QCOMPARE(format->itemData(format->currentIndex()).toString(), QString("line"));
    QCOMPARE(encoding->itemData(encoding->currentIndex()).toString(), QString("ascii"));
    QVERIFY(!length->isEnabled());                                          // only a fixed frame has a length
    QLineEdit *prefix = window.findChild<QLineEdit *>("cardReaderPrefix");
    QVERIFY(prefix != 0);
    QCOMPARE(prefix->text(), QString("CARD:"));                             // the station's Arduino reader says CARD: before the id
    QVERIFY(prefix->isEnabled());

    // a fixed frame needs its length
    format->setCurrentIndex(format->findData("fixed"));
    QMetaObject::invokeMethod(format, "activated", Q_ARG(int, format->currentIndex()));
    QVERIFY(length->isEnabled());
    QVERIFY(!prefix->isEnabled());                                          // the text before the id belongs to lines
    window.findChild<QPushButton *>("saveCardReaderButton")->click();
    QVERIFY(status->text().contains("how many characters"));
    QCOMPARE(Settings(QSqlDatabase::database(connection_)).value(SettingKey::CardReaderFormat), QString());

    port->setText("/dev/ttyUSB1");
    baud->setCurrentIndex(baud->findData(19200));
    length->setText("10");
    encoding->setCurrentIndex(encoding->findData("hex"));
    window.findChild<QPushButton *>("saveCardReaderButton")->click();
    QCOMPARE(status->text(), QString("Saved."));
    Settings settings(QSqlDatabase::database(connection_));
    QCOMPARE(settings.value(SettingKey::CardReaderPort), QString("/dev/ttyUSB1"));
    QCOMPARE(settings.intValue(SettingKey::CardReaderBaud), 19200);
    QCOMPARE(settings.value(SettingKey::CardReaderFormat), QString("fixed"));
    QCOMPARE(settings.intValue(SettingKey::CardReaderLength), 10);
    QCOMPARE(settings.value(SettingKey::CardReaderEncoding), QString("hex"));
    const MachineConfig::Change change = changes().recentChanges(1).at(0);
    QCOMPARE(change.what, QString("card reader"));
    QCOMPARE(change.newValue, QString("/dev/ttyUSB1 19200 fixed 10 hex"));
    QCOMPARE(change.userName, QString("Cash One"));

    // back to lines: the length is not kept
    format->setCurrentIndex(format->findData("line"));
    QMetaObject::invokeMethod(format, "activated", Q_ARG(int, format->currentIndex()));
    QVERIFY(!length->isEnabled());
    QVERIFY(prefix->isEnabled());
    prefix->setText(" RFID: ");
    window.findChild<QPushButton *>("saveCardReaderButton")->click();
    QCOMPARE(Settings(QSqlDatabase::database(connection_)).intValue(SettingKey::CardReaderLength), 0);
    QCOMPARE(Settings(QSqlDatabase::database(connection_)).value(SettingKey::CardReaderPrefix, "x"), QString("RFID:"));
    QCOMPARE(changes().recentChanges(1).at(0).newValue, QString("/dev/ttyUSB1 19200 line  hex RFID:"));

    // a reader that sends the bare id: the text before it is cleared, and that is kept (it is not the default again)
    prefix->clear();
    window.findChild<QPushButton *>("saveCardReaderButton")->click();
    QCOMPARE(Settings(QSqlDatabase::database(connection_)).value(SettingKey::CardReaderPrefix, "x"), QString());
}

void Smart210Test::theSchemaIsTheInitialMigrationAndTheCardLedgerWithTheColumnsInTheOrderOfTheWebTables()
{
    QSqlDatabase db = openDatabase();

    QCOMPARE(Migrator::bundled().size(), 12);                               // the initial schema, the ledger of the cards, the comment of a remainder, the order of the ledger, the index of the stock, the phone of a card holder (for old databases), the name of a nozzle, the card of a ticket, the print count of a ticket, the ranges of the old publishes, the hidden flag of the confirmed records of a card, the free payment flag of a card
    QCOMPARE(Migrator::version(db), 12);
    QCOMPARE(columnsOf(db, "card_transactions").join(" "), QString("id card_id type kg balance_kg price total_amount source_id occurred_at comment seq"));

    // the columns of the tables that travel to the web app, in the order of the web app's tables
    QCOMPARE(columnsOf(db, "sales").join(" "), QString("id created_at updated_at customer_id card_id sale_type fuel_type price amount_kg payment_method "
                                                       "total_amount paid_amount unpaid_amount receipt usage comment sale_date user_id deleted_at synced uploaded_at hidden"));   // (hidden: migration 011, not sent)
    QCOMPARE(columnsOf(db, "supplements").join(" "), QString("id fuel_type nozzle_id card_id supply_date supplied_at mode preset_kg density out_amount_lt "
                                                             "out_amount_kg comment board_result_id synced uploaded_at hidden"));
    QCOMPARE(columnsOf(db, "remainders").join(" "), QString("id supplement_id ticket_barcode fuel_type amount_kg remainder_date created_at printed_at synced uploaded_at comment"));
    QCOMPARE(columnsOf(db, "tickets").join(" "), QString("barcode fuel_type spec amount_kg status sale_id sale_date supplement_id supply_date synced uploaded_at card_id print_count last_printed_at"));   // (the columns of migrations 008 and 009 come last)
    QCOMPARE(columnsOf(db, "ticket_loans").join(" "), QString("id loaned_at date customer_id fuel_type amount_kg settled_amount_kg sale_id comment user_id deleted synced uploaded_at"));
    QCOMPARE(columnsOf(db, "ticket_loan_settlements").join(" "), QString("id loan_id ticket_barcode amount_kg settled_at date comment user_id synced uploaded_at"));
    QCOMPARE(columnsOf(db, "nozzles").join(" "), QString("id number tank_id fuel_type board_address mode density seen_at synced deleted name"));
    QCOMPARE(columnsOf(db, "supply_sessions").join(" "), QString("nozzle_number card_id preset_kg result_id started_at"));
    QCOMPARE(columnsOf(db, "checkouts").join(" "), QString("id date mode user_id performed_at records"));

    // none of the old names is left
    foreach (const QString &table, QStringList() << "sales" << "supplements" << "remainders" << "ticket_loans" << "ticket_loan_settlements" << "checkouts") {
        foreach (const QString &old, QStringList() << "checkout_date" << "sold_at" << "remainder_at" << "liters_dispensed" << "manual_reading" << "user_id_old") {
            QVERIFY2(!columnsOf(db, table).contains(old), qPrintable(table + "." + old));
        }
    }
    QVERIFY(!columnsOf(db, "nozzles").contains("board_host") && !columnsOf(db, "nozzles").contains("board_port"));
    QVERIFY(!columnsOf(db, "supplements").contains("user_id") && !columnsOf(db, "supply_sessions").contains("user_id"));

    // the indexes the code relies on
    QCOMPARE(one("SELECT COUNT(*) FROM sqlite_master WHERE type = 'index' AND name IN ('sales_sale_date', 'supplements_supply_date', 'supplements_board_result', "
                 "'sales_unsynced', 'checkouts_date', 'cards_uid', 'nozzles_number_live')").toInt(), 7);
    QCOMPARE(one("SELECT COUNT(*) FROM sqlite_master WHERE name LIKE '%checkout_date%' OR sql LIKE '%checkout_date%'").toInt(), 0);
}

// ---- screenshots of every screen (for reskinning the UI: take them before and after a change and compare) ----
//   set SCREENS_DIR to a folder, then run: tst_smart210 grabAllScreens   (without -platform offscreen, to get the real fonts)

namespace {

void shot(QWidget *window, const QString &dir, const QString &name)
{
    QCursor::setPos(QGuiApplication::primaryScreen()->geometry().bottomRight()); // the mouse is not over a button (no hover look in the picture)
    QTest::qWait(250);
    window->grab().save(dir + "/" + name + ".png");
}

}

void Smart210Test::grabAllScreens()
{
    const QString dir = QString::fromLocal8Bit(qgetenv("SCREENS_DIR"));
    if (dir.isEmpty()) {
        QSKIP("set SCREENS_DIR to a folder to take the screenshots of every screen");
    }
    QDir().mkpath(dir);
    const QString language = QString::fromLocal8Bit(qgetenv("SCREENS_LANG")); // en
    if (!language.isEmpty()) {
        Translations::install(qApp, language);
    }

    // a terminal with a cashier, nozzles, a PIN, some sales, a loan, a supply with a remainder and a card
    setUpLoggedInTerminalWithAPin();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    run("INSERT OR REPLACE INTO fuel_types (fuel_type, name, price, density) VALUES ('A', 'Gasoline', 800, 7400)");
    Settings(db).setValue(SettingKey::TicketSign, "00000");
    {
        Settings settings(db);
        setTicketCodes(settings);
    }
    Settings(db).setValue(SettingKey::TicketSerialBase, "500000000");
    for (int i = 0; i < 12; ++i) {
        run(QString("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (%1, 'D', %2, %3, 0, 1)")
                .arg(3000000101LL + i).arg(i < 8 ? 0 : 1).arg(i < 8 ? 500 : 1000));
    }
    run("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (3000000201, 'D', 0, 500, 1, 1)");
    run("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (3000000202, 'D', 0, 500, 1, 1)");
    const QDateTime now = QDateTime::currentDateTime();
    SpotSale::create(db, saleInput(1050, 2), 7, now.addSecs(-3600));
    SpotSale::create(db, saleInput(2000, 1), 7, now.addSecs(-1800));
    SpotSale::Input loan = saleInput(2500, 2);
    loan.usage = "ticket_loan";
    SpotSale::create(db, loan, 7, now.addSecs(-900));
    const QString card = makeCard(db, "04A1B2C3", 4000);
    Q_UNUSED(card);
    SupplyQueue::add(db, 1, code(3000000201), "D", now);
    SupplyQueue::add(db, 1, code(3000000202), "D", now);
    SupplyQueue::begin(db, 1, 1000, now);
    SupplyRecord::Reading reading;
    reading.resultId = 5;
    reading.dispensedX100 = 800;
    reading.litersX100 = 964;
    reading.presetX100 = 1000;
    reading.reason = BoardProtocol::ReasonTankFullOrNoFlow;
    SupplyRecord::record(db, 1, reading, now);

    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    {
        MainWindow window(config, QString(), "db", db);
        window.resize(1280, 720);
        window.show();
        QTest::qWait(500);
        foreach (const QString &name, QStringList() << "spot" << "ticket" << "review" << "upload" << "supply" << "manual" << "remainders"
                                                    << "dispensers" << "loan" << "settings" << "pin") {
            window.showPage(name);
            shot(&window, dir, name);
        }

        window.showPage("cards");
        window.findChild<CardPage *>()->showCard("04A1B2C3");
        shot(&window, dir, "cards");
        window.findChild<CardPage *>()->showCard("99FF00AA");
        shot(&window, dir, "cards_new");


        window.showPage("settings");
        window.showPage("pin");
        window.findChild<QLineEdit *>("pinField")->setText("4821");
        window.findChild<QPushButton *>("pinOkButton")->click();
        QTabWidget *tabs = 0;
        QLineEdit *readerPort = window.findChild<QLineEdit *>("cardReaderPort");
        foreach (QTabWidget *candidate, window.findChildren<QTabWidget *>()) {
            if (candidate->isAncestorOf(readerPort) && (tabs == 0 || tabs->isAncestorOf(candidate))) {
                tabs = candidate; // the innermost one that holds the card reader tab
            }
        }
        QVERIFY(tabs != 0);
        for (int i = 0; i < tabs->count(); ++i) {
            tabs->setCurrentIndex(i);
            shot(&window, dir, QString("machine_%1").arg(i));
        }
    }

    // a terminal nobody has logged in to, and a new one that is not set up yet
    {
        Session(db).logout();
        MainWindow window(config, QString(), "db", db);
        window.resize(1280, 720);
        window.show();
        QTest::qWait(300);
        shot(&window, dir, "login");
    }
    {
        QString error;
        const QString file = dir_->path() + "/fresh/smart210.db";
        QVERIFY(Database::open(file, "fresh", &error));
        QVERIFY(Migrator::apply(QSqlDatabase::database("fresh"), Migrator::bundled(), &error));
        AppConfig fresh;
        fresh.setBoardLink(false);
        MainWindow window(fresh, QString(), "db", QSqlDatabase::database("fresh"));
        window.resize(1280, 720);
        window.show();
        shot(&window, dir, "setup");
    }
    Database::close("fresh");
    removeTranslators();
}

void Smart210Test::theThemeComesFromTheResourcesOrFromAFileThatIsReadAgainWhenSaved()
{
    // the built-in style sheet is resources/theme.qss; a file that cannot be read is not used
    const QString builtIn = Theme::styleSheet();
    QVERIFY(builtIn.contains("QPushButton"));
    QVERIFY(builtIn.contains("QLabel[state=\"error\"]"));
    QCOMPARE(Theme::styleSheet(dir_->path() + "/no-such-theme.qss"), builtIn);

    // a theme file being tried: used at once, and read again whenever it is saved
    const QString file = dir_->path() + "/try.qss";
    QFile first(file);
    QVERIFY(first.open(QIODevice::WriteOnly | QIODevice::Text));
    first.write("QLabel#pageTitle { color: #123456; }\n");
    first.close();

    setUpLoggedInTerminalWithAPin();
    AppConfig config;
    config.setBoardLink(false);
    config.setThemeFile(file);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    QVERIFY(window.styleSheet().contains("#123456"));
    QVERIFY(!window.styleSheet().contains("QPushButton"));

    QFile second(file);
    QVERIFY(second.open(QIODevice::WriteOnly | QIODevice::Truncate | QIODevice::Text));
    second.write("QLabel#pageTitle { color: #654321; }\n");
    second.close();
    QTRY_VERIFY_WITH_TIMEOUT(window.styleSheet().contains("#654321"), 5000);

    // a message takes its colour from its state
    QLabel label;
    Theme::setState(&label, "error");
    QCOMPARE(label.property("state").toString(), QString("error"));
    Theme::setState(&label, "");
    QCOMPARE(label.property("state").toString(), QString());
}

void Smart210Test::aCashierWhoHasLoggedInCannotOpenMachineConfig()
{
    setUpLoggedInTerminalWithAPin();
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));
    QCOMPARE(window.currentPage(), QString("spot"));

    // the Settings page after the login has Log out and nothing about Machine Config
    window.showPage("settings");
    QVERIFY(window.findChild<QPushButton *>("logoutButton") != 0);
    QVERIFY(window.findChild<QPushButton *>("machineConfigButton") == 0);
    QVERIFY(window.findChild<QPushButton *>("loginSettingsButton") != 0);   // that one is on the login screen, which is not shown now
    QVERIFY(!window.findChild<QPushButton *>("loginSettingsButton")->isVisibleTo(&window) || window.currentPage() != "login");

    // after logging out, the login screen is the way in
    window.findChild<QPushButton *>("logoutButton")->click();
    QCOMPARE(window.currentPage(), QString("login"));
    QVERIFY(window.findChild<QPushButton *>("loginSettingsButton")->isVisibleTo(&window));
}

void Smart210Test::answerTheSettleDialog()
{
    LoanSettleDialog *dialog = 0;
    foreach (QWidget *widget, QApplication::topLevelWidgets()) {
        if (LoanSettleDialog *found = qobject_cast<LoanSettleDialog *>(widget)) {
            if (found->isVisible()) {
                dialog = found;
            }
        }
    }
    if (dialog == 0) {
        QTimer::singleShot(50, this, SLOT(answerTheSettleDialog()));

        return;
    }

    QLineEdit *scan = dialog->findChild<QLineEdit *>("settleScanField");
    QLabel *status = dialog->findChild<QLabel *>("settleStatus");
    settleSeen_.clear();
    foreach (const QString &ticket, settleTickets_) {
        typeAndEnter(scan, ticket);
        settleSeen_ << status->text();
    }
    settleTotals_ = dialog->findChild<QLabel *>("settleTotals")->text();
    dialog->findChild<QPushButton *>(settleNow_ ? "settleButton" : "settleCloseButton")->click();
}

void Smart210Test::answerTheMessageBox()
{
    QMessageBox *box = 0;
    foreach (QWidget *widget, QApplication::topLevelWidgets()) {
        if (QMessageBox *found = qobject_cast<QMessageBox *>(widget)) {
            if (found->isVisible()) {
                box = found;
            }
        }
    }
    if (box == 0) {
        QTimer::singleShot(50, this, SLOT(answerTheMessageBox()));

        return;
    }
    boxText_ = box->text();
    box->button(boxAnswer_)->click();
}

void Smart210Test::answerTheNozzleQuestion()
{
    NozzleDialog *dialog = 0;
    foreach (QWidget *widget, QApplication::topLevelWidgets()) {
        if (NozzleDialog *found = qobject_cast<NozzleDialog *>(widget)) {
            if (found->isVisible()) {
                dialog = found;
            }
        }
    }
    if (dialog == 0) {
        QTimer::singleShot(50, this, SLOT(answerTheNozzleQuestion()));

        return;
    }

    QListWidget *list = dialog->findChild<QListWidget *>("nzList");
    nozzleRows_ = list->count();
    nozzleRowsEnabled_ = 0;
    for (int row = 0; row < list->count(); ++row) {
        if (list->item(row)->flags() & Qt::ItemIsEnabled) {
            ++nozzleRowsEnabled_;
        }
        if (list->item(row)->data(Qt::UserRole).toInt() == nozzleToChoose_) {
            list->setCurrentRow(row);
        }
    }
    dialog->findChild<QPushButton *>(nozzleToChoose_ > 0 ? "nzOkButton" : "nzCancelButton")->click();
}

void Smart210Test::answerTheNozzleQuestionWithTheKeyboard()
{
    NozzleDialog *dialog = 0;
    foreach (QWidget *widget, QApplication::topLevelWidgets()) {
        if (NozzleDialog *found = qobject_cast<NozzleDialog *>(widget)) {
            if (found->isVisible()) {
                dialog = found;
            }
        }
    }
    if (dialog == 0) {
        QTimer::singleShot(50, this, SLOT(answerTheNozzleQuestionWithTheKeyboard()));

        return;
    }

    QListWidget *list = dialog->findChild<QListWidget *>("nzList");
    nozzleListHasFocus_ = (dialog->focusWidget() == list);
    nozzleFirstChosen_ = dialog->nozzle();
    if (nozzleToChoose_ <= 0) {
        QTest::keyClick(list, Qt::Key_Escape);                         // Cancel
        return;
    }
    for (int i = 0; i < 5 && dialog->nozzle() != nozzleToChoose_; ++i) {
        QTest::keyClick(list, Qt::Key_Down);                          // the arrow keys choose (a nozzle that is busy is jumped over)
    }
    QTest::keyClick(list, Qt::Key_Return);                             // Enter is OK
}

void Smart210Test::theNozzleQuestionIsAnsweredWithTheKeyboardAlone()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    SupplyBench bench(db);
    QVERIFY(bench.session.login(7, "secret1"));
    QVERIFY(startBus(&bench.bus, &bench.boards));
    QTRY_VERIFY_WITH_TIMEOUT(bench.bus.isOnline(1), 3000);
    QTRY_VERIFY_WITH_TIMEOUT(bench.bus.isOnline(2), 3000);
    PrintService printer(db, PrintOptions());
    Settings settings(db);
    SpotSalePage page(db, &bench.session, &printer, &bench.bus, &bench.config, &bench.selection, &bench.supply, &settings);
    page.reload();

    page.findChild<QComboBox *>("resultCombo")->setCurrentIndex(1);
    QComboBox *customer = page.findChild<QComboBox *>("customerCombo");
    customer->setCurrentIndex(customer->findText("ACME"));
    QLabel *status = page.findChild<QLabel *>("saleStatus");

    // Enter answers with the first free nozzle (1), which was chosen when the question opened and has the cursor
    QTest::keyClicks(page.findChild<QLineEdit *>("kgField"), "10");
    nozzleToChoose_ = 1;
    nozzleFirstChosen_ = -1;
    nozzleListHasFocus_ = false;
    QTimer::singleShot(50, this, SLOT(answerTheNozzleQuestionWithTheKeyboard()));
    page.findChild<QPushButton *>("saveSaleButton")->click();
    QCOMPARE(nozzleFirstChosen_, 1);
    QVERIFY(nozzleListHasFocus_);
    QTRY_VERIFY_WITH_TIMEOUT(status->text().contains("Nozzle 1: 10.00 kg are set"), 5000);
    QCOMPARE(SupplyQueue::entries(db, 1).size(), 1);

    // the arrow keys choose another nozzle: nozzle 1 has its preset (the board may not have said so yet, then it is still the first one chosen
    // and the Down key moves to 2; otherwise 2 is the only free one and is chosen at once)
    QTest::keyClicks(page.findChild<QLineEdit *>("kgField"), "5");
    nozzleToChoose_ = 2;
    nozzleFirstChosen_ = -1;
    QTimer::singleShot(50, this, SLOT(answerTheNozzleQuestionWithTheKeyboard()));
    page.findChild<QPushButton *>("saveSaleButton")->click();
    QVERIFY(nozzleFirstChosen_ == 1 || nozzleFirstChosen_ == 2);
    QTRY_VERIFY_WITH_TIMEOUT(status->text().contains("Nozzle 2: 5.00 kg are set"), 5000);

    // Escape is Cancel: the sale stays, no nozzle gets it
    QTest::keyClicks(page.findChild<QLineEdit *>("kgField"), "3");
    nozzleToChoose_ = 0;
    QTimer::singleShot(50, this, SLOT(answerTheNozzleQuestionWithTheKeyboard()));
    page.findChild<QPushButton *>("saveSaleButton")->click();
    QVERIFY(status->text().contains("No nozzle was chosen"));
    QCOMPARE(countRows("sales"), 3);
    QCOMPARE(SupplyQueue::entries(db, 1).size() + SupplyQueue::entries(db, 2).size(), 2);
}

void Smart210Test::aSpotSaleSetDirectlySendsTheWeightToTheChosenNozzleWithoutATicket()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    SupplyBench bench(db);
    QVERIFY(bench.session.login(7, "secret1"));
    QVERIFY(startBus(&bench.bus, &bench.boards));
    QTRY_VERIFY_WITH_TIMEOUT(bench.bus.isOnline(1), 3000);
    QTRY_VERIFY_WITH_TIMEOUT(bench.bus.isOnline(2), 3000);
    PrintService printer(db, PrintOptions());
    Settings settings(db);
    SpotSalePage page(db, &bench.session, &printer, &bench.bus, &bench.config, &bench.selection, &bench.supply, &settings);
    page.reload();

    QComboBox *result = page.findChild<QComboBox *>("resultCombo");
    QCOMPARE(result->count(), 2);
    QCOMPARE(result->itemText(0), QString("Print ticket"));
    QCOMPARE(result->itemText(1), QString("Set directly"));
    QCOMPARE(result->currentIndex(), 0);                             // the ticket is printed unless the setting of the Settings page says otherwise

    // the setting is only what the box starts with: the page takes it when it is opened, and the box can be changed for a sale
    QVERIFY(settings.setValue(SettingKey::SpotSaleResult, "direct"));
    page.reload();
    QCOMPARE(result->currentIndex(), 1);
    QVERIFY(settings.setValue(SettingKey::SpotSaleResult, "print"));
    page.reload();
    QCOMPARE(result->currentIndex(), 0);
    result->setCurrentIndex(1);
    QVERIFY(!settings.resultIsDirect(SettingKey::SpotSaleResult));    // choosing in the box does not change the setting
    QComboBox *customer = page.findChild<QComboBox *>("customerCombo");
    customer->setCurrentIndex(customer->findText("ACME"));
    QTest::keyClicks(page.findChild<QLineEdit *>("kgField"), "10.5");
    nozzleToChoose_ = 2;
    nozzleRows_ = nozzleRowsEnabled_ = -1;
    QTimer::singleShot(50, this, SLOT(answerTheNozzleQuestion()));

    page.findChild<QPushButton *>("saveSaleButton")->click();        // the question is open until it is answered

    QCOMPARE(nozzleRows_, 3);                                        // the auto nozzles of Diesel: 1, 2 and 4 (3 is manual)
    QCOMPARE(nozzleRowsEnabled_, 2);                                 // the board of nozzle 4 does not answer
    QLabel *status = page.findChild<QLabel *>("saleStatus");
    QTRY_VERIFY_WITH_TIMEOUT(status->text().contains("Nozzle 2: 10.50 kg are set"), 5000);
    QVERIFY(status->text().startsWith("Saved: 10.50 kg"));
    QVERIFY(!status->text().contains("Printing"));
    QVERIFY(!printer.isBusy());                                      // nothing was printed or sent to the printer
    QCOMPARE(countRows("sales"), 1);

    // the ticket of the sale is in the queue of the nozzle, as when the supplier scans it, and the preset reaches the board by itself
    QCOMPARE(SupplyQueue::entries(db, 2).size(), 1);
    QCOMPARE(SupplyQueue::entries(db, 2).first().barcode, one("SELECT barcode FROM tickets WHERE sale_id IS NOT NULL").toLongLong());
    QTRY_COMPARE_WITH_TIMEOUT(bench.boards.board(2).state, int(BoardProtocol::StateArmed), 3000);
    QCOMPARE(bench.boards.board(2).presetG, quint32(10500));
    QVERIFY(!bench.supply.isLocked(2));                                  // until the supplier takes the gun up
    QVERIFY(bench.boards.simulator().liftGun(2));
    QTRY_VERIFY_WITH_TIMEOUT(bench.supply.isLocked(2), 3000);
    QTRY_COMPARE_WITH_TIMEOUT(bench.boards.board(2).state, int(BoardProtocol::StateDispensing), 3000);
    QCOMPARE(SupplyQueue::session(db, 2).presetX100, qlonglong(1050));
    QVERIFY(!SupplyQueue::session(db, 1).exists);

    // the fill ends: the supplement is recorded with the sale's ticket
    QSignalSpy recorded(&bench.supply, SIGNAL(recorded(int,QString)));
    bench.boards.simulator().tick(80000);
    QTRY_COMPARE_WITH_TIMEOUT(recorded.size(), 1, 5000);
    QCOMPARE(one("SELECT COUNT(*) FROM tickets WHERE sale_id IS NOT NULL AND supplement_id IS NOT NULL").toInt(), 1);
}

void Smart210Test::aSpotSaleSetDirectlyThatIsCancelledKeepsTheSaleAndItsTicket()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    SupplyBench bench(db);
    QVERIFY(bench.session.login(7, "secret1"));
    QVERIFY(startBus(&bench.bus, &bench.boards));
    QTRY_VERIFY_WITH_TIMEOUT(bench.bus.isOnline(1), 3000);
    PrintService printer(db, PrintOptions());
    Settings settings(db);
    SpotSalePage page(db, &bench.session, &printer, &bench.bus, &bench.config, &bench.selection, &bench.supply, &settings);
    page.reload();

    page.findChild<QComboBox *>("resultCombo")->setCurrentIndex(1);
    QComboBox *customer = page.findChild<QComboBox *>("customerCombo");
    customer->setCurrentIndex(customer->findText("ACME"));
    QTest::keyClicks(page.findChild<QLineEdit *>("kgField"), "5");
    nozzleToChoose_ = 0;                                             // Cancel
    QTimer::singleShot(50, this, SLOT(answerTheNozzleQuestion()));

    page.findChild<QPushButton *>("saveSaleButton")->click();

    QLabel *status = page.findChild<QLabel *>("saleStatus");
    QVERIFY(status->text().startsWith("Saved: 5.00 kg"));
    QVERIFY(status->text().contains("No nozzle was chosen"));
    QCOMPARE(countRows("sales"), 1);                                 // the sale stays
    QCOMPARE(one("SELECT COUNT(*) FROM tickets WHERE sale_id IS NOT NULL").toInt(), 1);
    QVERIFY(!SupplyQueue::session(db, 1).exists);
    QCOMPARE(SupplyQueue::entries(db, 1).size(), 0);
    QVERIFY(page.findChild<QPushButton *>("printAgainButton")->isEnabled());   // the ticket can still be printed
    QCOMPARE(page.findChild<QComboBox *>("resultCombo")->currentIndex(), 1);   // and the mode stays for the next sale
}

void Smart210Test::theSettingsPageChoosesWhatASpotSaleAndACardFillGive()
{
    setUpLoggedInTerminalWithAPin();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", db);

    window.showPage("settings");
    QComboBox *spot = window.findChild<QComboBox *>("spotResultCombo");
    QComboBox *card = window.findChild<QComboBox *>("cardResultCombo");
    QVERIFY(spot != 0 && card != 0);
    QCOMPARE(spot->count(), 2);
    QCOMPARE(spot->itemText(0), QString("Print ticket"));
    QCOMPARE(spot->itemText(1), QString("Set directly"));
    QCOMPARE(card->itemText(1), QString("Set directly"));
    QCOMPARE(spot->currentIndex(), 0);                                       // a terminal that never chose prints
    QCOMPARE(card->currentIndex(), 0);
    QVERIFY(!Settings(db).resultIsDirect(SettingKey::SpotSaleResult));
    QVERIFY(!Settings(db).resultIsDirect(SettingKey::CardFillResult));

    // each is saved when it changes, one apart from the other
    spot->setCurrentIndex(1);
    QVERIFY(Settings(db).resultIsDirect(SettingKey::SpotSaleResult));
    QVERIFY(!Settings(db).resultIsDirect(SettingKey::CardFillResult));
    card->setCurrentIndex(1);
    QVERIFY(Settings(db).resultIsDirect(SettingKey::CardFillResult));
    spot->setCurrentIndex(0);
    QVERIFY(!Settings(db).resultIsDirect(SettingKey::SpotSaleResult));
    QVERIFY(Settings(db).resultIsDirect(SettingKey::CardFillResult));

    // and the page shows what is saved when it is opened again
    window.showPage("spot");
    window.showPage("settings");
    QCOMPARE(spot->currentIndex(), 0);
    QCOMPARE(card->currentIndex(), 1);
    QVERIFY(Settings::isDeviceBound(SettingKey::SpotSaleResult) && Settings::isDeviceBound(SettingKey::CardFillResult));   // they stay with the machine
}

// A card fill set directly: the ticket is made (the card pays) and the question for the nozzle opens, nothing is printed; with no nozzle chosen the ticket stays
// for Print again or Cancel.
void Smart210Test::aCardFillSetDirectlyAsksForTheNozzleAndPrintsNothing()
{
    setUpSellingTerminal();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const Cards::Registration li = Cards::registerCard(db, "04A1B2", "Li Wei", "D", "1234", "");
    QVERIFY(li.ok);
    QVERIFY(Cards::topUp(db, li.cardId, 5000, 750, 7, QDateTime::currentDateTime().addSecs(-60)).ok);
    QVERIFY(Settings(db).setValue(SettingKey::CardFillResult, "direct"));

    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", db);
    QTRY_VERIFY_WITH_TIMEOUT(server_->log().contains("GET /api/pull/ticket_loans?limit=200"), 10000);
    QTest::qWait(300);
    window.showPage("cards");
    window.findChild<CardPage *>()->showCard("04A1B2");
    QLineEdit *kgField = window.findChild<QLineEdit *>("fillKgField");
    QLineEdit *pin = window.findChild<QLineEdit *>("fillPinField");
    QPushButton *ok = window.findChild<QPushButton *>("fillOkButton");
    QLabel *message = window.findChild<QLabel *>("cardMessage");

    kgField->setText("10");
    pin->setText("1234");
    QComboBox *result = window.findChild<QComboBox *>("fillResultCombo");
    QVERIFY(result != 0);
    QCOMPARE(result->itemText(0), QString("Print ticket"));
    QCOMPARE(result->itemText(1), QString("Set directly"));
    QCOMPARE(result->currentIndex(), 1);                                    // it starts with the setting of the Settings page
    nozzleToChoose_ = 0;                                                    // Cancel
    QTimer::singleShot(50, this, SLOT(answerTheNozzleQuestion()));
    ok->click();                                                            // the question is open until it is answered

    QVERIFY2(message->text().contains("is made. The card holds 40.00 kg."), qPrintable(message->text()));
    QVERIFY2(message->text().contains("No nozzle was chosen"), qPrintable(message->text()));
    QVERIFY(message->text().contains("Print again prints the ticket."));
    QVERIFY(!message->text().contains("Printing"));
    QVERIFY(!message->text().contains("It was not printed"));               // nothing was sent to the printer
    QCOMPARE(Cards::find(db, "04A1B2").balanceX100, qlonglong(4000));       // the card paid
    QCOMPARE(one("SELECT COUNT(*) FROM tickets WHERE card_id = '" + li.cardId + "' AND status = 1 AND print_count = 0").toInt(), 1);
    QVERIFY(window.findChild<QPushButton *>("ticketPrintButton")->isEnabled());      // the ticket can still be printed
    QVERIFY(window.findChild<QPushButton *>("ticketCancelButton")->isEnabled());     // or cancelled
}

void Smart210Test::salesHasOnlyTheSpotSaleAndTheTicketSale()
{
    setUpLoggedInTerminalWithAPin();
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", QSqlDatabase::database(connection_));

    window.showPage("ticket");
    QVERIFY(window.findChild<QPushButton *>("navSales")->isChecked());
    QStringList tabs;
    foreach (QTabWidget *widget, window.findChildren<QTabWidget *>()) {
        if (widget->count() > 0 && widget->tabText(0) == "Spot sale") {
            for (int i = 0; i < widget->count(); ++i) {
                tabs << widget->tabText(i);
            }
        }
    }
    QCOMPARE(tabs, QStringList() << "Spot sale" << "Ticket sale");       // no Recent sales, no Card sale
    QVERIFY(window.findChild<QTableWidget *>("recentTable") == 0);
    QVERIFY(window.findChild<QLineEdit *>("csCardIdField") == 0);

    // the sidebar returns to the tab that was open
    window.showPage("review");
    window.findChild<QPushButton *>("navSales")->click();
    QCOMPARE(window.currentPage(), QString("ticket"));
}

void Smart210Test::aCardOnTheReaderOpensTheCardsScreenWithTheCardShown()
{
    setUpLoggedInTerminalWithAPin();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    run("INSERT OR REPLACE INTO fuel_types (fuel_type, name, price, density) VALUES ('D', 'Diesel', 750, 8300)");
    const QString card = makeCard(db, "04A1B2C3", 1000);   // 10.00 kg
    const QString lost = makeCard(db, "DEAD0001", 0);
    run("UPDATE cards SET status = 'blocked' WHERE id = '" + lost + "'");
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", db);
    QCOMPARE(window.currentPage(), QString("spot"));
    CardReader *reader = window.findChild<CardReader *>();
    QVERIFY(reader != 0);

    reader->feed("04a1b2c3\r\n");                         // the card is put on the reader: its screen opens, the card shown

    QCOMPARE(window.currentPage(), QString("cards"));
    QVERIFY(window.findChild<QPushButton *>("navCards")->isChecked());
    QCOMPARE(window.findChild<QLineEdit *>("cardIdField")->text(), QString("04A1B2C3"));
    QCOMPARE(window.findChild<QLabel *>("cardHolder")->text(), QString("Li Wei"));
    QVERIFY(window.findChild<QLabel *>("cardFuel")->text().startsWith("D"));
    QCOMPARE(window.findChild<QLabel *>("cardBalance")->text(), QString("10.00"));
    QCOMPARE(window.findChild<QStackedWidget *>("cardPanels")->currentIndex(), 2);   // the card's panel: top up and fill
    QVERIFY(window.findChild<QPushButton *>("cardHistoryButton")->isEnabled());
    QCOMPARE(window.findChild<QLineEdit *>("topUpPriceField")->text(), QString("7.50"));

    // the top-up is on this screen
    QTest::keyClicks(window.findChild<QLineEdit *>("topUpKgField"), "20");
    window.findChild<QPushButton *>("topUpButton")->click();
    QCOMPARE(Cards::findById(db, card).balanceX100, qlonglong(3000));
    QCOMPARE(window.findChild<QLabel *>("cardBalance")->text(), QString("30.00"));

    // a card that nobody registered goes to the registration; a lost one says so
    window.showPage("spot");
    reader->feed("99FF00AA\r\n");
    QCOMPARE(window.currentPage(), QString("cards"));
    QCOMPARE(window.findChild<QLineEdit *>("cardIdField")->text(), QString("99FF00AA"));
    QCOMPARE(window.findChild<QStackedWidget *>("cardPanels")->currentIndex(), 1);
    QVERIFY(!window.findChild<QPushButton *>("cardHistoryButton")->isEnabled());
    window.showPage("spot");
    reader->feed("DEAD0001\r\n");
    QCOMPARE(window.currentPage(), QString("cards"));
    QVERIFY(window.findChild<QLabel *>("cardMessage")->text().contains("blocked"));
}

void Smart210Test::aCardReadBeforeTheLoginOrOnTheCardsScreenKeepsTheScreen()
{
    setUpLoggedInTerminalWithAPin();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    makeCard(db, "04A1B2C3", 0);
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", db);
    CardReader *reader = window.findChild<CardReader *>();

    // on the Cards screen the reader repeats the id while the card lies on it: what the cashier typed stays
    window.showPage("cards");
    reader->feed("04A1B2C3\r\n");
    QTest::keyClicks(window.findChild<QLineEdit *>("topUpKgField"), "12");
    reader->feed("04A1B2C3\r\n");
    QCOMPARE(window.currentPage(), QString("cards"));
    QCOMPARE(window.findChild<QLineEdit *>("topUpKgField")->text(), QString("12"));

    window.showPage("settings");
    window.findChild<QPushButton *>("logoutButton")->click();
    QCOMPARE(window.currentPage(), QString("login"));
    reader->feed("04A1B2C3\r\n");
    QCOMPARE(window.currentPage(), QString("login"));               // nobody is logged in: nothing opens
}

void Smart210Test::theCardHistoryDialogListsTheLedgerOfTheCardWithItsBalances()
{
    setUpLoggedInTerminalWithAPin();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    const QString card = makeCard(db, "04A1B2C3", 0);
    const QString other = makeCard(db, "04A1B2C4", 0);
    const QString entry = "INSERT INTO card_transactions (id, card_id, type, kg, balance_kg, price, total_amount, source_id, occurred_at) VALUES ";
    run(entry + "('e1', '" + card + "', 'topup', 10000, 10000, 750, 75000, 's1', '2026-10-01 09:00:00')");
    run(entry + "('e2', '" + card + "', 'fill', -3050, 6950, NULL, NULL, 'f1', '2026-10-02 10:00:00')");
    run(entry + "('e3', '" + card + "', 'topup', 5000, 11950, 800, 40000, 's2', '2026-10-03 11:00:00')");
    run(entry + "('e4', '" + card + "', 'reversal', -5000, 6950, NULL, NULL, 's2', '2026-10-04 12:00:00')");
    run(entry + "('x1', '" + other + "', 'topup', 999, 999, 750, 7492, 's9', '2026-10-05 12:00:00')");            // another card's

    // made on the terminal and not in the ledger yet: shown after it, marked, with a balance that goes on from the last entry
    run("INSERT INTO supplements (id, fuel_type, nozzle_id, out_amount_lt, out_amount_kg, preset_kg, mode, supplied_at, synced, card_id) "
        "VALUES ('f2', 'D', 10, 1200, 1000, 1000, 'auto', '2026-10-06 09:30:00', 0, '" + card + "')");
    run("INSERT INTO supplements (id, fuel_type, nozzle_id, out_amount_lt, out_amount_kg, preset_kg, mode, supplied_at, synced, card_id) "
        "VALUES ('f1', 'D', 10, 360, 305, 305, 'auto', '2026-10-02 10:00:00', 1, '" + card + "')");                // already in the ledger (e2): not twice

    CardHistoryDialog dialog(db, card, "Li Wei");
    QTableWidget *table = dialog.findChild<QTableWidget *>("historyTable");
    QVERIFY(table != 0);
    QCOMPARE(table->rowCount(), 5);                                         // the five of this card: four of the ledger and the new fill
    QCOMPARE(table->item(0, 0)->text(), QString("2026-10-06 09:30:00"));   // newest first
    QCOMPARE(table->item(0, 1)->text(), QString("Fill (not confirmed yet)"));
    QCOMPARE(table->item(0, 2)->text(), QString("-10.00"));
    QCOMPARE(table->item(0, 3)->text(), QString("59.50"));                  // 69.50 (the last entry of the ledger) less 10.00
    QCOMPARE(table->item(1, 1)->text(), QString("Reversal (deleted)"));
    QCOMPARE(table->item(1, 2)->text(), QString("-50.00"));
    QCOMPARE(table->item(1, 3)->text(), QString("69.50"));
    QCOMPARE(table->item(2, 1)->text(), QString("Top-up"));
    QCOMPARE(table->item(2, 2)->text(), QString("+50.00"));
    QCOMPARE(table->item(2, 4)->text(), QString("8.00"));                   // price per kg
    QCOMPARE(table->item(2, 5)->text(), QString("400.00"));                 // paid
    QCOMPARE(table->item(3, 1)->text(), QString("Fill"));
    QCOMPARE(table->item(3, 4)->text(), QString());                         // no price for a fill
    QCOMPARE(table->item(4, 3)->text(), QString("100.00"));
    QCOMPARE(dialog.findChild<QLabel *>("historyBalance")->text(), QString("Balance: 59.50 kg"));

    CardHistoryDialog empty(db, "no-such-card", "Nobody");
    QCOMPARE(empty.findChild<QTableWidget *>("historyTable")->rowCount(), 0);
    QCOMPARE(empty.findChild<QLabel *>("historyBalance")->text(), QString("No entries yet."));
}

void Smart210Test::theLedgerOfACardIsDownloadedAndKeptAsTheWebAppHasIt()
{
    configureServer();
    serveEverything();
    SyncClient client(QSqlDatabase::database(connection_));
    QVERIFY(downloadOk(client));

    QCOMPARE(countRows("card_transactions"), 1);
    QCOMPARE(one("SELECT kg FROM card_transactions").toInt(), 4000);
    QCOMPARE(one("SELECT balance_kg FROM card_transactions").toInt(), 4000);
    QCOMPARE(one("SELECT price FROM card_transactions").toInt(), 750);
    QCOMPARE(one("SELECT type FROM card_transactions").toString(), QString("topup"));
    QCOMPARE(one("SELECT seq FROM card_transactions").toInt(), 7);

    // the web app works the balances out again (a fill that came in late): the entry comes again with its new balance and replaces the old
    server_->reply("/api/pull/card_transactions?limit=200", 200, q("{'status':'ok','more':false,'server_time':'2026-10-07 10:00:00','rows':["
                   "{'id':'44444444-4444-4444-4444-444444444444','card_id':'22222222-2222-2222-2222-222222222222','type':'topup','kg':'40.00','balance_kg':'25.00',"
                   "'price':'7.50','total_amount':'300.00','source_id':'55555555-5555-5555-5555-555555555555','occurred_at':'2026-10-06 09:00:00','comment':null},"
                   "{'id':'66666666-6666-6666-6666-666666666666','card_id':'22222222-2222-2222-2222-222222222222','type':'fill','kg':'-15.00','balance_kg':'-15.00',"
                   "'price':null,'total_amount':null,'source_id':'77777777-7777-7777-7777-777777777777','occurred_at':'2026-10-05 09:00:00','comment':null}]}"));
    QVERIFY(downloadOk(client));
    QCOMPARE(countRows("card_transactions"), 2);
    QCOMPARE(one("SELECT balance_kg FROM card_transactions WHERE type = 'topup'").toInt(), 2500);
    QCOMPARE(one("SELECT kg FROM card_transactions WHERE type = 'fill'").toInt(), -1500);
}

void Smart210Test::aRemainderGetsACommentOnTheRemaindersScreenAndItGoesUpWithTheNextUpload()
{
    setUpLoggedInTerminalWithAPin();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    run("INSERT OR IGNORE INTO tanks (id, name, fuel_type, capacity_kg, stock_kg) VALUES (1, 'T1', 'D', 5000000, 100000)");
    run("INSERT OR IGNORE INTO nozzles (id, number, tank_id, fuel_type, board_address, synced, deleted) VALUES (2, 1, 1, 'D', 1, 1, 0)");
    run("INSERT INTO supplements (id, fuel_type, nozzle_id, out_amount_lt, out_amount_kg, preset_kg, supplied_at, supply_date, synced) "
        "VALUES ('sup-1', 'D', 2, 1265, 1050, 1050, '2026-10-07 09:05:00', '2026-10-07', 1)");
    run("INSERT INTO remainders (id, supplement_id, ticket_barcode, fuel_type, amount_kg, created_at, remainder_date, printed_at, synced) "
        "VALUES ('rem-1', 'sup-1', 21500000001, 'D', 250, '2026-10-07 09:06:00', '2026-10-07', '2026-10-07 09:07:00', 1)");
    run("INSERT INTO tickets (barcode, fuel_type, amount_kg, status, synced) VALUES (21500000001, 'D', 250, 1, 1)");

    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", db);
    window.showPage("remainders");
    QTableWidget *table = window.findChild<QTableWidget *>("remainderTable");
    QLineEdit *comment = window.findChild<QLineEdit *>("remainderCommentField");
    QPushButton *save = window.findChild<QPushButton *>("remainderSaveCommentButton");
    QLabel *status = window.findChild<QLabel *>("remainderStatus");
    QVERIFY(table != 0 && comment != 0 && save != 0);
    QCOMPARE(table->horizontalHeaderItem(6)->text(), QString("Comment"));
    QVERIFY(!save->isEnabled());                                          // nothing is chosen

    table->selectRow(0);
    QVERIFY(save->isEnabled());
    QVERIFY(comment->text().isEmpty());
    QCOMPARE(footerWaiting(window), QString("Everything is uploaded"));
    QTest::keyClicks(comment, " left for Mr Li ");
    save->click();

    QVERIFY(status->text().contains("saved"));
    QCOMPARE(one("SELECT comment FROM remainders").toString(), QString("left for Mr Li"));      // trimmed
    QCOMPARE(one("SELECT synced FROM remainders").toInt(), 0);                                  // it goes up again
    QCOMPARE(footerWaiting(window), QString("Waiting for upload: 1"));
    QCOMPARE(table->item(0, 6)->text(), QString("left for Mr Li"));
    QCOMPARE(comment->text(), QString("left for Mr Li"));                                       // the chosen row stays chosen

    // the same text again changes nothing; an empty one takes the comment away
    save->click();
    QVERIFY(status->text().contains("nothing changed"));
    run("UPDATE remainders SET synced = 1");
    comment->clear();
    save->click();
    QVERIFY(one("SELECT comment FROM remainders").isNull());
    QCOMPARE(one("SELECT synced FROM remainders").toInt(), 0);

    // the comment is in the upload
    QCOMPARE(Uploader::pending(db).value("remainders"), 1);
    run("UPDATE remainders SET comment = 'for the cashier of the next shift'");
    server_->reply("/api/push/remainders", 200, q("{'status':'ok','saved':['rem-1'],'rejected':[]}"));
    server_->reply("/api/push/nozzles", 200, q("{'status':'ok','saved':['1'],'rejected':[]}"));
    configureServer();
    Uploader uploader(db);
    QString message;
    QVERIFY(uploadOk(uploader, &message));
    QJsonObject row;
    for (int i = 0; i < server_->requests().size(); ++i) {
        if (server_->requests().at(i).path == "/api/push/remainders") {
            row = QJsonDocument::fromJson(server_->requests().at(i).body).object().value("rows").toArray().at(0).toObject();
        }
    }
    QCOMPARE(row.value("comment").toString(), QString("for the cashier of the next shift"));
}

void Smart210Test::theDispensersScreenShowsTheNozzlesInDetail()
{
    setUpLoggedInTerminalWithAPin();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    MachineConfig nozzles(db);
    storeNozzles(&nozzles, connection_);                                                 // nozzles 1, 2 and 4 auto, 3 manual, all Diesel at 0.8300
    run("INSERT INTO tickets (barcode, fuel_type, spec, amount_kg, status, synced) VALUES (3000000041, 'D', 0, 1500, 1, 1)");
    QVERIFY(SupplyQueue::add(db, 1, code(3000000041), "D", QDateTime::currentDateTime()).ok);   // 15.00 kg queued on nozzle 1
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", db);

    window.showPage("dispensers");
    QTableWidget *table = window.findChild<QTableWidget *>("dispensersTable");
    QStringList headers;
    for (int i = 0; i < table->columnCount(); ++i) {
        headers << table->horizontalHeaderItem(i)->text();
    }
    QCOMPARE(headers, QStringList() << "Nozzle" << "Mode" << "Fuel type" << "Density" << "Preset (kg)" << "Dispensed (kg)" << "Status");   // no pulses per litre
    QCOMPARE(table->item(0, 0)->text(), QString("1"));
    QCOMPARE(table->item(0, 1)->text(), QString("Auto"));
    QCOMPARE(table->item(0, 2)->text(), QString("D"));
    QCOMPARE(table->item(0, 3)->text(), QString("0.8300"));
    QCOMPARE(table->item(0, 4)->text(), QString("15.00"));                  // the tickets waiting are the preset
    QCOMPARE(table->item(2, 1)->text(), QString("Manual"));
    QCOMPARE(table->item(2, 4)->text(), QString());                         // a manual nozzle's amount is typed at the dispenser
    QCOMPARE(table->item(0, 6)->text(), QString("No converter"));
}

// Selects the row of the table that has a cell with this text.
static bool selectRowWithCell(QTableWidget *table, const QString &text)
{
    for (int row = 0; row < table->rowCount(); ++row) {
        for (int column = 0; column < table->columnCount(); ++column) {
            if (table->item(row, column) && table->item(row, column)->text() == text) {
                table->selectRow(row);
                table->setCurrentCell(row, 0);

                return true;
            }
        }
    }

    return false;
}

void Smart210Test::theReviewScreenDeletesAMistakenSpotSaleAfterAConfirmation()
{
    setUpSellingTerminal();
    addStockTickets();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    addUser(8, "Cash Two", "other");
    SpotSale::create(db, spotInput(1050), 7, QDateTime(QDate(2026, 10, 7), QTime(9, 0, 0)));  // A: the mistake, 10.50 kg
    SpotSale::create(db, spotInput(1000), 7, QDateTime(QDate(2026, 10, 7), QTime(9, 1, 0)));  // B: the correct one
    TicketSale::create(db, ticketInput(QList<qlonglong>() << 3000000011LL), 7, QDateTime(QDate(2026, 10, 7), QTime(9, 2, 0))); // C: a ticket sale
    SpotSale::create(db, spotInput(700), 8, QDateTime(QDate(2026, 10, 7), QTime(9, 3, 0)));   // D: another cashier's
    AppConfig config;
    config.setBoardLink(false);
    config.setChangeLogFile(changeLog());
    MainWindow window(config, QString(), "db", db);

    window.showPage("review");
    window.findChild<QDateEdit *>("reviewDate")->setDate(QDate(2026, 10, 7));
    QTableWidget *sales = window.findChild<QTableWidget *>("reviewSalesTable");
    QPushButton *del = window.findChild<QPushButton *>("deleteSaleButton");
    QLabel *status = window.findChild<QLabel *>("reviewStatus");
    QCOMPARE(sales->rowCount(), 4);
    QVERIFY(!del->isEnabled());                                          // nothing is selected

    QVERIFY(selectRowWithCell(sales, "7.00"));                           // another cashier's sale
    QVERIFY(!del->isEnabled());
    QVERIFY(status->text().contains("another cashier"));
    QVERIFY(selectRowWithCell(sales, "Ticket / Sale"));                         // a ticket sale
    QVERIFY(!del->isEnabled());
    QVERIFY(status->text().contains("Only a spot sale"));

    QVERIFY(selectRowWithCell(sales, "10.50"));                          // the mistake
    QVERIFY(del->isEnabled());
    QCOMPARE(del->text(), QString("Delete sale"));
    del->click();
    QCOMPARE(del->text(), QString("Confirm delete"));                    // the first press only asks
    QCOMPARE(sales->rowCount(), 4);

    QVERIFY(selectRowWithCell(sales, "10.00"));                          // choosing another sale takes the question back
    QCOMPARE(del->text(), QString("Delete sale"));

    QVERIFY(selectRowWithCell(sales, "10.50"));
    del->click();
    del->click();                                                        // confirmed

    QCOMPARE(sales->rowCount(), 3);
    QVERIFY(status->text().contains("deleted"));
    QVERIFY(!del->isEnabled());
    QCOMPARE(one("SELECT COUNT(*) FROM sales WHERE amount_kg = 1050 AND deleted_at IS NOT NULL").toInt(), 1);
    QCOMPARE(Uploader::pending(db).value("sales"), 3);                   // the mistake never goes up
}


void Smart210Test::theSelectKeyOnlyTellsAndTheSelectCommandChoosesTheNozzleAndLetsTheOthersGo()
{
    QSqlDatabase db = openDatabase();
    MachineConfig config(db);
    storeNozzles(&config, connection_);
    FakeBoards boards;
    boards.add(1);
    boards.add(2);
    boards.add(4);
    BoardBus bus;
    quickBus(&bus, &boards);
    bus.addBoard(1);
    bus.addBoard(2);
    bus.addBoard(4);
    SupplySelection selection(&bus, &config);
    QVERIFY(startBus(&bus, &boards));
    QTRY_VERIFY_WITH_TIMEOUT(bus.isOnline(1) && bus.isOnline(2) && bus.isOnline(4), 3000);

    // the key of board 2: it only tells (SELECT_KEY); the Smart210 chooses the nozzle (its own state) and sends SELECT: the board beeps, nothing else
    boards.pressSelect(2);
    QCOMPARE(boards.board(2).state, int(BoardProtocol::StateIdle));
    QTRY_COMPARE_WITH_TIMEOUT(selection.current(), 2, 3000);
    QTRY_COMPARE_WITH_TIMEOUT(boards.board(2).beeps, 1, 3000);
    QVERIFY(boards.board(2).commands.contains(BoardProtocol::CmdSelect));    // the Smart210 sent SELECT to board 2
    QCOMPARE(boards.board(1).beeps, 0);
    QCOMPARE(boards.board(4).beeps, 0);
    QTest::qWait(300);
    QCOMPARE(bus.lastStatus(2).state, int(BoardProtocol::StateIdle));       // the next poll still says IDLE: the board has no "selected" state

    // the key of board 4: the choice moves to 4; board 2 is simply not the chosen one any more, and no board is told
    boards.pressSelect(4);
    QTRY_COMPARE_WITH_TIMEOUT(selection.current(), 4, 3000);
    QTRY_COMPARE_WITH_TIMEOUT(boards.board(4).beeps, 1, 3000);
    QCOMPARE(boards.board(2).beeps, 1);
    QVERIFY(!boards.board(2).commands.contains(BoardProtocol::CmdSetPreset));
}

void Smart210Test::aNozzleThatLosesTheChoiceBeforeItsFillGivesItsPresetBack()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    SupplyBench bench(db);
    QVERIFY(bench.session.login(7, "secret1"));
    QVERIFY(startBus(&bench.bus, &bench.boards));
    QTRY_VERIFY_WITH_TIMEOUT(bench.bus.isOnline(1) && bench.bus.isOnline(2), 3000);

    bench.boards.simulator().pressSelect(1);
    QTRY_COMPARE_WITH_TIMEOUT(bench.selection.current(), 1, 3000);
    bench.supply.scanned(code(3000000041));                               // 15.00 kg
    QTRY_COMPARE_WITH_TIMEOUT(bench.boards.board(1).state, int(BoardProtocol::StateArmed), 3000);
    QCOMPARE(bench.boards.board(1).presetG, quint32(15000));

    // the supplier chooses nozzle 2 before taking the gun up at nozzle 1: nozzle 1 gives its preset back and is IDLE again
    bench.boards.simulator().pressSelect(2);
    QTRY_COMPARE_WITH_TIMEOUT(bench.selection.current(), 2, 3000);
    QTRY_COMPARE_WITH_TIMEOUT(bench.boards.board(1).state, int(BoardProtocol::StateIdle), 3000);
    QCOMPARE(bench.boards.board(1).presetG, quint32(0));
    QTest::qWait(500);
    QCOMPARE(bench.boards.board(1).state, int(BoardProtocol::StateIdle));  // and it is not set again while it is not the chosen one
    QCOMPARE(SupplyQueue::entries(db, 1).size(), 1);                       // its tickets stay in the queue
    QVERIFY(!bench.supply.isLocked(1));

    // chosen again: the preset of its queue is set again
    bench.boards.simulator().pressSelect(1);
    QTRY_COMPARE_WITH_TIMEOUT(bench.selection.current(), 1, 3000);
    QTRY_COMPARE_WITH_TIMEOUT(bench.boards.board(1).state, int(BoardProtocol::StateArmed), 3000);
    QCOMPARE(bench.boards.board(1).presetG, quint32(15000));

    // a fill that has started keeps its preset when the choice moves
    QVERIFY(bench.boards.simulator().liftGun(1));
    QTRY_VERIFY_WITH_TIMEOUT(bench.supply.isLocked(1), 3000);
    bench.boards.simulator().pressSelect(2);
    QTRY_COMPARE_WITH_TIMEOUT(bench.selection.current(), 2, 3000);
    QTest::qWait(400);
    QCOMPARE(bench.boards.board(1).state, int(BoardProtocol::StateDispensing));
    QCOMPARE(bench.boards.board(1).presetG, quint32(15000));
}

void Smart210Test::theGunUpIsReportedAndTheSmart210StartsOnlyWhenTicketsAreQueued()
{
    setUpSupplying();
    QSqlDatabase db = QSqlDatabase::database(connection_);
    SupplyBench bench(db);
    QVERIFY(bench.session.login(7, "secret1"));
    QVERIFY(startBus(&bench.bus, &bench.boards));
    QTRY_VERIFY_WITH_TIMEOUT(bench.bus.isOnline(1), 3000);

    // tickets queued, the preset is on the board: the gun goes up, the board only reports it, the Smart210 sends START
    bench.boards.simulator().pressSelect(1);
    QTRY_COMPARE_WITH_TIMEOUT(bench.selection.current(), 1, 3000);
    bench.supply.scanned(code(3000000041));
    QTRY_COMPARE_WITH_TIMEOUT(bench.boards.board(1).state, int(BoardProtocol::StateArmed), 3000);
    QTest::qWait(300);
    QCOMPARE(bench.boards.board(1).commands.count(BoardProtocol::CmdStart), 0);      // nothing starts by itself
    QVERIFY(bench.boards.simulator().liftGun(1));
    QTRY_COMPARE_WITH_TIMEOUT(bench.boards.board(1).state, int(BoardProtocol::StateDispensing), 3000);
    QCOMPARE(bench.boards.board(1).commands.count(BoardProtocol::CmdStart), 1);
    QVERIFY(bench.supply.isLocked(1));
    bench.boards.simulator().tick(80000);
    QTRY_VERIFY_WITH_TIMEOUT(!bench.supply.isLocked(1), 5000);                       // the fill ended and was recorded (the gun goes back)
    QTRY_COMPARE_WITH_TIMEOUT(bench.boards.board(1).state, int(BoardProtocol::StateIdle), 3000);

    // the queue was cleared with the clear key, then the gun is up: no START, and the preset is set to zero
    QCOMPARE(bench.selection.current(), 1);                                          // the nozzle is still the chosen one
    bench.supply.scanned(code(3000000042));
    QTRY_COMPARE_WITH_TIMEOUT(bench.boards.board(1).presetG, quint32(20000), 3000);
    bench.boards.simulator().pressClearKey(1);
    QTRY_COMPARE_WITH_TIMEOUT(SupplyQueue::entries(db, 1).size(), 0, 3000);
    QVERIFY(bench.boards.simulator().liftGun(1));
    QTest::qWait(600);
    QCOMPARE(bench.boards.board(1).commands.count(BoardProtocol::CmdStart), 1);      // still only the first fill's START
    QCOMPARE(bench.boards.board(1).state, int(BoardProtocol::StateIdle));
    QCOMPARE(bench.boards.board(1).presetG, quint32(0));
    QVERIFY(!bench.supply.isLocked(1));
}

QTEST_MAIN(Smart210Test)
#include "tst_smart210.moc"
