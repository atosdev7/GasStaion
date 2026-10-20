<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE TS>
<TS version="2.1" language="zh_CN">
<context>
    <name>ManualSupplyPage</name>
    <message>
        <location filename="../src/supplies/ManualSupplyPage.ui" line="13"/>
        <source>Manual input</source>
        <translation>手动录入</translation>
    </message>
    <message>
        <location filename="../src/supplies/ManualSupplyPage.ui" line="19"/>
        <source>A manual-mode nozzle is never recorded by the program: type its supply in here, with the tickets that paid for it.</source>
        <translation>手动模式的油枪程序不会自动记录：请在此录入其供油情况和对应的油票。</translation>
    </message>
    <message>
        <location filename="../src/supplies/ManualSupplyPage.ui" line="31"/>
        <source>Nozzle</source>
        <translation>油枪</translation>
    </message>
    <message>
        <location filename="../src/supplies/ManualSupplyPage.ui" line="36"/>
        <source>Date and time</source>
        <translation>日期和时间</translation>
    </message>
    <message>
        <location filename="../src/supplies/ManualSupplyPage.ui" line="41"/>
        <source>Density (kg/L)</source>
        <translation>密度（千克/升）</translation>
    </message>
    <message>
        <location filename="../src/supplies/ManualSupplyPage.ui" line="64"/>
        <source>Preset (kg)</source>
        <translation>预置（千克）</translation>
    </message>
    <message>
        <location filename="../src/supplies/ManualSupplyPage.ui" line="69"/>
        <source>Dispensed (kg)</source>
        <translation>已加油量（千克）</translation>
    </message>
    <message>
        <location filename="../src/supplies/ManualSupplyPage.ui" line="74"/>
        <source>Litres</source>
        <translation>升</translation>
    </message>
    <message>
        <location filename="../src/supplies/ManualSupplyPage.ui" line="105"/>
        <source>Ticket barcode, then Enter</source>
        <translation>油票条码，然后按回车</translation>
    </message>
    <message>
        <location filename="../src/supplies/ManualSupplyPage.ui" line="112"/>
        <source>Add ticket</source>
        <translation>添加油票</translation>
    </message>
    <message>
        <location filename="../src/supplies/ManualSupplyPage.ui" line="118"/>
        <source>Remove</source>
        <translation>删除</translation>
    </message>
    <message>
        <location filename="../src/supplies/ManualSupplyPage.ui" line="130"/>
        <source>Comment (optional)</source>
        <translation>备注（可选）</translation>
    </message>
    <message>
        <location filename="../src/supplies/ManualSupplyPage.ui" line="147"/>
        <source>Save supply</source>
        <translation>保存供油</translation>
    </message>
    <message>
        <location filename="../src/supplies/ManualSupplyPage.cpp" line="54"/>
        <source>Ticket</source>
        <translation>油票</translation>
    </message>
    <message>
        <location filename="../src/supplies/ManualSupplyPage.cpp" line="54"/>
        <source>kg</source>
        <translation>公斤</translation>
    </message>
    <message>
        <location filename="../src/supplies/ManualSupplyPage.cpp" line="109"/>
        <source>Nozzle %1 (%2)</source>
        <translation>%1 号油枪（%2）</translation>
    </message>
    <message>
        <location filename="../src/supplies/ManualSupplyPage.cpp" line="180"/>
        <source>Choose the nozzle first.</source>
        <translation>请先选中油枪。</translation>
    </message>
    <message>
        <location filename="../src/supplies/ManualSupplyPage.cpp" line="193"/>
        <source>This ticket is in the list already.</source>
        <translation>这张油票已在列表中。</translation>
    </message>
    <message>
        <location filename="../src/supplies/ManualSupplyPage.cpp" line="245"/>
        <source>No nozzle works in manual mode.</source>
        <translation>没有手动模式的油枪。</translation>
    </message>
    <message>
        <location filename="../src/supplies/ManualSupplyPage.cpp" line="248"/>
        <source>Type the ticket barcodes, one after the other.</source>
        <translation>请逐张输入油票条码。</translation>
    </message>
    <message>
        <location filename="../src/supplies/ManualSupplyPage.cpp" line="248"/>
        <source>Type the kg and the litres that were dispensed.</source>
        <translation>请输入已加油的千克数和升数。</translation>
    </message>
    <message>
        <location filename="../src/supplies/ManualSupplyPage.cpp" line="253"/>
        <source>Tickets: %1 kg, dispensed: %2 kg, difference: %3 kg (the tickets must cover it, at most %4 kg more)</source>
        <translation>油票：%1 千克，已加油：%2 千克，差：%3 千克（油票必须够用，最多可多 %4 千克）</translation>
    </message>
    <message>
        <location filename="../src/supplies/ManualSupplyPage.cpp" line="265"/>
        <source>Type the weight and the litres as numbers, for example 15.00.</source>
        <translation>请以数字输入重量和升数，例如 15.00。</translation>
    </message>
    <message>
        <location filename="../src/supplies/ManualSupplyPage.cpp" line="280"/>
        <source>The supply was recorded with %1 tickets. It will be uploaded.</source>
        <translation>供油已记录，含 %1 张油票。将会上传。</translation>
    </message>
