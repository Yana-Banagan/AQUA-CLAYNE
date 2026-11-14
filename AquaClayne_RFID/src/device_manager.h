#ifndef DEVICE_MANAGER_H
#define DEVICE_MANAGER_H

#include <Arduino.h>
#include <freertos/FreeRTOS.h>
#include <freertos/queue.h>
#include "wifi_manager.h"
#include "rfid_manager.h"
#include "oled_manager.h"
#include "api_client.h"

class DeviceManager {
public:
    DeviceManager(
        WiFiManager& wifi,
        RFIDManager& rfid,
        OLEDManager& oled,
        APIClient& api
    );

    void begin();
    void loop();

private:
    struct RFIDScanEvent {
        char uid[21];
        char deviceId[37];
        char sessionId[37];
        char scanId[37];
        bool registrationMode;
    };

    WiFiManager& _wifi;
    RFIDManager& _rfid;
    OLEDManager& _oled;
    APIClient& _api;

    unsigned long _lastHeartbeat;
    unsigned long _lastSessionPoll;
    unsigned long _retryScanAt = 0;
    unsigned long _heartbeatRetryInterval;
    unsigned long _sessionPollInterval;
    unsigned long _scanRetryInterval;
    unsigned long _lastStackReport;
    QueueHandle_t _scanQueue = nullptr;
    portMUX_TYPE _scanMux = portMUX_INITIALIZER_UNLOCKED;
    char _scanDeviceId[37] = {};
    char _scanSessionId[37] = {};

    // Active session state
    bool _hasActiveSession;
    String _activeDeviceId;
    String _activeSessionId;
    String _activeContainerCode;
    String _activeCustomerName;
    int _sessionExpiresIn;
    bool _wasWifiConnected;
    bool _heartbeatAuthenticated;

    void handleHeartbeat();
    void handleSessionPoll();
    void handleRFIDScan();
    void finishPendingScan();
    void clearActiveSession();
    bool hasActiveSession();
    void reportLoopStack();
    static void rfidScanTask(void* parameter);
    void beep(int ms = 80);
};

#endif // DEVICE_MANAGER_H

