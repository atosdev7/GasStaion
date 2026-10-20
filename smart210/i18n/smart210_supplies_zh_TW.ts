<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE TS>
<TS version="2.1" language="zh_TW">
<context>
    <name>ManualSupplyPage</name>
    <message>
        <location filename="../ui/ManualSupplyPage.ui" line="13"/>
        <source>Manual input</source>
        <translation>手動輸入</translation>
    </message>
    <message>
        <location filename="../ui/ManualSupplyPage.ui" line="19"/>
        <source>A manual-mode nozzle is never recorded by the program: type its supply in here, with the tickets that paid for it.</source>
        <translation>手動模式的油槍程式不會自動記錄：請在此輸入其供油情況和對應的油票。</translation>
    </message>
    <message>
        <location filename="../ui/ManualSupplyPage.ui" line="31"/>
        <source>Nozzle</source>
        <translation>油槍</translation>
    </message>
    <message>
        <location filename="../ui/ManualSupplyPage.ui" line="36"/>
        <source>Date and time</source>
        <translation>日期和時間</translation>
    </message>
    <message>
        <location filename="../ui/ManualSupplyPage.ui" line="41"/>
        <source>Density (kg/L)</source>
        <translation>密度（公斤/公升）</translation>
    </message>
    <message>
        <location filename="../ui/ManualSupplyPage.ui" line="64"/>
        <source>Preset (kg)</source>
        <translation>預置（公斤）</translation>
    </message>
    <message>
        <location filename="../ui/ManualSupplyPage.ui" line="69"/>
        <source>Dispensed (kg)</source>
        <translation>已加油量（公斤）</translation>
    </message>
    <message>
        <location filename="../ui/ManualSupplyPage.ui" line="74"/>
        <source>Litres</source>
        <translation>公升</translation>
    </message>
    <message>
        <location filename="../ui/ManualSupplyPage.ui" line="105"/>
        <source>Ticket barcode, then Enter</source>
        <translation>油票條碼，然後按 Enter</translation>
    </message>
    <message>
        <location filename="../ui/ManualSupplyPage.ui" line="112"/>
        <source>Add ticket</source>
        <translation>新增油票</translation>
    </message>
    <message>
        <location filename="../ui/ManualSupplyPage.ui" line="118"/>
        <source>Remove</source>
        <translation>刪除</translation>
    </message>
    <message>
        <location filename="../ui/ManualSupplyPage.ui" line="130"/>
        <source>Comment (optional)</source>
        <translation>備註（可選）</translation>
    </message>
    <message>
        <location filename="../ui/ManualSupplyPage.ui" line="147"/>
        <source>Save supply</source>
        <translation>儲存供油</translation>
    </message>
    <message>
        <location filename="../src/ManualSupplyPage.cpp" line="54"/>
        <source>Ticket</source>
        <translation>油票</translation>
    </message>
    <message>
        <location filename="../src/ManualSupplyPage.cpp" line="54"/>
        <source>kg</source>
        <translation>公斤</translation>
    </message>
    <message>
        <location filename="../src/ManualSupplyPage.cpp" line="109"/>
        <source>Nozzle %1 (%2)</source>
        <translation>%1 號油槍（%2）</translation>
    </message>
    <message>
        <location filename="../src/ManualSupplyPage.cpp" line="180"/>
        <source>Choose the nozzle first.</source>
        <translation>請先選中油槍。</translation>
    </message>
    <message>
        <location filename="../src/ManualSupplyPage.cpp" line="193"/>
        <source>This ticket is in the list already.</source>
        <translation>這張油票已在清單中。</translation>
    </message>
    <message>
        <location filename="../src/ManualSupplyPage.cpp" line="245"/>
        <source>No nozzle works in manual mode.</source>
        <translation>沒有手動模式的油槍。</translation>
    </message>
    <message>
        <location filename="../src/ManualSupplyPage.cpp" line="248"/>
        <source>Type the ticket barcodes, one after the other.</source>
        <translation>請逐張輸入油票條碼。</translation>
    </message>
    <message>
        <location filename="../src/ManualSupplyPage.cpp" line="248"/>
        <source>Type the kg and the litres that were dispensed.</source>
        <translation>請輸入已加油的公斤數和公升數。</translation>
    </message>
    <message>
        <location filename="../src/ManualSupplyPage.cpp" line="253"/>
        <source>Tickets: %1 kg, dispensed: %2 kg, difference: %3 kg (the tickets must cover it, at most %4 kg more)</source>
        <translation>油票：%1 公斤，已加油：%2 公斤，差：%3 公斤（油票必須足夠，最多可多 %4 公斤）</translation>
    </message>
    <message>
        <location filename="../src/ManualSupplyPage.cpp" line="265"/>
        <source>Type the weight and the litres as numbers, for example 15.00.</source>
        <translation>請以數字輸入重量和公升數，例如 15.00。</translation>
    </message>
    <message>
        <location filename="../src/ManualSupplyPage.cpp" line="280"/>
        <source>The supply was recorded with %1 tickets. It will be uploaded.</source>
        <translation>供油已記錄，含 %1 張油票。將會上傳。</translation>
    </message>
