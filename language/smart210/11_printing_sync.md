# Smart210: Printing, upload and sync (no own page)

Section = a screen of the terminal. Strings are ordered by the source file they come from. **MISSING** = no translation.

| Source (English) | zh-CN | zh-TW | Source file |
|---|---|---|---|
| The server did not answer in time. | 服务器没有及时响应。 | 伺服器沒有及時回應。 | ApiReply |
| The server sent an unexpected answer (HTTP %1). | 服务器返回了意外的内容（HTTP %1）。 | 伺服器回傳了意外的內容（HTTP %1）。 | ApiReply |
| The server is busy, try again in a minute. | 服务器正忙，请一分钟后重试。 | 伺服器忙碌中，請一分鐘後重試。 | ApiReply |
| The server refused the request (HTTP %1). | 服务器拒绝了请求（HTTP %1）。 | 伺服器拒絕了請求（HTTP %1）。 | ApiReply |
| Cannot reach the server: %1 | 无法连接服务器：%1 | 無法連線伺服器：%1 | ApiReply |
| The printer is offline. | 打印机离线。 | 印表機離線。 | EscPos |
| The printer is out of paper. | 打印机缺纸。 | 印表機缺紙。 | EscPos |
| The printer has an error. | 打印机出错。 | 印表機出錯。 | EscPos |
| The printer cover is open. | 打印机盖子没有合上。 | 印表機蓋子沒有闔上。 | EscPos |
| There is no ticket to print for this sale. | 这笔销售没有可打印的油票。 | 這筆銷售沒有可列印的油票。 | PrintService |
| There is no ticket to print. | 没有可打印的油票。 | 沒有可列印的油票。 | PrintService |
| No printer is set up. Enter its address on the Machine Config page. | 尚未设置打印机。请在“机器配置”页面输入它的地址。 | 尚未設定印表機。請在「機器設定」頁面輸入它的位址。 | PrintService |
| Gas Station Remainder Ticket | 加油站余油票 | 加油站餘油票 | PrintService |
| Gas Station Ticket Loan Voucher | 加油站借票凭证 | 加油站借票憑證 | PrintService |
| Gas Station Other Use Ticket | 加油站其他用途油票 | 加油站其他用途油票 | PrintService |
| Gas Station Self Use Ticket | 加油站自用油票 | 加油站自用油票 | PrintService |
| Gas Station Fuel Ticket | 加油站油票 | 加油站油票 | PrintService |
| The printer did not answer in time. | 打印机没有及时响应。 | 印表機沒有及時回應。 | PrinterConnection |
| The ticket was sent to the printer. | 油票已发送到打印机。 | 油票已傳送到印表機。 | PrinterConnection |
| Cannot reach the printer: %1 | 无法连接打印机：%1 | 無法連線印表機：%1 | PrinterConnection |
| The printer closed the connection. | 打印机断开了连接。 | 印表機中斷了連線。 | PrinterConnection |
| The printer is connected (it did not report its status). | 打印机已连接（它没有报告状态）。 | 印表機已連線（它沒有回報狀態）。 | PrinterConnection |
| The printer is ready. | 打印机就绪。 | 印表機就緒。 | PrinterConnection |
| The printer is busy. | 打印机正忙。 | 印表機忙碌中。 | PrinterConnection |
| No server is set up. | 尚未设置服务器。 | 尚未設定伺服器。 | SyncClient |
| The downloaded settings could not be saved: %1 | 下载的设置无法保存：%1 | 下載的設定無法儲存：%1 | SyncClient |
| The downloaded data could not be saved: %1 | 下载的数据无法保存：%1 | 下載的資料無法儲存：%1 | SyncClient |
| Download complete: %1 rows stored, %2 kept until they are uploaded. | 下载完成：已保存 %1 条，另有 %2 条在上传之前保持不变。 | 下載完成：已儲存 %1 筆，另有 %2 筆在上傳之前保持不變。 | SyncClient |
| Download complete: %1 rows stored. | 下载完成：已保存 %1 条。 | 下載完成：已儲存 %1 筆。 | SyncClient |
| The server's database was restored: %1 records will be sent again with the next upload. | 服务器的数据库已恢复：%1 条记录将在下次上传时重新发送。 | 伺服器的資料庫已還原：%1 筆記錄將在下次上傳時重新傳送。 | SyncClient |
| No server is set up. | 尚未设置服务器。 | 尚未設定伺服器。 | Uploader |
| Uploaded %1 records. The server refused %2: see the list of refused records. | 已上传 %1 条记录。服务器拒绝了 %2 条：请查看被拒绝的记录。 | 已上傳 %1 筆記錄。伺服器拒絕了 %2 筆：請查看被拒絕的記錄。 | Uploader |
| Uploaded %1 records. | 已上传 %1 条记录。 | 已上傳 %1 筆記錄。 | Uploader |
