# Smart210: Sales (ticket, spot, card sale, recent sales)

Sidebar item: **Sales**

Section = a screen of the terminal. Strings are ordered by the source file they come from. **MISSING** = no translation.

| Source (English) | zh-CN | zh-TW | Source file |
|---|---|---|---|
| Log in first. | 请先登录。 | 請先登入。 | Cards, TicketSale, SpotSale, LoanSettlement |
| Enter the weight in kg, above 0 and up to 999999. | 请输入重量（公斤），大于 0 且不超过 999999。 | 請輸入重量（公斤），大於 0 且不超過 999999。 | Cards, SpotSale |
| Enter the price per kg. | 请输入每公斤单价。 | 請輸入每公斤單價。 | Cards, TicketSale, SpotSale |
| The amount is not allowed. | 金额不合规。 | 金額不合規。 | Cards, TicketSale, SpotSale |
| Choose the fuel type. | 请选择油品。 | 請選擇油品。 | Cards, SpotSale |
| A day can be moved by one day. | 一次只能移动一天。 | 一次只能移動一天。 | Checkout |
| This day was not checked out on this terminal. | 这一天没有在本机结账。 | 這一天沒有在本機結帳。 | Checkout |
| The day can only be moved for one day after its checkout. | 结账后一天内才能移动该日期。 | 結帳後一天內才能移動該日期。 | Checkout |
| Some records of this day were already uploaded. | 这一天有记录已经上传。 | 這一天有記錄已經上傳。 | Checkout |
| The day could not be moved. | 日期无法移动。 | 日期無法移動。 | Checkout |
| This sale was already deleted. | 这笔销售已被删除。 | 這筆銷售已被刪除。 | SaleDeletion |
| The sale could not be deleted. | 销售无法删除。 | 銷售無法刪除。 | SaleDeletion |
| Tickets were already surrendered for the loan of this sale. | 这笔销售的借票已有油票抵偿。 | 這筆銷售的借票已有油票抵償。 | SaleDeletion |
| Fuel was already dispensed for the ticket of this sale. | 这笔销售的油票已经加过油。 | 這筆銷售的油票已經加過油。 | SaleDeletion |
| This sale was made by another cashier. | 这笔销售是其他收银员做的。 | 這筆銷售是其他收銀員做的。 | SaleDeletion |
| Only a spot sale can be deleted on the terminal. | 本机只能删除现场销售。 | 本機只能刪除現場銷售。 | SaleDeletion |
| This sale was already uploaded, so it can no longer be deleted here. | 这笔销售已上传，不能再在本机删除。 | 這筆銷售已上傳，不能再在本機刪除。 | SaleDeletion |
| This sale does not exist. | 这笔销售不存在。 | 這筆銷售不存在。 | SaleDeletion |
| Cash | 现金 | 現金 | SpotSalePage |
| Comment | 备注 | 備註 | SpotSalePage |
| Result | 结果 | 結果 | SpotSalePage |
| Amount | 金额 | 金額 | SpotSalePage |
| Fuel type | 油品 | 油品 | SpotSalePage |
| Receipt issued | 已开收据 | 已開收據 | SpotSalePage |
| Use | 用途 | 用途 | SpotSalePage |
| Save sale | 保存销售 | 儲存銷售 | SpotSalePage |
| Print again | 再打印一次 | 再列印一次 | SpotSalePage |
| Print ticket | 打印油票 | 列印油票 | SpotSalePage |
| Set directly | 直接设置 | 直接設定 | SpotSalePage |
| Choose the customer | 请选择客户 | 請選擇客戶 | SpotSalePage |
| Saved: %1 kg, amount %2. Ticket %3 | 已保存：%1 公斤，金额 %2。油票 %3 | 已儲存：%1 公斤，金額 %2。油票 %3 | SpotSalePage |
| Saved: %1 kg. Ticket %2 | 已保存：%1 公斤。油票 %2 | 已儲存：%1 公斤。油票 %2 | SpotSalePage |
| No nozzle was chosen, so nothing was sent. Print again prints the ticket. | 没有选择油枪，所以没有发送任何内容。“再次打印”可打印油票。 | 沒有選擇油槍，所以沒有傳送任何內容。「再次列印」可列印油票。 | SpotSalePage |
| Print again prints the ticket. | “再次打印”可打印油票。 | 「再次列印」可列印油票。 | SpotSalePage |
| Nozzle %1: %2 kg are set. Take the gun up to start the fill. | %1 号油枪：已设定 %2 千克。拿起油枪开始加油。 | %1 號油槍：已設定 %2 公斤。拿起油槍開始加油。 | SpotSalePage |
| Printing... | 正在打印... | 正在列印... | SpotSalePage |
| The ticket was sent to the printer. | 油票已发送到打印机。 | 油票已傳送到印表機。 | SpotSalePage |
| It was not printed: %1 | 没有打印出来：%1 | 沒有列印出來：%1 | SpotSalePage |
| Weight (kg) | 重量（公斤） | 重量（公斤） | SpotSalePage |
| Payment method | 付款方式 | 付款方式 | SpotSalePage |
| Price per kg | 每公斤单价 | 每公斤單價 | SpotSalePage |
| Ticket loan | 借票 | 借票 | SpotSalePage |
| Other | 其他 | 其他 | SpotSalePage |
| Self use | 自用 | 自用 | SpotSalePage |
| Sale | 销售 | 銷售 | SpotSalePage |
| Spot sale | 现场销售 | 現場銷售 | SpotSalePage |
| Customer | 客户 | 客戶 | SpotSalePage |
| The ticket numbers of this terminal are used up. | 本终端的油票编号已用完。 | 本終端的油票編號已用完。 | TicketNumbers |
| The ticket numbers are not known yet. Download from the server first. | 尚未取得油票编号设置。请先从服务器下载。 | 尚未取得油票編號設定。請先從伺服器下載。 | TicketNumbers |
| Ticket %1 cannot be sold: %2 | 油票 %1 不能出售：%2 | 油票 %1 不能出售：%2 | TicketSale |
| This ticket was already sold. | 这张油票已经售出。 | 這張油票已經售出。 | TicketSale |
| Too many tickets for one sale. | 一笔销售的油票太多。 | 一筆銷售的油票太多。 | TicketSale |
| Choose what the fuel is for. | 请选择用途。 | 請選擇用途。 | TicketSale, SpotSale |
| Choose the customer. | 请选择客户。 | 請選擇客戶。 | TicketSale, SpotSale |
| The sale could not be saved. | 销售无法保存。 | 銷售無法儲存。 | TicketSale, SpotSale |
| it is twice in the list. | 它在列表中出现了两次。 | 它在列表中出現了兩次。 | TicketSale, SupplyRecord, LoanSettlement |
| The tickets are not of the chosen fuel type. | 这些油票不是所选的油品。 | 這些油票不是所選的油品。 | TicketSale |
| This ticket was already used. | 这张油票已经使用。 | 這張油票已經使用。 | TicketSale, SupplyQueue, LoanSettlement |
| This ticket is restricted and cannot be sold. | 这张油票已被限制，不能出售。 | 這張油票已被限制，不能出售。 | TicketSale |
| That is not a ticket barcode (16 digits). | 这不是油票条码（16 位数字）。 | 這不是油票條碼（16 位數字）。 | TicketSale, SupplyQueue, LoanSettlement |
| This ticket is not known on this terminal. | 本终端没有这张油票。 | 本終端沒有這張油票。 | TicketSale, SupplyQueue, LoanSettlement |
| This ticket is another fuel type than the tickets already scanned. | 这张油票与已扫描的油票油品不同。 | 這張油票與已掃描的油票油品不同。 | TicketSale |
| Scan the tickets first. | 请先扫描油票。 | 請先掃描油票。 | TicketSale, LoanSettlement |
| Not valid: some tickets from %1 to %2 are not available to sell. | 无效：序号 %1 至 %2 中有油票不可销售。 | 無效：序號 %1 至 %2 中有油票不可銷售。 | TicketSalePage |
| Save sale | 保存销售 | 儲存銷售 | TicketSalePage |
| Choose the customer | 请选择客户 | 請選擇客戶 | TicketSalePage |
| %1 kg (%2) | %1 公斤（%2） | %1 公斤（%2） | TicketSalePage |
| There are not enough tickets of this spec in stock to sell. | 该规格库存油票不足，无法销售。 | 該規格庫存油票不足，無法銷售。 | TicketSalePage |
| Valid: serials %1 to %2 can be sold. | 有效：序号 %1 至 %2 可以销售。 | 有效：序號 %1 至 %2 可以銷售。 | TicketSalePage |
| Comment | 备注 | 備註 | TicketSalePage |
| Some tickets are not available to sell any more: remove the item and add it again. | 部分油票已不可销售：请删除该项目后重新添加。 | 部分油票已不可銷售：請刪除該項目後重新新增。 | TicketSalePage |
| Remove the item | 删除条目 | 刪除項目 | TicketSalePage |
| Remove this item from the list? | 从列表中删除这一条目吗？ | 從清單中刪除這個項目嗎？ | TicketSalePage |
| %1 tickets, %2 kg | %1 张油票，%2 公斤 | %1 張油票，%2 公斤 | TicketSalePage |
| , amount %1 | ，金额 %1 | ，金額 %1 | TicketSalePage |
| Too many tickets for one sale. | 一笔销售的油票太多。 | 一筆銷售的油票太多。 | TicketSalePage |
| Saved: %1 tickets, %2 kg, amount %3 | 已保存：%1 张油票，%2 公斤，金额 %3 | 已儲存：%1 張油票，%2 公斤，金額 %3 | TicketSalePage |
| Tickets to sell | 待售油票 | 待售油票 | TicketSalePage |
| Receipt issued | 已开收据 | 已開收據 | TicketSalePage |
| Clear list | 清空列表 | 清空列表 | TicketSalePage |
| End serial | 结束序号 | 結束序號 | TicketSalePage |
| Customer | 客户 | 客戶 | TicketSalePage |
| Ticket sale | 油票销售 | 油票銷售 | TicketSalePage |
| Sale | 销售 | 銷售 | TicketSalePage |
| Self use | 自用 | 自用 | TicketSalePage |
| Other | 其他 | 其他 | TicketSalePage |
| Ticket loan | 借票 | 借票 | TicketSalePage |
| kg | 公斤 | 公斤 | TicketSalePage |
| Fuel type | 油品 | 油品 | TicketSalePage |
| Count | 数量 | 數量 | TicketSalePage |
| Price per kg | 每公斤单价 | 每公斤單價 | TicketSalePage |
| Use | 用途 | 用途 | TicketSalePage |
| lowest available | 最低可用 | 最低可用 | TicketSalePage |
| Check | 检查 | 檢查 | TicketSalePage |
| Add item | 添加项目 | 新增項目 | TicketSalePage |
| Spec | 规格 | 規格 | TicketSalePage |
| Start serial | 起始序号 | 起始序號 | TicketSalePage |
| Saved: %1 tickets, %2 kg | 已保存：%1 张油票，%2 公斤 | 已儲存：%1 張油票，%2 公斤 | TicketSalePage |
