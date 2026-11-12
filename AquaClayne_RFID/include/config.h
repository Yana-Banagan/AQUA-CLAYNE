#ifndef CONFIG_H
#define CONFIG_H

#include <Arduino.h>
#include "secrets.h"

// =============================================================================
// NETWORK & SERVER CONFIGURATION
// =============================================================================
// Update with local Wi-Fi credentials during physical setup
#define DEFAULT_WIFI_SSID       WIFI_SSID
#define DEFAULT_WIFI_PASS       WIFI_PASSWORD

// Aqua Clayne Backend Base URL
// In development, use local computer IP: e.g. "http://192.168.1.108:8000"
// In production, use HTTPS URL: e.g. "https://aqua-clayne-production.up.railway.app"
#define API_BASE_URL            "http://192.168.1.104:8000"

// Device Authentication Token (hashed on Laravel backend)
// Corresponds to seeded device 'ESP32-STA-01' in rfid_devices table
#define DEVICE_CODE             "ESP32-STA-01"
#define FIRMWARE_VERSION        "1.0.0"

// Request timeouts & intervals (in milliseconds)
#define HTTP_TIMEOUT_MS         5000
#define HEARTBEAT_INTERVAL_MS   15000   // Send heartbeat every 15 seconds
#define SESSION_POLL_MS         1500    // Poll for active registration session every 1.5s
#define SCAN_DEBOUNCE_MS        2500    // Prevent duplicate triggers for 2.5s

// =============================================================================
// HARDWARE PIN DEFINITIONS (ESP32 DEVKIT V1)
// =============================================================================
// RC522 RFID Reader (SPI Interface)
#define RC522_SS_PIN            5       // SDA / SS on RC522
#define RC522_RST_PIN           27      // RST on RC522
#define RC522_SCK_PIN           18      // SCK on RC522
#define RC522_MISO_PIN          19      // MISO on RC522
#define RC522_MOSI_PIN          23      // MOSI on RC522

// SSD1306 0.96" OLED Display (I2C Interface)
#define OLED_SDA_PIN            21      // I2C Data
#define OLED_SCL_PIN            22      // I2C Clock
#define OLED_I2C_ADDR           0x3C    // Standard I2C address for 128x64 OLED
#define SCREEN_WIDTH            128
#define SCREEN_HEIGHT           64
#define OLED_RESET_PIN          -1      // Reset pin (-1 if sharing Arduino reset pin)

// LCD I2C display (shares the bus with the OLED at a different address)
#define LCD_SDA_PIN             21
#define LCD_SCL_PIN             22
#define LCD_I2C_ADDR            0x27
#define LCD_COLUMNS             16
#define LCD_ROWS                2

// Status feedback hardware
#define BUZZER_1_PIN            25
#define BUZZER_2_PIN            26
#define GREEN_LED_PIN           32
#define RED_LED_PIN             33

#endif // CONFIG_H