</context>
<context>
    <name>QObject</name>
    <message>
        <location filename="../src/SupplyQueue.cpp" line="38"/>
        <source>This ticket was already used.</source>
        <translation>這張油票已經使用。</translation>
    </message>
    <message>
        <location filename="../src/SupplyQueue.cpp" line="16"/>
        <source>That is not a ticket barcode (16 digits).</source>
        <translation>這不是油票條碼（16 位數字）。</translation>
    </message>
    <message>
        <location filename="../src/SupplyQueue.cpp" line="25"/>
        <source>This ticket is not known on this terminal.</source>
        <translation>本終端沒有這張油票。</translation>
    </message>
    <message>
        <location filename="../src/SupplyQueue.cpp" line="42"/>
        <source>This ticket is restricted and cannot be used.</source>
        <translation>這張油票已被限制，不能使用。</translation>
    </message>
    <message>
        <location filename="../src/SupplyRecord.cpp" line="137"/>
        <source>it is twice in the list.</source>
        <translation>它在列表中出現了兩次。</translation>
    </message>
    <message>
        <location filename="../src/SupplyQueue.cpp" line="34"/>
        <source>This ticket was never sold.</source>
        <translation>這張油票從未售出。</translation>
    </message>
    <message>
        <location filename="../src/SupplyQueue.cpp" line="47"/>
        <source>This ticket is another fuel type (%1) than the nozzle (%2).</source>
        <translation>這張油票的油品（%1）與油槍（%2）不同。</translation>
    </message>
    <message>
        <location filename="../src/SupplyQueue.cpp" line="54"/>
        <source>This ticket is already in the queue of nozzle %1.</source>
        <translation>這張油票已在油槍 %1 的佇列中。</translation>
    </message>
    <message>
        <location filename="../src/SupplyQueue.cpp" line="88"/>
        <source>The ticket could not be added.</source>
        <translation>無法新增這張油票。</translation>
    </message>
    <message>
        <location filename="../src/SupplyRecord.cpp" line="79"/>
        <source>Nozzle %1 is not known yet.</source>
        <translation>油槍 %1 還不存在。</translation>
    </message>
    <message>
        <location filename="../src/SupplyRecord.cpp" line="239"/>
        <source>The supply could not be saved.</source>
        <translation>無法儲存這次供油。</translation>
    </message>
    <message>
        <location filename="../src/SupplyRecord.cpp" line="137"/>
        <source>Ticket %1 cannot be used: %2</source>
        <translation>油票 %1 不能使用：%2</translation>
    </message>
    <message>
        <location filename="../src/SupplyRecord.cpp" line="149"/>
        <source>The tickets add up to %1 kg but %2 kg was dispensed: the tickets must cover everything that was dispensed.</source>
        <translation>油票合計 %1 公斤，但已加油 %2 公斤：油票必須涵蓋全部已加的油。</translation>
    </message>
    <message>
        <location filename="../src/SupplyRecord.cpp" line="151"/>
        <source>The tickets add up to %1 kg but only %2 kg was dispensed: they may be at most %3 kg more.</source>
        <translation>油票合計 %1 公斤，但只加了 %2 公斤：最多只能多 %3 公斤。</translation>
    </message>
