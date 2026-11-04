#include "port.h"

#include "board_config.h"
#include "pinfilter.h"
#include "store.h"
#include "w25q.h"
#include "ui_drivers.h"

#include <string.h>

extern TIM_HandleTypeDef htim1;
extern RTC_HandleTypeDef hrtc;
extern SPI_HandleTypeDef hspi3;
extern UART_HandleTypeDef huart6;

/* ---------------------------------------------------------------- the flow meter */

/* TIM1 is the encoder counter of the flow meter (encoder mode on CH1 and CH2, both edges of A and B, so 4 counts for each cycle; the count goes down with a backflow). It is
   16 bit, so it is widened here to 32 bit: the main loop and the compare interrupt below both read it, so the widening runs with the interrupts off. The main loop must come round
   before 32767 counts have passed (at 30 ms a pass that is 1.09 million counts a second, far above any flow meter). */
static uint16_t last_count_;
static uint32_t total_count_;

static void port_pump(void *ctx, int on);

static uint32_t irq_off(void)
{
    const uint32_t primask = __get_PRIMASK();

    __disable_irq();

    return primask;
}

static void irq_restore(uint32_t primask)
{
    if (!primask) {
        __enable_irq();
    }
}

/* the widened count; the interrupts are off */
static uint32_t widen_locked(void)
{
    const uint16_t now = (uint16_t)__HAL_TIM_GET_COUNTER(&htim1);

    total_count_ += (uint32_t)(int32_t)(int16_t)(uint16_t)(now - last_count_);
    last_count_ = now;

    return total_count_;
}

static uint32_t port_pulses(void *ctx)
{
    uint32_t count;
    const uint32_t primask = irq_off();

    (void)ctx;
    count = widen_locked();
    irq_restore(primask);

    return count;
}

/* The hardware stop of the pump (Hal.arm_stop): the compare channel 3 of TIM1 (CH1 and CH2 are the encoder inputs; a compare works in the encoder mode and needs no pin)
   interrupts when the counter reaches the count of the stop, and the interrupt switches the pump off at once, whatever the main loop is doing (a pass lasts about 30 ms while
   the display and the keyboard are served). The counter is 16 bit: a stop that is more than STOP_WINDOW counts ahead is not armed in the compare yet, the core arms it again at
   every tick. */
#define STOP_WINDOW 30000L

static volatile uint8_t stop_armed_;
static uint32_t stop_at_;

/* sets the compare for the stop (or stops the pump when the count is there); the interrupts are off or this is the interrupt itself */
static void stop_compare_locked(void)
{
    const uint32_t now = widen_locked();
    const int32_t left = (int32_t)(stop_at_ - now);

    if (!stop_armed_) {
        TIM1->DIER &= ~TIM_DIER_CC3IE;

        return;
    }
    if (left <= 0) {
        port_pump(0, 0);                     /* the count is there: the pump is off now */
        stop_armed_ = 0;
        TIM1->DIER &= ~TIM_DIER_CC3IE;

        return;
    }
    if (left > STOP_WINDOW) {
        TIM1->DIER &= ~TIM_DIER_CC3IE;      /* too far for the 16 bit counter: armed when it comes near */

        return;
    }
    TIM1->CCR3 = (uint16_t)(last_count_ + (uint32_t)left);
    TIM1->SR = ~TIM_SR_CC3IF;
    TIM1->DIER |= TIM_DIER_CC3IE;
}

static void port_arm_stop(void *ctx, uint32_t at_count)
{
    const uint32_t primask = irq_off();

    (void)ctx;
    stop_at_ = at_count;
    stop_armed_ = 1;
    stop_compare_locked();
    irq_restore(primask);
}

static void port_disarm_stop(void *ctx)
{
    const uint32_t primask = irq_off();

    (void)ctx;
    stop_armed_ = 0;
    TIM1->DIER &= ~TIM_DIER_CC3IE;
    irq_restore(primask);
}

/* (here and not in stm32f4xx_it.c: CubeMX writes that file again) */
void TIM1_CC_IRQHandler(void)
{
    if (TIM1->SR & TIM_SR_CC3IF) {
        TIM1->SR = ~TIM_SR_CC3IF;
        stop_compare_locked();               /* the counter is at the compare value: the pump stops; a match that is not the stop (a jitter of the encoder) sets the compare again */
    }
}

/* ---------------------------------------------------------------- pump, hook */

static void port_pump(void *ctx, int on)
{
    const GPIO_PinState level = on ? RELAY_ON_LEVEL : (RELAY_ON_LEVEL == GPIO_PIN_SET ? GPIO_PIN_RESET : GPIO_PIN_SET);

    (void)ctx;
    HAL_GPIO_WritePin(RELAY1_GPIO_Port, RELAY1_Pin, level);
    HAL_GPIO_WritePin(RELAY2_GPIO_Port, RELAY2_Pin, level);
}