</context>
<context>
    <name>QObject</name>
    <message>
        <location filename="../src/supplies/SupplyQueue.cpp" line="38"/>
        <source>This ticket was already used.</source>
        <translation>这张油票已经使用。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyQueue.cpp" line="16"/>
        <source>That is not a ticket barcode (16 digits).</source>
        <translation>这不是油票条码（16 位数字）。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyQueue.cpp" line="25"/>
        <source>This ticket is not known on this terminal.</source>
        <translation>本终端没有这张油票。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyQueue.cpp" line="42"/>
        <source>This ticket is restricted and cannot be used.</source>
        <translation>这张油票已被限制，不能使用。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyRecord.cpp" line="137"/>
        <source>it is twice in the list.</source>
        <translation>它在列表中出现了两次。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyQueue.cpp" line="34"/>
        <source>This ticket was never sold.</source>
        <translation>这张油票从未售出。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyQueue.cpp" line="47"/>
        <source>This ticket is another fuel type (%1) than the nozzle (%2).</source>
        <translation>这张油票的油品（%1）与油枪（%2）不同。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyQueue.cpp" line="54"/>
        <source>This ticket is already in the queue of nozzle %1.</source>
        <translation>这张油票已在油枪 %1 的队列中。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyQueue.cpp" line="88"/>
        <source>The ticket could not be added.</source>
        <translation>无法添加这张油票。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyRecord.cpp" line="79"/>
        <source>Nozzle %1 is not known yet.</source>
        <translation>油枪 %1 还不存在。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyRecord.cpp" line="239"/>
        <source>The supply could not be saved.</source>
        <translation>无法保存这次供油。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyRecord.cpp" line="137"/>
        <source>Ticket %1 cannot be used: %2</source>
        <translation>油票 %1 不能使用：%2</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyRecord.cpp" line="149"/>
        <source>The tickets add up to %1 kg but %2 kg was dispensed: the tickets must cover everything that was dispensed.</source>
        <translation>油票合计 %1 千克，但已加油 %2 千克：油票必须覆盖全部已加的油。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyRecord.cpp" line="151"/>
        <source>The tickets add up to %1 kg but only %2 kg was dispensed: they may be at most %3 kg more.</source>
        <translation>油票合计 %1 千克，但只加了 %2 千克：最多只能多 %3 千克。</translation>
    </message>
