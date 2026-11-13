#include "wifi_manager.h"
#include <WiFi.h>

WiFiManager::WiFiManager(const char* ssid, const char* password)
    : _ssid(ssid), _password(password), _lastReconnectAttempt(0) {}

namespace {
const char* wifiStatusName(wl_status_t status) {
    switch (status) {
        case WL_IDLE_STATUS: return "idle";
        case WL_NO_SSID_AVAIL: return "SSID not found";
        case WL_SCAN_COMPLETED: return "scan completed";
        case WL_CONNECTED: return "connected";
        case WL_CONNECT_FAILED: return "connection failed";
        case WL_CONNECTION_LOST: return "connection lost";
        case WL_DISCONNECTED: return "disconnected";
        default: return "unknown";
    }
}
}

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
            wl_status_t status = WiFi.status();
            Serial.printf("[WiFi] %s (status %d). Attempting reconnect...\n",
                wifiStatusName(status), static_cast<int>(status));
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

