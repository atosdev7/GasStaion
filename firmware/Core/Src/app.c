#include "app.h"

#include "board_config.h"
#include "board.h"
#include "port.h"
#include "ui.h"
#include "ui_drivers.h"

static Hal hal_;
static UiHal ui_hal_;
static Board board_;
static Ui ui_;
static uint32_t last_ms_;

void app_init(void)
{
    port_init(&hal_, &ui_hal_);
    board_init(&board_, &hal_, BOARD_ADDRESS, 0);
    ui_init(&ui_, &board_, &ui_hal_);
    last_ms_ = HAL_GetTick();
}

/* One pass of the main loop. It must come round at least every few milliseconds: the bytes of a request are taken from the receive ring,
   the answer goes out at once, the supply is watched, and the timers of the fill and the display run on the millisecond tick. */
void app_loop(void)
{
    const uint32_t now = HAL_GetTick();
    const uint32_t elapsed = now - last_ms_;
    int byte;

    while ((byte = port_rx_byte()) >= 0) {
        if (board_feed(&board_, (uint8_t)byte)) {
            size_t len;
            const uint8_t *reply = board_reply(&board_, &len);

            port_send(reply, len);
        }
    }

    if (port_power_cut()) {
        board_power_fail(&board_);
    } else {
        board_power_restored(&board_);
    }

    if (elapsed > 0) {
        last_ms_ = now;
        board_tick(&board_, elapsed);
        board_idle_work(&board_);              /* the long work of the memory, in a pass where nothing runs */
        ui_tick(&ui_, elapsed);
        port_poll();
    }
}