static PinFilter power_;
static PinFilter gun_;

/* the filtered state of the gun's switch (check_gun_status): 1 when the nozzle is lifted */
static int port_hook(void *ctx)
{
    (void)ctx;

    return gun_.state;
}

/* The timer: every INPUT_PICK_MS one pick of each pin goes into its buffer. It decides nothing and changes no state of the board (the state changes in check_*_status,
   in the main loop, where the board can save). It is the 1 ms tick of the HAL (SysTick) counted to INPUT_PICK_MS, so TIM6 and TIM7 stay free for the display and
   keyboard drivers. HAL_IncTick is weak in the HAL: this one does what it does and the picks. */
void HAL_IncTick(void)
{
    static uint8_t ms;

    uwTick += uwTickFreq;
    if (++ms >= INPUT_PICK_MS) {
        ms = 0;
        pin_filter_pick(&power_, HAL_GPIO_ReadPin(POWER_OFF_GPIO_Port, POWER_OFF_Pin) == POWER_CUT_LEVEL);
        pin_filter_pick(&gun_, HAL_GPIO_ReadPin(SWITCH_GPIO_Port, SWITCH_Pin) == HOOK_LIFTED_LEVEL);
    }
}

void check_power_status(void)
{
    pin_filter_check(&power_);
}

int port_power_cut(void)
{
    return power_.state;
}

void check_gun_status(void)
{
    pin_filter_check(&gun_);
}

/* ---------------------------------------------------------------- the clock, the serial number */

static uint8_t weekday_of(uint16_t year, uint8_t month, uint8_t day)
{
    static const uint8_t t[12] = {0, 3, 2, 5, 0, 3, 5, 1, 4, 6, 2, 4};
    uint16_t y = year;
    uint8_t w;

    if (month < 3) {
        y = (uint16_t)(y - 1);
    }
    w = (uint8_t)((y + y / 4 - y / 100 + y / 400 + t[month - 1] + day) % 7); /* 0 = Sunday */

    return w == 0 ? RTC_WEEKDAY_SUNDAY : w;
}

static int port_rtc_get(void *ctx, DateTime *out)
{
    RTC_TimeTypeDef t;
    RTC_DateTypeDef d;

    (void)ctx;
    if (HAL_RTC_GetTime(&hrtc, &t, RTC_FORMAT_BIN) != HAL_OK || HAL_RTC_GetDate(&hrtc, &d, RTC_FORMAT_BIN) != HAL_OK) { /* the date after the time */
        return 0;
    }
    out->year = (uint16_t)(2000 + d.Year);
    out->month = d.Month;
    out->day = d.Date;
    out->hour = t.Hours;
    out->minute = t.Minutes;
    out->second = t.Seconds;

    return 1;
}

static int port_rtc_set(void *ctx, const DateTime *in)
{
    RTC_TimeTypeDef t = {0};
    RTC_DateTypeDef d = {0};

    (void)ctx;
    t.Hours = in->hour;
    t.Minutes = in->minute;
    t.Seconds = in->second;
    t.TimeFormat = RTC_HOURFORMAT12_AM;
    t.DayLightSaving = RTC_DAYLIGHTSAVING_NONE;
    t.StoreOperation = RTC_STOREOPERATION_RESET;
    d.Year = (uint8_t)(in->year - 2000);
    d.Month = in->month;
    d.Date = in->day;
    d.WeekDay = weekday_of(in->year, in->month, in->day);

    return HAL_RTC_SetTime(&hrtc, &t, RTC_FORMAT_BIN) == HAL_OK && HAL_RTC_SetDate(&hrtc, &d, RTC_FORMAT_BIN) == HAL_OK;
}

static uint32_t port_serial(void *ctx)
{
    (void)ctx;

    return HAL_GetUIDw0() ^ HAL_GetUIDw1() ^ HAL_GetUIDw2();
}

/* ---------------------------------------------------------------- the memory */

static Store store_;

/* 1 when the flash answered as a 25Q32 and is not write protected (w25q_init): otherwise the memory is reported as damaged (fault 3) and the board does not work */
static int flash_ok_;

static int port_load(void *ctx, Persist *out)
{
    (void)ctx;
    if (!flash_ok_) {
        return -1;
    }

    return store_load(&store_, out);
}

static int port_save(void *ctx, const Persist *in)
{
    (void)ctx;

    return flash_ok_ && store_save(&store_, in);
}

static int port_save_now(void *ctx, const Persist *in)
{
    (void)ctx;

    return flash_ok_ && store_save_noerase(&store_, in);
}

