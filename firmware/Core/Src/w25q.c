#include "w25q.h"

#define CMD_WRITE_ENABLE 0x06
#define CMD_READ_STATUS 0x05
#define CMD_READ 0x03
#define CMD_PAGE_PROGRAM 0x02
#define CMD_SECTOR_ERASE 0x20
#define CMD_RELEASE_POWER_DOWN 0xAB
#define CMD_JEDEC_ID 0x9F

#define SPI_TIMEOUT_MS 100
#define PROGRAM_TIMEOUT_MS 20      /* a page takes 3 ms at most */
#define ERASE_TIMEOUT_MS 800       /* a 4 KB sector takes 400 ms at most */

static SPI_HandleTypeDef *spi_;

static void select(void)
{
    HAL_GPIO_WritePin(ROM_CS_GPIO_Port, ROM_CS_Pin, GPIO_PIN_RESET);
}

static void deselect(void)
{
    HAL_GPIO_WritePin(ROM_CS_GPIO_Port, ROM_CS_Pin, GPIO_PIN_SET);
}

static int send(const uint8_t *data, uint16_t len)
{
    return HAL_SPI_Transmit(spi_, (uint8_t *)data, len, SPI_TIMEOUT_MS) == HAL_OK;
}

static int command(uint8_t cmd)
{
    int ok;

    select();
    ok = send(&cmd, 1);
    deselect();

    return ok;
}

static int address_command(uint8_t cmd, uint32_t addr)
{
    uint8_t head[4];

    head[0] = cmd;
    head[1] = (uint8_t)(addr >> 16);
    head[2] = (uint8_t)(addr >> 8);
    head[3] = (uint8_t)addr;

    return send(head, 4);
}

static int wait_ready(uint32_t timeout_ms)
{
    const uint32_t start = HAL_GetTick();
    uint8_t cmd = CMD_READ_STATUS;
    uint8_t status = 0xFF;

    do {
        select();
        if (!send(&cmd, 1) || HAL_SPI_Receive(spi_, &status, 1, SPI_TIMEOUT_MS) != HAL_OK) {
            deselect();

            return 0;
        }
        deselect();
        if (!(status & 0x01)) {
            return 1; /* the busy bit is clear */
        }
    } while (HAL_GetTick() - start < timeout_ms);

    return 0;
}

int w25q_init(SPI_HandleTypeDef *spi)
{
    uint8_t cmd = CMD_JEDEC_ID;
    uint8_t id[3] = {0, 0, 0};
    int ok;

    spi_ = spi;
    deselect();
    command(CMD_RELEASE_POWER_DOWN);
    HAL_Delay(1);
    select();
    ok = send(&cmd, 1) && HAL_SPI_Receive(spi_, id, 3, SPI_TIMEOUT_MS) == HAL_OK;
    deselect();

    return ok && !(id[0] == 0x00 && id[1] == 0x00 && id[2] == 0x00) && !(id[0] == 0xFF && id[1] == 0xFF && id[2] == 0xFF);
}

static int w_read(void *ctx, uint32_t addr, uint8_t *buf, uint32_t len)
{
    int ok;

    (void)ctx;
    select();
    ok = address_command(CMD_READ, addr) && HAL_SPI_Receive(spi_, buf, (uint16_t)len, SPI_TIMEOUT_MS) == HAL_OK;
    deselect();

    return ok;
}

static int w_program(void *ctx, uint32_t addr, const uint8_t *buf, uint32_t len)
{
    int ok;

    (void)ctx;
    if (!wait_ready(PROGRAM_TIMEOUT_MS) || !command(CMD_WRITE_ENABLE)) {
        return 0;
    }
    select();
    ok = address_command(CMD_PAGE_PROGRAM, addr) && send(buf, (uint16_t)len);
    deselect();

    return ok && wait_ready(PROGRAM_TIMEOUT_MS);
}

static int w_erase(void *ctx, uint32_t addr)
{
    int ok;

    (void)ctx;
    if (!wait_ready(PROGRAM_TIMEOUT_MS) || !command(CMD_WRITE_ENABLE)) {
        return 0;
    }
    select();
    ok = address_command(CMD_SECTOR_ERASE, addr);
    deselect();

    return ok && wait_ready(ERASE_TIMEOUT_MS);
}

const Flash *w25q_flash(void)
{
    static const Flash flash = {0, w_read, w_program, w_erase};

    return &flash;
}
