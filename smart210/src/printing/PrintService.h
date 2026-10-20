#ifndef PRINTSERVICE_H
#define PRINTSERVICE_H

#include "PrintOptions.h"
#include "PrinterConnection.h"
#include "TicketRenderer.h"

#include <QObject>
#include <QSqlDatabase>

// Prints the ticket of a spot sale: it reads the sale and its ticket from the database, draws the ticket (TicketRenderer) and sends it to
// the printer whose address is in the settings (printer_host, printer_port; set on the Machine Config page). The sale is already saved
// when this runs, so a printer that is off only produces a message: the cashier can press Print again.
class PrintService : public QObject
{
    Q_OBJECT

public:
    PrintService(QSqlDatabase db, const PrintOptions &options, QObject *parent = 0);

    bool hasPrinter() const;
    bool isBusy() const { return connection_.isBusy(); }
    void setTimeoutMs(int timeoutMs) { connection_.setTimeoutMs(timeoutMs); }
    void setStatusWaitMs(int waitMs) { connection_.setStatusWaitMs(waitMs); }

    void printSaleTicket(const QString &saleId);
    void printRemainderTicket(const QString &barcode);
    void checkPrinter();

    // The ticket of a spot sale as it would be printed. False and `error` when the sale is not a spot sale of this terminal.
    bool ticketOf(const QString &saleId, TicketData *ticket, QString *error) const;

    // The remainder ticket of a supply (its 16 digits) as it would be printed. False and `error` when there is no such ticket.
    bool remainderTicketOf(const QString &barcode, TicketData *ticket, QString *error) const;

    // The long title of the ticket of a sale made for this use (sale, self, other, ticket_loan, remainder).
    QString titleFor(const QString &usage) const;

    static const int DefaultPort = 9100;

signals:
    // ok: the job was handed to the printer. The text is for the cashier.
    void finished(bool ok, const QString &message);

private:
    QSqlDatabase db_;
    PrintOptions options_;
    PrinterConnection connection_;
};

#endif