</context>
<context>
    <name>SupplyPage</name>
    <message>
        <location filename="../src/SupplyPage.cpp" line="71"/>
        <source>Nozzle</source>
        <translation>油槍</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="71"/>
        <location filename="../src/SupplyPage.cpp" line="91"/>
        <source>Fuel type</source>
        <translation>油品</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="71"/>
        <source>Mode</source>
        <translation>模式</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="71"/>
        <source>Status</source>
        <translation>狀態</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="71"/>
        <source>Dispensed (kg)</source>
        <translation>已加油量（公斤）</translation>
    </message>
    <message>
        <location filename="../ui/SupplyPage.ui" line="13"/>
        <source>Supplies</source>
        <translation>供油</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="71"/>
        <source>Tickets (kg - count)</source>
        <translation>油票（公斤 - 張數）</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="91"/>
        <source>Barcode</source>
        <translation>條碼</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="91"/>
        <source>Amount (kg)</source>
        <translation>數量（公斤）</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="179"/>
        <source>No converter</source>
        <translation>轉換器未連線</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="181"/>
        <source>No answer</source>
        <translation>無回應</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="183"/>
        <source>Reading the config...</source>
        <translation>正在讀取設定...</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="187"/>
        <location filename="../src/SupplyPage.cpp" line="202"/>
        <source>Selected</source>
        <translation>已選擇</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="190"/>
        <source>Ready to start</source>
        <translation>準備開始</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="193"/>
        <source>Dispensing</source>
        <translation>加油中</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="196"/>
        <source>Finished</source>
        <translation>已完成</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="199"/>
        <source>Fault</source>
        <translation>故障</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="202"/>
        <source>Free</source>
        <translation>空閒</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="220"/>
        <source>Working</source>
        <translation>加油中</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="220"/>
        <location filename="../src/SupplyPage.cpp" line="226"/>
        <source>Set</source>
        <translation>已設定</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="226"/>
        <source>Queued</source>
        <translation>排隊中</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="228"/>
        <source>Done</source>
        <translation>完成</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="238"/>
        <source>Manual</source>
        <translation>手動</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="238"/>
        <source>Auto</source>
        <translation>自動</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="239"/>
        <source>%1 - %2</source>
        <translation>%1 - %2</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="267"/>
        <source>Click a nozzle to see its tickets.</source>
        <translation>點擊一把油槍查看它的油票。</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="273"/>
        <source>Tickets of nozzle %1</source>
        <translation>%1 號油槍的油票</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="278"/>
        <source>A fill with a card: %1 kg.</source>
        <translation>刷卡加油：%1 公斤。</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="298"/>
        <source>Done: %1 kg dispensed.</source>
        <translation>完成：已加油 %1 公斤。</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="300"/>
        <source>No tickets are queued on this nozzle.</source>
        <translation>這把油槍沒有排隊的油票。</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="276"/>
        <location filename="../src/SupplyPage.cpp" line="295"/>
        <source>Card: %1</source>
        <translation>卡：%1</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="286"/>
        <source>Started: %1 kg. The tickets are locked until the fill ends.</source>
        <translation>已開始：%1 公斤。加油結束前油票已鎖定。</translation>
    </message>
    <message>
        <location filename="../src/SupplyPage.cpp" line="288"/>
        <source>%1 tickets, together %2 kg.</source>
        <translation>%1 張油票，共 %2 公斤。</translation>
    </message>
</context>
<context>
    <name>SupplySelection</name>
    <message>
        <location filename="../src/SupplySelection.cpp" line="29"/>
        <source>There is no such nozzle.</source>
        <translation>沒有這支油槍。</translation>
    </message>
    <message>
        <location filename="../src/SupplySelection.cpp" line="31"/>
        <source>There is no connection to the boards.</source>
        <translation>與控制板之間沒有連線。</translation>
    </message>
    <message>
        <location filename="../src/SupplySelection.cpp" line="33"/>
        <source>The board of nozzle %1 does not answer.</source>
        <translation>油槍 %1 的控制板沒有回應。</translation>
    </message>
    <message>
        <location filename="../src/SupplySelection.cpp" line="35"/>
        <source>The config of nozzle %1 has not been read yet.</source>
        <translation>油槍 %1 的設定還沒有讀取。</translation>
    </message>
    <message>
        <location filename="../src/SupplySelection.cpp" line="37"/>
        <source>Nozzle %1 is in manual mode: the supplier types the amount at the dispenser.</source>
        <translation>油槍 %1 是手動模式：供油員在加油機上輸入油量。</translation>
    </message>
    <message>
        <location filename="../src/SupplySelection.cpp" line="39"/>
        <source>Nozzle %1 has a fault.</source>
        <translation>油槍 %1 有故障。</translation>
    </message>
    <message>
        <location filename="../src/SupplySelection.cpp" line="41"/>
        <source>Nozzle %1 is busy.</source>
        <translation>油槍 %1 忙碌中。</translation>
    </message>