/* the sector of the memory that the ring needs next is erased here, while nothing runs: 45 to 400 ms, a pass of the main loop that takes long, once in 64 saves */
static void port_housekeeping(void *ctx)
{
    (void)ctx;
    if (flash_ok_) {
        store_erase_ahead(&store_);
    }
}

/* ---------------------------------------------------------------- the RS-485 line */

/* The ring must hold what the line carries while the main loop is busy with the display and the keyboard (about 30 ms): at 115200 baud that is up to 350 bytes
   (every board's requests and answers pass here). A power of two. */
#define RX_RING 512

static volatile uint8_t tx_busy_;       /* an answer is going out (see the transmit part below) */
static void port_tx_finished(void);

static uint8_t rx_byte_;
static volatile uint8_t ring_[RX_RING];
static volatile uint16_t head_;
static volatile uint16_t tail_;

void HAL_UART_RxCpltCallback(UART_HandleTypeDef *huart)
{
    if (huart->Instance == USART6) {
        const uint16_t next = (uint16_t)((head_ + 1) % RX_RING);

        if (next != tail_) { /* a full ring drops the byte: the master asks again */
            ring_[head_] = rx_byte_;
            head_ = next;
        }
        HAL_UART_Receive_IT(&huart6, &rx_byte_, 1);
    }
}

void HAL_UART_ErrorCallback(UART_HandleTypeDef *huart)
{
    if (huart->Instance == USART6) {
        HAL_UART_Receive_IT(&huart6, &rx_byte_, 1); /* an overrun or a framing error: the byte is lost, the receiver goes on */
        if (tx_busy_ && huart->gState == HAL_UART_STATE_READY) {
            port_tx_finished();                     /* a DMA error ended the answer: the driver is switched off, the master asks again */
        }
    }
}

int port_rx_byte(void)
{
    int byte;

    if (tail_ == head_) {
        return -1;
    }
    byte = ring_[tail_];
    tail_ = (uint16_t)((tail_ + 1) % RX_RING);

    return byte;
}

/* The answer goes out by DMA (USART6_TX is DMA2 stream 6, channel 5): the main loop is not held while the bytes go. The driver of the line (RE and DE are one pin) is
   switched on before and off when the last bit has gone (the transmit-complete interrupt). The bytes are copied: the core reuses its answer buffer for the next frame. */
static DMA_HandleTypeDef hdma_tx_;
static uint8_t tx_buf_[PORT_TX_MAX];
static volatile uint16_t tx_dropped_;

static void port_tx_dma_init(void)
{
    __HAL_RCC_DMA2_CLK_ENABLE();
    hdma_tx_.Instance = DMA2_Stream6;
    hdma_tx_.Init.Channel = DMA_CHANNEL_5;
    hdma_tx_.Init.Direction = DMA_MEMORY_TO_PERIPH;
    hdma_tx_.Init.PeriphInc = DMA_PINC_DISABLE;
    hdma_tx_.Init.MemInc = DMA_MINC_ENABLE;
    hdma_tx_.Init.PeriphDataAlignment = DMA_PDATAALIGN_BYTE;
    hdma_tx_.Init.MemDataAlignment = DMA_MDATAALIGN_BYTE;
    hdma_tx_.Init.Mode = DMA_NORMAL;
    hdma_tx_.Init.Priority = DMA_PRIORITY_LOW;
    hdma_tx_.Init.FIFOMode = DMA_FIFOMODE_DISABLE;
    HAL_DMA_Init(&hdma_tx_);
    __HAL_LINKDMA(&huart6, hdmatx, hdma_tx_);
    HAL_NVIC_SetPriority(DMA2_Stream6_IRQn, 1, 0);   /* below the receive interrupt of the line */
    HAL_NVIC_EnableIRQ(DMA2_Stream6_IRQn);
}

/* (here and not in stm32f4xx_it.c: CubeMX writes that file again) */
void DMA2_Stream6_IRQHandler(void)
{
    HAL_DMA_IRQHandler(&hdma_tx_);
}

static void port_tx_finished(void)
{
    HAL_GPIO_WritePin(RS485_RE_GPIO_Port, RS485_RE_Pin, GPIO_PIN_RESET);   /* the driver off: the line is listened to again */
    tx_busy_ = 0;
}

void HAL_UART_TxCpltCallback(UART_HandleTypeDef *huart)
{
    if (huart->Instance == USART6) {
        port_tx_finished();
    }
}

int port_send(const uint8_t *data, size_t len)
{
    if (len == 0 || len > sizeof(tx_buf_) || tx_busy_) {
        ++tx_dropped_;           /* the master asks again, and a repeated request gets the same answer again */

        return 0;
    }
    memcpy(tx_buf_, data, len);
    tx_busy_ = 1;
    HAL_GPIO_WritePin(RS485_RE_GPIO_Port, RS485_RE_Pin, GPIO_PIN_SET);
    if (HAL_UART_Transmit_DMA(&huart6, tx_buf_, (uint16_t)len) != HAL_OK) {
        port_tx_finished();
        ++tx_dropped_;

        return 0;
    }

    return 1;
}

