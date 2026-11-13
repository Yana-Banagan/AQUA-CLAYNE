#ifndef DEVICE_MANAGER_H
#define DEVICE_MANAGER_H

#include <Arduino.h>
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
    WiFiManager& _wifi;
    RFIDManager& _rfid;
    OLEDManager& _oled;
    APIClient& _api;

    unsigned long _lastHeartbeat;
    unsigned long _lastSessionPoll;
    unsigned long _lastScanTime;
    String _lastScannedUID;

    // Active session state
    bool _hasActiveSession;
    String _activeSessionId;
    String _activeContainerCode;
    String _activeCustomerName;
    int _sessionExpiresIn;
    bool _wasWifiConnected;

    void handleHeartbeat();
    void handleSessionPoll();
    void handleRFIDScan();
    void beep(int ms = 80);
};

#endif // DEVICE_MANAGER_H