</context>
<context>
    <name>SupplyPage</name>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="71"/>
        <source>Nozzle</source>
        <translation>油枪</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="71"/>
        <location filename="../src/supplies/SupplyPage.cpp" line="91"/>
        <source>Fuel type</source>
        <translation>油品</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="71"/>
        <source>Mode</source>
        <translation>模式</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="71"/>
        <source>Status</source>
        <translation>状态</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="71"/>
        <source>Dispensed (kg)</source>
        <translation>已加油量（千克）</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.ui" line="13"/>
        <source>Supplies</source>
        <translation>供油</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="71"/>
        <source>Tickets (kg - count)</source>
        <translation>油票（千克 - 张数）</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="91"/>
        <source>Barcode</source>
        <translation>条码</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="91"/>
        <source>Amount (kg)</source>
        <translation>数量（千克）</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="179"/>
        <source>No converter</source>
        <translation>转换器未连接</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="181"/>
        <source>No answer</source>
        <translation>无响应</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="183"/>
        <source>Reading the config...</source>
        <translation>正在读取配置...</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="187"/>
        <location filename="../src/supplies/SupplyPage.cpp" line="202"/>
        <source>Selected</source>
        <translation>已选择</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="190"/>
        <source>Ready to start</source>
        <translation>准备开始</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="193"/>
        <source>Dispensing</source>
        <translation>加油中</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="196"/>
        <source>Finished</source>
        <translation>已完成</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="199"/>
        <source>Fault</source>
        <translation>故障</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="202"/>
        <source>Free</source>
        <translation>空闲</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="220"/>
        <source>Working</source>
        <translation>加油中</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="220"/>
        <location filename="../src/supplies/SupplyPage.cpp" line="226"/>
        <source>Set</source>
        <translation>已设定</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="226"/>
        <source>Queued</source>
        <translation>排队中</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="228"/>
        <source>Done</source>
        <translation>完成</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="238"/>
        <source>Manual</source>
        <translation>手动</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="238"/>
        <source>Auto</source>
        <translation>自动</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="239"/>
        <source>%1 - %2</source>
        <translation>%1 - %2</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="267"/>
        <source>Click a nozzle to see its tickets.</source>
        <translation>点击一把油枪查看它的油票。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="273"/>
        <source>Tickets of nozzle %1</source>
        <translation>%1 号油枪的油票</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="278"/>
        <source>A fill with a card: %1 kg.</source>
        <translation>刷卡加油：%1 千克。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="298"/>
        <source>Done: %1 kg dispensed.</source>
        <translation>完成：已加油 %1 千克。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="300"/>
        <source>No tickets are queued on this nozzle.</source>
        <translation>这把油枪没有排队的油票。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="276"/>
        <location filename="../src/supplies/SupplyPage.cpp" line="295"/>
        <source>Card: %1</source>
        <translation>卡：%1</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="286"/>
        <source>Started: %1 kg. The tickets are locked until the fill ends.</source>
        <translation>已开始：%1 千克。加油结束前油票已锁定。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplyPage.cpp" line="288"/>
        <source>%1 tickets, together %2 kg.</source>
        <translation>%1 张油票，共 %2 千克。</translation>
    </message>
</context>
<context>
    <name>SupplySelection</name>
    <message>
        <location filename="../src/supplies/SupplySelection.cpp" line="29"/>
        <source>There is no such nozzle.</source>
        <translation>没有这支油枪。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySelection.cpp" line="31"/>
        <source>There is no connection to the boards.</source>
        <translation>与控制板之间没有连接。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySelection.cpp" line="33"/>
        <source>The board of nozzle %1 does not answer.</source>
        <translation>油枪 %1 的控制板没有响应。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySelection.cpp" line="35"/>
        <source>The config of nozzle %1 has not been read yet.</source>
        <translation>油枪 %1 的配置还没有读取。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySelection.cpp" line="37"/>
        <source>Nozzle %1 is in manual mode: the supplier types the amount at the dispenser.</source>
        <translation>油枪 %1 是手动模式：供油员在加油机上输入油量。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySelection.cpp" line="39"/>
        <source>Nozzle %1 has a fault.</source>
        <translation>油枪 %1 有故障。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySelection.cpp" line="41"/>
        <source>Nozzle %1 is busy.</source>
        <translation>油枪 %1 正忙。</translation>
    </message>
