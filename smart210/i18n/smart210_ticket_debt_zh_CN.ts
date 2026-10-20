<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE TS>
<TS version="2.1" language="zh_CN">
<context>
    <name>LoanSettleDialog</name>
    <message>
        <location filename="../ui/LoanSettleDialog.ui" line="14"/>
        <source>Settle a ticket debt</source>
        <translation>结算油票欠账</translation>
    </message>
    <message>
        <location filename="../ui/LoanSettleDialog.ui" line="25"/>
        <source>Scan a ticket, or type its 16 digits and press Enter</source>
        <translation>扫描油票，或输入 16 位数字后按回车</translation>
    </message>
    <message>
        <location filename="../ui/LoanSettleDialog.ui" line="42"/>
        <source>Remove selected</source>
        <translation>删除所选</translation>
    </message>
    <message>
        <location filename="../ui/LoanSettleDialog.ui" line="48"/>
        <source>Clear list</source>
        <translation>清空列表</translation>
    </message>
    <message>
        <location filename="../ui/LoanSettleDialog.ui" line="73"/>
        <source>Settle</source>
        <translation>确认还票</translation>
    </message>
    <message>
        <location filename="../ui/LoanSettleDialog.ui" line="79"/>
        <source>Close</source>
        <translation>关闭</translation>
    </message>
    <message>
        <location filename="../src/LoanSettleDialog.cpp" line="23"/>
        <source>%1: %2 kg of %3 are owed. Scan the tickets the customer brings back.</source>
        <translation>%1：欠 %3 %2 千克。请扫描客户退回的油票。</translation>
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
        <translation>%1 张油票，%2 千克，结算后余额：%3 千克</translation>
    </message>
    <message>
        <location filename="../src/LoanSettleDialog.cpp" line="74"/>
        <source>This ticket is already in the list.</source>
        <translation>这张油票已在列表中。</translation>
    </message>
    <message>
        <location filename="../src/LoanSettleDialog.cpp" line="139"/>
        <source>Settled %1 tickets, %2 kg. Still owed: %3 kg</source>
        <translation>已结算 %1 张油票，%2 千克。仍欠：%3 千克</translation>
    </message>
    <message>
        <location filename="../src/LoanSettleDialog.cpp" line="140"/>
        <source>Settled %1 tickets, %2 kg. The loan is paid off.</source>
        <translation>已结算 %1 张油票，%2 千克。欠账已还清。</translation>
    </message>
</context>
<context>
    <name>LoanSettlePage</name>
    <message>
        <location filename="../ui/LoanSettlePage.ui" line="13"/>
        <source>Ticket Debt</source>
        <translation>提货票欠款</translation>
    </message>
    <message>
        <location filename="../ui/LoanSettlePage.ui" line="18"/>
        <source>Choose a debt and press Settle to surrender the tickets the customer brings back.</source>
        <translation>选择一笔欠账，按“结算”交回客户退回的油票。</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlePage.cpp" line="22"/>
        <source>Customer</source>
        <translation>客户</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlePage.cpp" line="22"/>
        <source>Fuel</source>
        <translation>油品</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlePage.cpp" line="22"/>
        <source>Loaned at</source>
        <translation>借出时间</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlePage.cpp" line="22"/>
        <source>Loaned kg</source>
        <translation>借出公斤</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlePage.cpp" line="22"/>
        <source>Settled kg</source>
        <translation>已还公斤</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlePage.cpp" line="22"/>
        <source>Balance kg</source>
        <translation>未还公斤</translation>
    </message>
    <message>
        <location filename="../ui/LoanSettlePage.ui" line="39"/>
        <source>Settle</source>
        <translation>确认还票</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlePage.cpp" line="76"/>
        <source>There are no open ticket loans.</source>
        <translation>没有未还清的借票。</translation>
    </message>
</context>
<context>
    <name>QObject</name>
    <message>
        <location filename="../src/LoanSettlement.cpp" line="105"/>
        <source>Log in first.</source>
        <translation>请先登录。</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlement.cpp" line="76"/>
        <source>This ticket was already used.</source>
        <translation>这张油票已经使用。</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlement.cpp" line="54"/>
        <source>That is not a ticket barcode (16 digits).</source>
        <translation>这不是油票条码（16 位数字）。</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlement.cpp" line="63"/>
        <source>This ticket is not known on this terminal.</source>
        <translation>本终端没有这张油票。</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlement.cpp" line="72"/>
        <source>This ticket was never sold, so it cannot settle a loan.</source>
        <translation>这张油票从未售出，不能用来还票。</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlement.cpp" line="80"/>
        <source>This ticket is restricted and cannot be used.</source>
        <translation>这张油票已被限制，不能使用。</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlement.cpp" line="85"/>
        <source>This ticket is another fuel type than the loan.</source>
        <translation>这张油票与借票的油品不同。</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlement.cpp" line="91"/>
        <source>This ticket is in the queue of nozzle %1.</source>
        <translation>这张油票在油枪 %1 的队列中。</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlement.cpp" line="120"/>
        <source>Choose the loan first.</source>
        <translation>请先选择借票记录。</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlement.cpp" line="134"/>
        <source>Ticket %1 cannot settle the loan: %2</source>
        <translation>油票 %1 不能用来还票：%2</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlement.cpp" line="182"/>
        <source>The settlement could not be saved.</source>
        <translation>还票无法保存。</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlement.cpp" line="108"/>
        <source>Scan the tickets first.</source>
        <translation>请先扫描油票。</translation>
    </message>
    <message>
        <location filename="../src/LoanSettlement.cpp" line="135"/>
        <source>it is twice in the list.</source>
        <translation>它在列表中出现了两次。</translation>
    </message>
</context>
</TS>
