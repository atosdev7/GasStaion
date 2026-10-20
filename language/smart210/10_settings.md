# Smart210: Settings (machine config, server, PIN)

Sidebar item: **Settings**

Section = a screen of the terminal. Strings are ordered by the source file they come from. **MISSING** = no translation.

| Source (English) | zh-CN | zh-TW | Source file |
|---|---|---|---|
| Machine Config | 机器配置 | 機器設定 | MachineConfigPage |
| Nozzle %1 | 油枪 %1 | 油槍 %1 | MachineConfigPage |
| The board of nozzle %1 does not answer. | 油枪 %1 的控制板没有响应。 | 油槍 %1 的控制板沒有回應。 | MachineConfigPage |
| Enter the pulses per litre as a number above 0, for example 1200 or 1234.5. | 请输入每升脉冲数，须大于 0，例如 1200 或 1234.5。 | 請輸入每公升脈衝數，須大於 0，例如 1200 或 1234.5。 | MachineConfigPage |
| Enter the density in kg per litre, from 0.1000 to 2.0000. | 请输入密度（千克/升），范围 0.1000 到 2.0000。 | 請輸入密度（公斤/公升），範圍 0.1000 到 2.0000。 | MachineConfigPage |
| Pick a fuel type. The fuel types come from the web app with the config download. | 请选择油品。油品随配置下载从网页端获得。 | 請選擇油品。油品隨設定下載從網頁端取得。 | MachineConfigPage |
| Pick a nozzle in the table first. | 请先在表格中选择一支油枪。 | 請先在表格中選擇一支油槍。 | MachineConfigPage |
| There is no connection to the boards. | 与控制板之间没有连接。 | 與控制板之間沒有連線。 | MachineConfigPage |
| Reading the boards... | 正在读取控制板... | 正在讀取控制板... | MachineConfigPage |
| Pick a nozzle in the table to change its config. | 在表格中选择一支油枪即可修改它的配置。 | 在表格中選擇一支油槍即可修改它的設定。 | MachineConfigPage |
| Sent to the board of nozzle %1. Waiting for its answer... | 已发送到油枪 %1 的控制板。等待它的回应... | 已傳送到油槍 %1 的控制板。等待它的回應... | MachineConfigPage |
| (none) | （无） | （無） | MachineConfigPage |
| (pick a tank) | （请选择油罐） | （請選擇油罐） | MachineConfigPage |
| Nozzle | 油枪 | 油槍 | MachineConfigPage |
| Reading... | 读取中... | 讀取中... | MachineConfigPage |
| No answer | 无响应 | 無回應 | MachineConfigPage |
| No converter | 转换器未连接 | 轉換器未連線 | MachineConfigPage |
| Continuous | 持续 | 持續 | MachineConfigPage |
| The config could not be sent. Try again. | 配置无法发送，请再试一次。 | 設定無法傳送，請再試一次。 | MachineConfigPage |
| The board of nozzle %1 confirmed the config. | 油枪 %1 的控制板已确认配置。 | 油槍 %1 的控制板已確認設定。 | MachineConfigPage |
| Latest changes | 最近的修改 | 最近的修改 | MachineConfigPage |
| The reader is read on this serial port (8 data bits, no parity, 1 stop bit). Put a card on it: its id shows on the Cards screen. The station's Arduino reader sends one line "CARD: <id>" for a card (9600 baud, ends with a line break): keep the text before the id as CARD: and lines of another kind are ignored. An original Arduino Uno shows up as /dev/ttyACM0, a clone with a CH340 chip as /dev/ttyUSB0. | 读卡器通过此串口读取（8 位数据位，无校验，1 位停止位）。把卡放上去：卡号会显示在“油卡”页面。本站的 Arduino 读卡器每读到一张卡发送一行“CARD: <卡号>”（9600 波特，以换行结尾）：请把卡号前的文字保持为 CARD:，其他内容的行会被忽略。原装 Arduino Uno 显示为 /dev/ttyACM0，使用 CH340 芯片的兼容板显示为 /dev/ttyUSB0。 | 讀卡器透過此序列埠讀取（8 位元資料位元，無同位檢查，1 位元停止位元）。把卡放上去：卡號會顯示在「油卡」頁面。本站的 Arduino 讀卡器每讀到一張卡傳送一行「CARD: <卡號>」（9600 鮑率，以換行結尾）：請把卡號前的文字保持為 CARD:，其他內容的行會被忽略。原廠 Arduino Uno 顯示為 /dev/ttyACM0，使用 CH340 晶片的相容板顯示為 /dev/ttyUSB0。 | MachineConfigPage |
| Server address | 服务器地址 | 伺服器位址 | MachineConfigPage |
| Upload | 上传 | 上傳 | MachineConfigPage |
| Card reader | 读卡器 | 讀卡器 | MachineConfigPage |
| Printer address | 打印机地址 | 印表機位址 | MachineConfigPage |
| Converter port | 转换器端口 | 轉換器連接埠 | MachineConfigPage |
| Nozzle %1 | 油枪 %1 | 油槍 %1 | MachineConfigPage |
| Saved. | 已保存。 | 已儲存。 | MachineConfigPage |
| The values could not be saved. | 数值无法保存。 | 數值無法儲存。 | MachineConfigPage |
| Nozzle %1: %2 | 油枪 %1：%2 | 油槍 %1：%2 | MachineConfigPage |
| Checking the printer... | 正在检查打印机... | 正在檢查印表機... | MachineConfigPage |
| Saved, but this terminal cannot listen on that port: %1 | 已保存，但本终端无法监听该端口：%1 | 已儲存，但本終端無法監聽該連接埠：%1 | MachineConfigPage |
| Enter a port from 1 to 65535. | 请输入 1 到 65535 之间的端口。 | 請輸入 1 到 65535 之間的連接埠。 | MachineConfigPage |
| The terminal downloads from the server every minute. | 终端每分钟从服务器下载一次。 | 終端每分鐘從伺服器下載一次。 | MachineConfigPage |
| Records go up only when the cashier presses Upload data on the Review screen. | 只有收银员在“核对”页面按下“上传数据”时，记录才会上传。 | 只有收銀員在「核對」頁面按下「上傳資料」時，記錄才會上傳。 | MachineConfigPage |
| Every record goes up by itself %1 minutes after it was made (time to delete a mistaken sale). A record that was uploaded cannot be changed on the terminal any more. | 每条记录在生成 %1 分钟后自动上传（留出时间删除录错的销售）。已上传的记录在终端上不能再修改。 | 每筆記錄在產生 %1 分鐘後自動上傳（留出時間刪除錄錯的銷售）。已上傳的記錄在終端上不能再修改。 | MachineConfigPage |
| The records of the business days that are over (9 AM to 9 AM) go up by themselves. Today's records stay here, so that they can still be fixed or deleted, until Upload data is pressed. | 已结束的营业日（上午 9 点到次日上午 9 点）的记录会自动上传。当天的记录留在本机，仍可修改或删除，直到按下“上传数据”。 | 已結束的營業日（上午 9 點到次日上午 9 點）的記錄會自動上傳。當天的記錄留在本機，仍可修改或刪除，直到按下「上傳資料」。 | MachineConfigPage |
| Enter how many characters the id has (1 to 64). | 请输入卡号的字符数（1 到 64）。 | 請輸入卡號的字元數（1 到 64）。 | MachineConfigPage |
| At close of day | 日结时 | 日結時 | MachineConfigPage |
| Online | 在线 | 線上 | MachineConfigPage |
| Card reader | 读卡器 | 讀卡器 | MachineConfigPage |
| Printer address | 打印机地址 | 印表機位址 | MachineConfigPage |
| Auto | 自动 | 自動 | MachineConfigPage |
| Tank | 油罐 | 油罐 | MachineConfigPage |
| Density | 密度 | 密度 | MachineConfigPage |
| Fuel type | 油品 | 油品 | MachineConfigPage |
| Mode | 模式 | 模式 | MachineConfigPage |
| Nozzles | 油枪 | 油槍 | MachineConfigPage |
| Port | 端口 | 連接埠 | MachineConfigPage |
| Check printer | 检查打印机 | 檢查印表機 | MachineConfigPage |
| Save port | 保存端口 | 儲存連接埠 | MachineConfigPage |
| Save printer | 保存打印机 | 儲存印表機 | MachineConfigPage |
| For example 192.168.1.20 | 例如 192.168.1.20 | 例如 192.168.1.20 | MachineConfigPage |
| Back | 返回 | 返回 | MachineConfigPage |
| Read | 读取 | 讀取 | MachineConfigPage |
| Send | 发送 | 傳送 | MachineConfigPage |
| e.g. 1200 or 1234.5 | 例如 1200 或 1234.5 | 例如 1200 或 1234.5 | MachineConfigPage |
| kg/L, e.g. 0.8300 | 千克/升，例如 0.8300 | 公斤/公升，例如 0.8300 | MachineConfigPage |
| Pulses per litre | 每升脉冲数 | 每公升脈衝數 | MachineConfigPage |
| Manual | 手动 | 手動 | MachineConfigPage |
| Status | 状态 | 狀態 | MachineConfigPage |
| Converter port (this terminal listens) | 转换器端口（本终端监听） | 轉換器連接埠（本終端監聽） | MachineConfigPage |
| Upload | 上传 | 上傳 | MachineConfigPage |
| The id ends with CR or LF | 卡号以回车或换行结尾 | 卡號以回車或換行結尾 | MachineConfigPage |
| Text before the id | 卡号前的文字 | 卡號前的文字 | MachineConfigPage |
| What the bytes are | 字节的含义 | 位元組的含義 | MachineConfigPage |
| Server | 服务器 | 伺服器 | MachineConfigPage |
| Printer | 打印机 | 印表機 | MachineConfigPage |
| Speed (baud) | 速率（波特率） | 速率（鮑率） | MachineConfigPage |
| Save | 保存 | 儲存 | MachineConfigPage |
| Upload to the server | 上传到服务器 | 上傳到伺服器 | MachineConfigPage |
| How the id comes | 卡号的格式 | 卡號的格式 | MachineConfigPage |
| Serial port | 串口 | 序列埠 | MachineConfigPage |
| Save card reader | 保存读卡器设置 | 儲存讀卡器設定 | MachineConfigPage |
| Hex digits of the bytes | 字节的十六进制数字 | 位元組的十六進位數字 | MachineConfigPage |
| Text (ASCII) | 文本（ASCII） | 文字（ASCII） | MachineConfigPage |
| none | 无 | 無 | MachineConfigPage |
| characters | 字符数 | 字元數 | MachineConfigPage |
| The id has a fixed length | 卡号长度固定 | 卡號長度固定 | MachineConfigPage |
| Length of the id | 卡号长度 | 卡號長度 | MachineConfigPage |
| Too many wrong PINs. Try again in %1 min. | PIN 错误次数过多，请 %1 分钟后再试。 | PIN 錯誤次數過多，請 %1 分鐘後再試。 | PinPage |
| Wrong PIN. %1 more tries before the PIN is locked. | PIN 错误。再错 %1 次 PIN 将被锁定。 | PIN 錯誤。再錯 %1 次 PIN 將被鎖定。 | PinPage |
| No PIN is set for this terminal. Set one on the Devices screen of the web app. | 本终端尚未设置 PIN。请在网页端的“设备”页面设置。 | 本終端尚未設定 PIN。請在網頁端的「裝置」頁面設定。 | PinPage |
| Cancel | 取消 | 取消 | PinPage |
| OK | 确定 | 確定 | PinPage |
| Enter the PIN of this terminal. | 请输入本终端的 PIN。 | 請輸入本終端的 PIN。 | PinPage |
| Machine Config | 机器配置 | 機器設定 | PinPage |
| Server | 服务器 | 伺服器 | ServerForm |
| For example 192.168.1.10 | 例如 192.168.1.10 | 例如 192.168.1.10 | ServerForm |
| Test Connection | 测试连接 | 測試連線 | ServerForm |
| Address | 地址 | 位址 | ServerForm |
| Port | 端口 | 連接埠 | ServerForm |
| Connecting... | 正在连接... | 連線中... | ServerForm |
| Settings | 设置 | 設定 | SettingsPage |
| Log out | 退出登录 | 登出 | SettingsPage |
| Logged in as %1 | 当前登录：%1 | 目前登入：%1 | SettingsPage |
