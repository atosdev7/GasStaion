#include "PrintService.h"

#include "Barcode.h"
#include "Settings.h"

#include <QDateTime>
#include <QSqlQuery>
#include <QVariant>

PrintService::PrintService(QSqlDatabase db, const PrintOptions &options, QObject *parent)
    : QObject(parent), db_(db), options_(options), connection_(this)
{
    connect(&connection_, SIGNAL(finished(bool,QString)), this, SIGNAL(finished(bool,QString)));
}

bool PrintService::hasPrinter() const
{
    return !Settings(db_).value(SettingKey::PrinterHost).isEmpty();
}

QString PrintService::titleFor(const QString &usage) const
{
    if (options_.titles.contains(usage) && !options_.titles.value(usage).isEmpty()) {
        return options_.titles.value(usage);
    }

    // The defaults, in the language of the terminal; config.ini overrides them (ticketTitle_sale and so on).
    if (usage == "self") {
        return tr("Gas Station Self Use Ticket");
    }
    if (usage == "other") {
        return tr("Gas Station Other Use Ticket");
    }
    if (usage == "ticket_loan") {
        return tr("Gas Station Ticket Loan Voucher");
    }
    if (usage == "remainder") {
        return tr("Gas Station Remainder Ticket");
    }
    if (usage == "card") {
        return tr("Gas Station Card Fuel Ticket");
    }

    return tr("Gas Station Fuel Ticket");
}

bool PrintService::ticketOf(const QString &saleId, TicketData *ticket, QString *error) const
{
    QSqlQuery query(db_);
    query.prepare("SELECT s.usage, s.fuel_type, COALESCE((SELECT name FROM fuel_types WHERE fuel_type = s.fuel_type), s.fuel_type), "
                  "s.amount_kg, s.created_at, t.barcode FROM sales s JOIN tickets t ON t.sale_id = s.id "
                  "WHERE s.id = ? AND s.sale_type = 'spot' AND s.deleted_at IS NULL");
    query.addBindValue(saleId);
    if (!query.exec() || !query.next()) {
        *error = tr("There is no ticket to print for this sale.");

        return false;
    }

    ticket->title = titleFor(query.value(0).toString());
    ticket->fuelName = query.value(2).toString();
    ticket->kgX100 = query.value(3).toLongLong();
    ticket->when = QDateTime::fromString(query.value(4).toString(), "yyyy-MM-dd HH:mm:ss");
    ticket->barcode = Barcode::fromKey(query.value(5).toLongLong());

    return true;
}

bool PrintService::remainderTicketOf(const QString &barcode, TicketData *ticket, QString *error) const
{
    const qlonglong key = Barcode::toKey(barcode);
    QSqlQuery query(db_);
    query.prepare("SELECT t.fuel_type, COALESCE((SELECT name FROM fuel_types WHERE fuel_type = t.fuel_type), t.fuel_type), t.amount_kg, "
                  "COALESCE(t.sale_date, '') FROM tickets t WHERE t.barcode = ?");
    query.addBindValue(key);
    if (key < 0 || !query.exec() || !query.next()) {
        *error = tr("There is no ticket to print.");

        return false;
    }

    ticket->title = titleFor("remainder");
    ticket->fuelName = query.value(1).toString();
    ticket->kgX100 = query.value(2).toLongLong();
    ticket->when = QDateTime::fromString(query.value(3).toString(), "yyyy-MM-dd HH:mm:ss");
    ticket->barcode = Barcode::fromKey(key);

    return true;
}

bool PrintService::cardTicketOf(const QString &barcode, TicketData *ticket, QString *error) const
{
    if (!remainderTicketOf(barcode, ticket, error)) { // the same columns: the fuel, the weight, the moment
        return false;
    }
    ticket->title = titleFor("card");

    return true;
}

void PrintService::printCardTicket(const QString &barcode)
{
    TicketData ticket;
    QString error;
    if (!cardTicketOf(barcode, &ticket, &error)) {
        emit finished(false, error);

        return;
    }
    if (!hasPrinter()) {
        emit finished(false, tr("No printer is set up. Enter its address on the Machine Config page."));

        return;
    }

    Settings settings(db_);
    connection_.send(settings.value(SettingKey::PrinterHost), settings.intValue(SettingKey::PrinterPort, DefaultPort),
                     TicketRenderer::render(ticket, options_));
}

void PrintService::printRemainderTicket(const QString &barcode)
{
    TicketData ticket;
    QString error;
    if (!remainderTicketOf(barcode, &ticket, &error)) {
        emit finished(false, error);

        return;
    }
    if (!hasPrinter()) {
        emit finished(false, tr("No printer is set up. Enter its address on the Machine Config page."));

        return;
    }

    Settings settings(db_);
    connection_.send(settings.value(SettingKey::PrinterHost), settings.intValue(SettingKey::PrinterPort, DefaultPort),
                     TicketRenderer::render(ticket, options_));
}

void PrintService::printSaleTicket(const QString &saleId)
{
    TicketData ticket;
    QString error;
    if (!ticketOf(saleId, &ticket, &error)) {
        emit finished(false, error);

        return;
    }
    if (!hasPrinter()) {
        emit finished(false, tr("No printer is set up. Enter its address on the Machine Config page."));

        return;
    }

    Settings settings(db_);
    connection_.send(settings.value(SettingKey::PrinterHost), settings.intValue(SettingKey::PrinterPort, DefaultPort),
                     TicketRenderer::render(ticket, options_));
}

void PrintService::checkPrinter()
{
    if (!hasPrinter()) {
        emit finished(false, tr("No printer is set up. Enter its address on the Machine Config page."));

        return;
    }

    Settings settings(db_);
    connection_.check(settings.value(SettingKey::PrinterHost), settings.intValue(SettingKey::PrinterPort, DefaultPort));
}
