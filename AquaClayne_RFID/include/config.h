#ifndef CONFIG_H
#define CONFIG_H

#include <Arduino.h>

#if __has_include("firmware_secrets.h")
#include "firmware_secrets.h"
#elif __has_include("secrets.h")
#include "secrets.h"
#else
#error "Missing secret configuration file. Copy include/secrets.example.h to include/firmware_secrets.h or include/secrets.h."
#endif

// =============================================================================
// NETWORK & SERVER CONFIGURATION
// =============================================================================
#define DEFAULT_WIFI_SSID WIFI_SSID
#define DEFAULT_WIFI_PASS WIFI_PASSWORD

// Aqua Clayne Backend Base URL
// In development, use local computer IP: e.g. "http://192.168.1.108:8000"
// In production, use HTTPS URL: e.g. "https://aqua-clayne-production.up.railway.app"
#define API_BASE_URL "https://aqua-clayne-production.up.railway.app"

#define DEVICE_CODE "ESP32-STA-01"

#define FIRMWARE_VERSION "1.0.0"

// Request timeouts & intervals (in milliseconds)
#define HTTP_TIMEOUT_MS 5000
#define HEARTBEAT_INTERVAL_MS 15000 // Send heartbeat every 15 seconds
#define SESSION_POLL_MS 1500        // Poll for active registration session every 1.5s
#define SCAN_DEBOUNCE_MS 2500       // Prevent duplicate triggers for 2.5s

// =============================================================================
// HARDWARE PIN DEFINITIONS (ESP32 DEVKIT V1)
// =============================================================================
// RC522 RFID Reader (SPI Interface)
#define RFID_SS 5    // RC522 SDA / SS
#define RFID_RST 27
#define RFID_SCK 18
#define RFID_MISO 19
#define RFID_MOSI 23

// Keep the names used by the firmware's RFIDManager.
#define RC522_SS_PIN RFID_SS
#define RC522_RST_PIN RFID_RST
#define RC522_SCK_PIN RFID_SCK
#define RC522_MISO_PIN RFID_MISO
#define RC522_MOSI_PIN RFID_MOSI

// SSD1306 I2C OLED. Power the module from ESP32 3V3; share GND.
#define OLED_SDA_PIN 21
#define OLED_SCL_PIN 22
#define OLED_I2C_ADDR 0x3C
#define SCREEN_WIDTH 128
#define SCREEN_HEIGHT 64
#define OLED_RESET_PIN -1 // Reset pin (-1 if sharing Arduino reset pin)

// Status outputs (active-high LEDs and buzzers).
#define BUZZER_1 25
#define BUZZER_2 26
#define GREEN_LED 32
#define RED_LED 33
#define BUZZER_1_PIN BUZZER_1
#define BUZZER_2_PIN BUZZER_2
#define GREEN_LED_PIN GREEN_LED
#define RED_LED_PIN RED_LED

#endif // CONFIG_H
