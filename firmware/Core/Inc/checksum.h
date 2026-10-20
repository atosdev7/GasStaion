#ifndef CHECKSUM_H
#define CHECKSUM_H

#include <stddef.h>
#include <stdint.h>

/* CRC-16/Modbus (polynomial 0xA001 reflected, start 0xFFFF, no final xor). The CRC of "123456789" is 0x4B37. */
uint16_t crc16(const uint8_t *data, size_t len);

/* CRC-32 (the usual one: polynomial 0xEDB88320 reflected, start and final xor 0xFFFFFFFF) for the records in the flash. */
uint32_t crc32(const uint8_t *data, size_t len);

#endif
