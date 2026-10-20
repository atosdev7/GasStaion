/* USER CODE BEGIN Header */
/**
  ******************************************************************************
  * @file           : main.h
  * @brief          : Header for main.c file.
  *                   This file contains the common defines of the application.
  ******************************************************************************
  * @attention
  *
  * Copyright (c) 2026 STMicroelectronics.
  * All rights reserved.
  *
  * This software is licensed under terms that can be found in the LICENSE file
  * in the root directory of this software component.
  * If no LICENSE file comes with this software, it is provided AS-IS.
  *
  ******************************************************************************
  */
/* USER CODE END Header */

/* Define to prevent recursive inclusion -------------------------------------*/
#ifndef __MAIN_H
#define __MAIN_H

#ifdef __cplusplus
extern "C" {
#endif

/* Includes ------------------------------------------------------------------*/
#include "stm32f4xx_hal.h"

/* Private includes ----------------------------------------------------------*/
/* USER CODE BEGIN Includes */

/* USER CODE END Includes */

/* Exported types ------------------------------------------------------------*/
/* USER CODE BEGIN ET */

/* USER CODE END ET */

/* Exported constants --------------------------------------------------------*/
/* USER CODE BEGIN EC */

/* USER CODE END EC */

/* Exported macro ------------------------------------------------------------*/
/* USER CODE BEGIN EM */

/* USER CODE END EM */

/* Exported functions prototypes ---------------------------------------------*/
void Error_Handler(void);

/* USER CODE BEGIN EFP */

/* USER CODE END EFP */

/* Private defines -----------------------------------------------------------*/
#define RELAY2_Pin GPIO_PIN_2
#define RELAY2_GPIO_Port GPIOE
#define RELAY1_Pin GPIO_PIN_3
#define RELAY1_GPIO_Port GPIOE
#define KEY_DATA_Pin GPIO_PIN_4
#define KEY_DATA_GPIO_Port GPIOE
#define KEY_CLK_Pin GPIO_PIN_5
#define KEY_CLK_GPIO_Port GPIOE
#define KEY_SEL_Pin GPIO_PIN_6
#define KEY_SEL_GPIO_Port GPIOE
#define BEEP_Pin GPIO_PIN_0
#define BEEP_GPIO_Port GPIOC
#define SWITCH_Pin GPIO_PIN_0
#define SWITCH_GPIO_Port GPIOA
#define LCD_SEL_Pin GPIO_PIN_4
#define LCD_SEL_GPIO_Port GPIOA
#define LCD_CLK_Pin GPIO_PIN_5
#define LCD_CLK_GPIO_Port GPIOA
#define LCD_DATA_Pin GPIO_PIN_6
#define LCD_DATA_GPIO_Port GPIOA
#define LED1_Pin GPIO_PIN_12
#define LED1_GPIO_Port GPIOE
#define LED2_Pin GPIO_PIN_13
#define LED2_GPIO_Port GPIOE
#define LED3_Pin GPIO_PIN_14
#define LED3_GPIO_Port GPIOE
#define LED4_Pin GPIO_PIN_15
#define LED4_GPIO_Port GPIOE
#define POWER_OFF_Pin GPIO_PIN_14
#define POWER_OFF_GPIO_Port GPIOB
#define RS485_TX_Pin GPIO_PIN_6
#define RS485_TX_GPIO_Port GPIOC
#define RS485_RX_Pin GPIO_PIN_7
#define RS485_RX_GPIO_Port GPIOC
#define RS485_RE_Pin GPIO_PIN_8
#define RS485_RE_GPIO_Port GPIOC
#define ROM_CS_Pin GPIO_PIN_15
#define ROM_CS_GPIO_Port GPIOA
#define RELAY7_Pin GPIO_PIN_7
#define RELAY7_GPIO_Port GPIOB
#define RELAY6_Pin GPIO_PIN_8
#define RELAY6_GPIO_Port GPIOB
#define RELAY5_Pin GPIO_PIN_9
#define RELAY5_GPIO_Port GPIOB
#define RELAY4_Pin GPIO_PIN_0
#define RELAY4_GPIO_Port GPIOE
#define RELAY3_Pin GPIO_PIN_1
#define RELAY3_GPIO_Port GPIOE

/* USER CODE BEGIN Private defines */

/* USER CODE END Private defines */

#ifdef __cplusplus
}
#endif

#endif /* __MAIN_H */
