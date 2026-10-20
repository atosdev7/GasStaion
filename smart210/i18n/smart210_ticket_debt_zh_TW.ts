<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE TS>
<TS version="2.1" language="zh_TW">
<context>
    <name>LoanSettleDialog</name>
    <message>
        <location filename="../ui/LoanSettleDialog.ui" line="14"/>
        <source>Settle a ticket debt</source>
        <translation>結算油票欠帳</translation>
    </message>
    <message>
        <location filename="../ui/LoanSettleDialog.ui" line="25"/>
        <source>Scan a ticket, or type its 16 digits and press Enter</source>
        <translation>掃描油票，或輸入 16 位數字後按 Enter</translation>
    </message>
    <message>
        <location filename="../ui/LoanSettleDialog.ui" line="42"/>
        <source>Remove selected</source>
        <translation>刪除所選</translation>
    </message>
    <message>
        <location filename="../ui/LoanSettleDialog.ui" line="48"/>
        <source>Clear list</source>
        <translation>清空列表</translation>
    </message>
    <message>
        <location filename="../ui/LoanSettleDialog.ui" line="73"/>
        <source>Settle</source>
        <translation>確認還票</translation>
    </message>
    <message>
        <location filename="../ui/LoanSettleDialog.ui" line="79"/>
        <source>Close</source>
        <translation>關閉</translation>
    </message>
    <message>
        <location filename="../src/LoanSettleDialog.cpp" line="23"/>
        <source>%1: %2 kg of %3 are owed. Scan the tickets the customer brings back.</source>
        <translation>%1：欠 %3 %2 公斤。請掃描客戶退回的油票。</translation>
    </message>
    <message>
        <location filename="../src/LoanSettleDialog.cpp" line="27"/>
        <source>Ticket</source>
        <translation>油票</translation>
    </message>
    <message>
        <location filename="../src/LoanSettleDialog.cpp" line="27"/>
        <source>kg</source>
        <translation>公斤</translation>
    </message>
    <message>
        <location filename="../src/LoanSettleDialog.cpp" line="59"/>
        <source>%1 tickets, %2 kg, balance after: %3 kg</source>
        <translation>%1 張油票，%2 公斤，結算後餘額：%3 公斤</translation>
    </message>
    <message>
        <location filename="../src/LoanSettleDialog.cpp" line="74"/>
        <source>This ticket is already in the list.</source>
        <translation>這張油票已在清單中。</translation>
    </message>
    <message>
        <location filename="../src/LoanSettleDialog.cpp" line="139"/>
        <source>Settled %1 tickets, %2 kg. Still owed: %3 kg</source>
        <translation>已結算 %1 張油票，%2 公斤。仍欠：%3 公斤</translation>
    </message>
    <message>
        <location filename="../src/LoanSettleDialog.cpp" line="140"/>
        <source>Settled %1 tickets, %2 kg. The loan is paid off.</source>
        <translation>已結算 %1 張油票，%2 公斤。欠帳已還清。</translation>
    </message>
</context>
<context>
    <name>LoanSettlePage</name>
    <message>
        <location filename="../ui/LoanSettlePage.ui" line="13"/>
        <source>Ticket Debt</source>
        <translation>提貨票欠款</translation>
    </message>
    <message>
        <location filename="../ui/LoanSettlePage.ui" line="18"/>
        <source>Choose a debt and press Settle to surrender the tickets the customer brings back.</source>
        <translation>選擇一筆欠帳，按「結算」交回客戶退回的油票。</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlePage.cpp" line="22"/>
        <source>Customer</source>
        <translation>客戶</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlePage.cpp" line="22"/>
        <source>Fuel</source>
        <translation>油品</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlePage.cpp" line="22"/>
        <source>Loaned at</source>
        <translation>借出時間</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlePage.cpp" line="22"/>
        <source>Loaned kg</source>
        <translation>借出公斤</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlePage.cpp" line="22"/>
        <source>Settled kg</source>
        <translation>已還公斤</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlePage.cpp" line="22"/>
        <source>Balance kg</source>
        <translation>未還公斤</translation>
    </message>
    <message>
        <location filename="../ui/LoanSettlePage.ui" line="39"/>
        <source>Settle</source>
        <translation>確認還票</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlePage.cpp" line="76"/>
        <source>There are no open ticket loans.</source>
        <translation>沒有未還清的借票。</translation>
    </message>
</context>
<context>
    <name>QObject</name>
    <message>
        <location filename="../src/LoanSettlement.cpp" line="105"/>
        <source>Log in first.</source>
        <translation>請先登入。</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlement.cpp" line="76"/>
        <source>This ticket was already used.</source>
        <translation>這張油票已經使用。</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlement.cpp" line="54"/>
        <source>That is not a ticket barcode (16 digits).</source>
        <translation>這不是油票條碼（16 位數字）。</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlement.cpp" line="63"/>
        <source>This ticket is not known on this terminal.</source>
        <translation>本終端沒有這張油票。</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlement.cpp" line="72"/>
        <source>This ticket was never sold, so it cannot settle a loan.</source>
        <translation>這張油票從未售出，不能用來還票。</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlement.cpp" line="80"/>
        <source>This ticket is restricted and cannot be used.</source>
        <translation>這張油票已被限制，不能使用。</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlement.cpp" line="85"/>
        <source>This ticket is another fuel type than the loan.</source>
        <translation>這張油票與借票的油品不同。</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlement.cpp" line="91"/>
        <source>This ticket is in the queue of nozzle %1.</source>
        <translation>這張油票在油槍 %1 的佇列中。</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlement.cpp" line="120"/>
        <source>Choose the loan first.</source>
        <translation>請先選擇借票記錄。</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlement.cpp" line="134"/>
        <source>Ticket %1 cannot settle the loan: %2</source>
        <translation>油票 %1 不能用來還票：%2</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlement.cpp" line="182"/>
        <source>The settlement could not be saved.</source>
        <translation>還票無法儲存。</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlement.cpp" line="108"/>
        <source>Scan the tickets first.</source>
        <translation>請先掃描油票。</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlement.cpp" line="135"/>
        <source>it is twice in the list.</source>
        <translation>它在列表中出現了兩次。</translation>
    </message>
</context>
</TS>