uint16_t port_tx_dropped(void)
{
    return tx_dropped_;
}

/* ---------------------------------------------------------------- beeper, LEDs */

static uint32_t beep_until_;
static uint8_t beeping_;

static void port_beep(void *ctx, uint16_t ms)
{
    (void)ctx;
    HAL_GPIO_WritePin(BEEP_GPIO_Port, BEEP_Pin, BEEP_ON_LEVEL);
    beep_until_ = HAL_GetTick() + ms;
    beeping_ = 1;
}

void port_poll(void)
{
    if (beeping_ && (int32_t)(HAL_GetTick() - beep_until_) >= 0) {
        HAL_GPIO_WritePin(BEEP_GPIO_Port, BEEP_Pin, BEEP_ON_LEVEL == GPIO_PIN_SET ? GPIO_PIN_RESET : GPIO_PIN_SET);
        beeping_ = 0;
    }
}

static void port_led(void *ctx, uint8_t index, int on)
{
    static GPIO_TypeDef *const ports[4] = {LED1_GPIO_Port, LED2_GPIO_Port, LED3_GPIO_Port, LED4_GPIO_Port};
    static const uint16_t pins[4] = {LED1_Pin, LED2_Pin, LED3_Pin, LED4_Pin};
    const GPIO_PinState lit = LED_ON_LEVEL;

    (void)ctx;
    if (index < 4) {
        HAL_GPIO_WritePin(ports[index], pins[index], on ? lit : (lit == GPIO_PIN_SET ? GPIO_PIN_RESET : GPIO_PIN_SET));
    }
}

/* the beep of the SELECT command (the Smart210 chose this nozzle) */
static void port_select_beep(void *ctx)
{
    port_beep(ctx, 150);
}

/* ---------------------------------------------------------------- the display and the keyboard (ui_drivers.h) */

static void port_display_update(void *ctx, const char *kg, const char *litres, const char *density, const char *keypad)
{
    (void)ctx;
    UpdateDisplay(kg, litres, density, keypad);
}

static void port_display_refresh(void *ctx)
{
    (void)ctx;
    RefreshDisplay();
}

static int port_key(void *ctx)
{
    (void)ctx;

    return ScanKeyboardAndRefreshDisplay();
}

/* ---------------------------------------------------------------- start up */

void port_init(Hal *hal, UiHal *ui)
{
    port_pump(0, 0); /* the pump is off before anything else */
    pin_filter_init(&power_, POWER_PICKS, 0);   /* the supply is on until the picks of the POWER_OFF input say it is cut */
    pin_filter_init(&gun_, GUN_PICKS, HAL_GPIO_ReadPin(SWITCH_GPIO_Port, SWITCH_Pin) == HOOK_LIFTED_LEVEL);   /* the gun starts as the pin says */

    HAL_TIM_Encoder_Start(&htim1, TIM_CHANNEL_ALL);
    last_count_ = (uint16_t)__HAL_TIM_GET_COUNTER(&htim1);
    total_count_ = 0;
    TIM1->CCMR2 &= ~(TIM_CCMR2_OC3M | TIM_CCMR2_CC3S);   /* channel 3: a compare that changes no pin (frozen), no input */
    TIM1->CCER &= ~TIM_CCER_CC3E;
    HAL_NVIC_SetPriority(TIM1_CC_IRQn, 0, 0);            /* the pump stop is the most urgent thing on the board */
    HAL_NVIC_EnableIRQ(TIM1_CC_IRQn);

    port_tx_dma_init();
    HAL_UART_Receive_IT(&huart6, &rx_byte_, 1);

    flash_ok_ = w25q_init(&hspi3);
    store_init(&store_, w25q_flash(), STORE_BASE, STORE_AUTOFORMAT);

    hal->ctx = 0;
    hal->load = port_load;
    hal->save = port_save;
    hal->save_now = port_save_now;
    hal->housekeeping = port_housekeeping;
    hal->pulses = port_pulses;
    hal->pump = port_pump;
    hal->arm_stop = port_arm_stop;
    hal->disarm_stop = port_disarm_stop;
    hal->hook = port_hook;
    hal->beep = port_select_beep;
    hal->rtc_get = port_rtc_get;
    hal->rtc_set = port_rtc_set;
    hal->serial = port_serial;

    ui->ctx = 0;
    ui->update = port_display_update;
    ui->refresh = port_display_refresh;
    ui->scan = port_key;
    ui->beep = port_beep;
    ui->led = port_led;
}
