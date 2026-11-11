#include "wifi_manager.h"
#include <WiFi.h>

WiFiManager::WiFiManager(const char* ssid, const char* password)
    : _ssid(ssid), _password(password), _lastReconnectAttempt(0) {}

void WiFiManager::begin() {
    Serial.printf("[WiFi] Initializing connection to SSID: %s\n", _ssid);
    WiFi.mode(WIFI_STA);
    WiFi.setAutoReconnect(true);
    connect();
}

void WiFiManager::connect() {
    WiFi.begin(_ssid, _password);
    _lastReconnectAttempt = millis();
}

bool WiFiManager::isConnected() {
    return WiFi.status() == WL_CONNECTED;
}

void WiFiManager::loop() {
    if (!isConnected()) {
        unsigned long now = millis();
        if (now - _lastReconnectAttempt > 10000) {
            Serial.println("[WiFi] Connection lost. Attempting reconnect...");
            connect();
        }
    }
}

String WiFiManager::getIP() {
    return isConnected() ? WiFi.localIP().toString() : "0.0.0.0";
}

int WiFiManager::getRSSI() {
    return isConnected() ? WiFi.RSSI() : 0;
}