</context>
<context>
    <name>SupplySession</name>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="53"/>
        <source>Choose a nozzle first: tap it, or press its select key at the dispenser.</source>
        <translation>请先选择油枪：点它，或按加油机上的选择键。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="55"/>
        <source>The config of nozzle %1 has not been read yet.</source>
        <translation>油枪 %1 的配置还没有读到。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="57"/>
        <source>Nozzle %1 is in manual mode: the supplier types the amount at the dispenser, the cashier enters the tickets afterwards.</source>
        <translation>油枪 %1 是手动模式：供油员在加油机上输入油量，收银员事后录入油票。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="59"/>
        <location filename="../src/supplies/SupplySession.cpp" line="90"/>
        <location filename="../src/supplies/SupplySession.cpp" line="106"/>
        <source>Nozzle %1 has started: its tickets cannot be changed.</source>
        <translation>油枪 %1 已开始：不能再更改它的油票。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="64"/>
        <source>Nozzle %1: ticket added, %2 kg. Together %3 kg.</source>
        <translation>油枪 %1：已添加油票，%2 千克。共 %3 千克。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="125"/>
        <location filename="../src/supplies/SupplySession.cpp" line="182"/>
        <source>Choose a nozzle first.</source>
        <translation>请先选择油枪。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="131"/>
        <source>Nozzle %1 is in manual mode: the supplier starts the fill at the dispenser.</source>
        <translation>油枪 %1 是手动模式：由供油员在加油机上开始加油。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="133"/>
        <source>Nozzle %1 has started already.</source>
        <translation>油枪 %1 已经开始。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="141"/>
        <source>Log in first.</source>
        <translation>请先登录。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="143"/>
        <source>There is no connection to the boards.</source>
        <translation>与油枪板没有连接。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="145"/>
        <source>The board of nozzle %1 does not answer.</source>
        <translation>油枪 %1 的板子没有应答。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="147"/>
        <source>Choose the nozzle first.</source>
        <translation>请先选中油枪。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="149"/>
        <source>Nozzle %1 is busy.</source>
        <translation>油枪 %1 正忙。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="164"/>
        <source>The board is busy. Try again.</source>
        <translation>板子正忙。请再试一次。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="127"/>
        <source>This card cannot be used: it is not active.</source>
        <translation>这张卡不能使用：状态不是正常。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="129"/>
        <source>Nozzle %1 gives another fuel type than the card (%2).</source>
        <translation>油枪 %1 的油品与卡（%2）不同。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="135"/>
        <source>Nozzle %1 has tickets in its queue: clear them first.</source>
        <translation>油枪 %1 的队列里有油票：请先清除。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="137"/>
        <source>Enter the weight to supply.</source>
        <translation>请输入要加的重量。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="139"/>
        <source>The card holds only %1 kg.</source>
        <translation>卡内只有 %1 千克。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="184"/>
        <source>Enter the weight and the litres that were dispensed.</source>
        <translation>请输入已加油的重量和升数。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="186"/>
        <source>Nozzle %1 has a fill running or a result waiting: the app reads it by itself.</source>
        <translation>油枪 %1 正在加油或有结果等待读取：程序会自己读取。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="188"/>
        <source>More than was asked for (%1 kg). Check the reading.</source>
        <translation>超过了要求的量（%1 千克）。请检查读数。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="190"/>
        <source>More than the tickets cover (%1 kg). Check the reading.</source>
        <translation>超过油票所含（%1 千克）。请检查读数。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="192"/>
        <source>Enter the tickets first.</source>
        <translation>请先输入油票。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="265"/>
        <source>Nozzle %1: the queue was cleared at the dispenser.</source>
        <translation>%1 号油枪：队列已在加油机上清除。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="351"/>
        <source>Nozzle %1 does not answer. Press Start again.</source>
        <translation>油枪 %1 没有应答。请再按一次开始。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="352"/>
        <source>Nozzle %1 does not answer. The tickets stay: press Start again.</source>
        <translation>油枪 %1 没有应答。油票保留：请再按“开始”。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="357"/>
        <location filename="../src/supplies/SupplySession.cpp" line="452"/>
        <source>Nozzle %1: no answer to the start. If the fill runs, its result is recorded by itself.</source>
        <translation>油枪 %1：开始命令没有应答。如果正在加油，结果会自动记录。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="371"/>
        <source>Nozzle %1: the board is busy. Press Start again.</source>
        <translation>油枪 %1：板子正忙。请再按“开始”。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="410"/>
        <source>Nozzle %1: %2 kg dispensed, but its tickets could not be matched: the supply is saved without them.</source>
        <translation>%1 号油枪：已加 %2 千克，但油票无法对上：此次供油不带油票保存。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="312"/>
        <location filename="../src/supplies/SupplySession.cpp" line="382"/>
        <source>Nozzle %1 started: %2 kg.</source>
        <translation>油枪 %1 已开始：%2 千克。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="403"/>
        <source>Nozzle %1: the result could not be saved: %2</source>
        <translation>油枪 %1：无法保存结果：%2</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="412"/>
        <source>Nozzle %1: %2 kg dispensed. A remainder ticket of %3 kg is made.</source>
        <translation>油枪 %1：已加油 %2 千克。生成一张 %3 千克的余油票。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="414"/>
        <source>Nozzle %1: %2 kg dispensed.</source>
        <translation>油枪 %1：已加油 %2 千克。</translation>
    </message>
    <message>
        <location filename="../src/supplies/SupplySession.cpp" line="436"/>
        <location filename="../src/supplies/SupplySession.cpp" line="441"/>
        <location filename="../src/supplies/SupplySession.cpp" line="448"/>
        <source>Nozzle %1: %2</source>
        <translation>油枪 %1：%2</translation>
    </message>
</context>
</TS>
