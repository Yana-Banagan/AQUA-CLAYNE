#include <Arduino.h>
#include "config.h"
#include "wifi_manager.h"
#include "rfid_manager.h"
#include "oled_manager.h"
#include "api_client.h"
#include "device_manager.h"

// Initialize system managers
WiFiManager wifiManager(DEFAULT_WIFI_SSID, DEFAULT_WIFI_PASS);
RFIDManager rfidManager(RC522_SS_PIN, RC522_RST_PIN);
OLEDManager oledManager(SCREEN_WIDTH, SCREEN_HEIGHT, OLED_RESET_PIN);
APIClient apiClient(API_BASE_URL, DEVICE_AUTH_TOKEN);
DeviceManager deviceManager(wifiManager, rfidManager, oledManager, apiClient);

void setup() {
    Serial.begin(115200);
    delay(1000);

    Serial.println("\n==========================================");
    Serial.println("  AQUA CLAYNE - ESP32 RFID SMART SCANNER  ");
    Serial.printf("  Device ID: %s | Firmware: v%s\n", DEVICE_CODE, FIRMWARE_VERSION);
    Serial.println("==========================================\n");

    oledManager.begin(OLED_I2C_ADDR);
    deviceManager.begin();
}

void loop() {
    deviceManager.loop();
    delay(10);
}