</context>
<context>
    <name>SupplySession</name>
    <message>
        <location filename="../src/SupplySession.cpp" line="53"/>
        <source>Choose a nozzle first: tap it, or press its select key at the dispenser.</source>
        <translation>請先選擇油槍：點它，或按加油機上的選擇鍵。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="55"/>
        <source>The config of nozzle %1 has not been read yet.</source>
        <translation>油槍 %1 的設定還沒有讀到。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="57"/>
        <source>Nozzle %1 is in manual mode: the supplier types the amount at the dispenser, the cashier enters the tickets afterwards.</source>
        <translation>油槍 %1 是手動模式：供油員在加油機上輸入油量，收銀員事後輸入油票。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="59"/>
        <location filename="../src/SupplySession.cpp" line="90"/>
        <location filename="../src/SupplySession.cpp" line="106"/>
        <source>Nozzle %1 has started: its tickets cannot be changed.</source>
        <translation>油槍 %1 已開始：不能再更改它的油票。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="64"/>
        <source>Nozzle %1: ticket added, %2 kg. Together %3 kg.</source>
        <translation>油槍 %1：已新增油票，%2 公斤。共 %3 公斤。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="125"/>
        <location filename="../src/SupplySession.cpp" line="182"/>
        <source>Choose a nozzle first.</source>
        <translation>請先選擇油槍。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="131"/>
        <source>Nozzle %1 is in manual mode: the supplier starts the fill at the dispenser.</source>
        <translation>油槍 %1 是手動模式：由供油員在加油機上開始加油。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="133"/>
        <source>Nozzle %1 has started already.</source>
        <translation>油槍 %1 已經開始。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="141"/>
        <source>Log in first.</source>
        <translation>請先登入。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="143"/>
        <source>There is no connection to the boards.</source>
        <translation>與油槍板沒有連線。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="145"/>
        <source>The board of nozzle %1 does not answer.</source>
        <translation>油槍 %1 的板子沒有回應。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="147"/>
        <source>Choose the nozzle first.</source>
        <translation>請先選中油槍。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="149"/>
        <source>Nozzle %1 is busy.</source>
        <translation>油槍 %1 正忙。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="164"/>
        <source>The board is busy. Try again.</source>
        <translation>板子正忙。請再試一次。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="127"/>
        <source>This card cannot be used: it is not active.</source>
        <translation>這張卡不能使用：狀態不是正常。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="129"/>
        <source>Nozzle %1 gives another fuel type than the card (%2).</source>
        <translation>油槍 %1 的油品與卡（%2）不同。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="135"/>
        <source>Nozzle %1 has tickets in its queue: clear them first.</source>
        <translation>油槍 %1 的佇列裡有油票：請先清除。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="137"/>
        <source>Enter the weight to supply.</source>
        <translation>請輸入要加的重量。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="139"/>
        <source>The card holds only %1 kg.</source>
        <translation>卡內只有 %1 公斤。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="184"/>
        <source>Enter the weight and the litres that were dispensed.</source>
        <translation>請輸入已加油的重量和公升數。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="186"/>
        <source>Nozzle %1 has a fill running or a result waiting: the app reads it by itself.</source>
        <translation>油槍 %1 正在加油或有結果等待讀取：程式會自己讀取。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="188"/>
        <source>More than was asked for (%1 kg). Check the reading.</source>
        <translation>超過了要求的量（%1 公斤）。請檢查讀數。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="190"/>
        <source>More than the tickets cover (%1 kg). Check the reading.</source>
        <translation>超過油票所含（%1 公斤）。請檢查讀數。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="192"/>
        <source>Enter the tickets first.</source>
        <translation>請先輸入油票。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="265"/>
        <source>Nozzle %1: the queue was cleared at the dispenser.</source>
        <translation>%1 號油槍：佇列已在加油機上清除。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="351"/>
        <source>Nozzle %1 does not answer. Press Start again.</source>
        <translation>油槍 %1 沒有應答。請再按一次開始。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="352"/>
        <source>Nozzle %1 does not answer. The tickets stay: press Start again.</source>
        <translation>油槍 %1 沒有回應。油票保留：請再按「開始」。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="357"/>
        <location filename="../src/SupplySession.cpp" line="452"/>
        <source>Nozzle %1: no answer to the start. If the fill runs, its result is recorded by itself.</source>
        <translation>油槍 %1：開始命令沒有回應。如果正在加油，結果會自動記錄。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="371"/>
        <source>Nozzle %1: the board is busy. Press Start again.</source>
        <translation>油槍 %1：板子正忙。請再按「開始」。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="410"/>
        <source>Nozzle %1: %2 kg dispensed, but its tickets could not be matched: the supply is saved without them.</source>
        <translation>%1 號油槍：已加 %2 公斤，但油票無法對上：此次供油不帶油票儲存。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="312"/>
        <location filename="../src/SupplySession.cpp" line="382"/>
        <source>Nozzle %1 started: %2 kg.</source>
        <translation>油槍 %1 已開始：%2 公斤。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="403"/>
        <source>Nozzle %1: the result could not be saved: %2</source>
        <translation>油槍 %1：無法儲存結果：%2</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="412"/>
        <source>Nozzle %1: %2 kg dispensed. A remainder ticket of %3 kg is made.</source>
        <translation>油槍 %1：已加油 %2 公斤。產生一張 %3 公斤的餘油票。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="414"/>
        <source>Nozzle %1: %2 kg dispensed.</source>
        <translation>油槍 %1：已加油 %2 公斤。</translation>
    </message>
    <message>
        <location filename="../src/SupplySession.cpp" line="436"/>
        <location filename="../src/SupplySession.cpp" line="441"/>
        <location filename="../src/SupplySession.cpp" line="448"/>
        <source>Nozzle %1: %2</source>
        <translation>油槍 %1：%2</translation>
    </message>
</context>
</TS>
