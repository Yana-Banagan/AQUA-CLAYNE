#include "device_manager.h"
#include "config.h"

DeviceManager::DeviceManager(
    WiFiManager& wifi,
    RFIDManager& rfid,
    OLEDManager& oled,
    APIClient& api
) : _wifi(wifi), _rfid(rfid), _oled(oled), _api(api),
    _lastHeartbeat(0), _lastSessionPoll(0), _lastScanTime(0),
    _lastScannedUID(""), _hasActiveSession(false),
    _activeSessionId(""), _activeContainerCode(""),
    _activeCustomerName(""), _sessionExpiresIn(0) {}

void DeviceManager::begin() {
    pinMode(BUZZER_PIN, OUTPUT);
    digitalWrite(BUZZER_PIN, LOW);

    _oled.setState(STATE_BOOTING);
    _wifi.begin();

    _oled.setState(STATE_CONNECTING_WIFI, DEFAULT_WIFI_SSID);
    unsigned long startWait = millis();
    while (!_wifi.isConnected() && millis() - startWait < 12000) {
        delay(250);
        _wifi.loop();
    }

    if (_wifi.isConnected()) {
        _oled.setState(STATE_WIFI_CONNECTED, _wifi.getIP());
        delay(1200);
        _rfid.begin();
        _oled.setState(STATE_DEVICE_ONLINE);
        beep(100);
    } else {
        _oled.setState(STATE_WIFI_DISCONNECTED);
    }
}

void DeviceManager::beep(int ms) {
    digitalWrite(BUZZER_PIN, HIGH);
    delay(ms);
    digitalWrite(BUZZER_PIN, LOW);
}

void DeviceManager::loop() {
    _wifi.loop();

    if (!_wifi.isConnected()) {
        _oled.setState(STATE_WIFI_DISCONNECTED);
        return;
    }

    handleHeartbeat();
    handleSessionPoll();
    handleRFIDScan();
}

void DeviceManager::handleHeartbeat() {
    unsigned long now = millis();
    if (now - _lastHeartbeat >= HEARTBEAT_INTERVAL_MS) {
        _lastHeartbeat = now;
        String err = "";
        bool ok = _api.sendHeartbeat(FIRMWARE_VERSION, err);
        if (!ok) {
            Serial.printf("[Heartbeat] Failed: %s\n", err.c_str());
            if (err == "UNAUTHORIZED") {
                _oled.setState(STATE_UNAUTHORIZED);
            }
        }
    }
}

void DeviceManager::handleSessionPoll() {
    unsigned long now = millis();
    if (now - _lastSessionPoll >= SESSION_POLL_MS) {
        _lastSessionPoll = now;

        String sessionId, containerCode, customerName, err;
        int expiresIn = 0;
        bool found = _api.checkActiveSession(sessionId, containerCode, customerName, expiresIn, err);

        if (found && sessionId.length() > 0) {
            if (!_hasActiveSession || _activeSessionId != sessionId) {
                // New registration session started on admin dashboard!
                _hasActiveSession = true;
                _activeSessionId = sessionId;
                _activeContainerCode = containerCode;
                _activeCustomerName = customerName;
                _sessionExpiresIn = expiresIn;

                Serial.printf("[Registration] Active session: %s for %s (%s)\n",
                    sessionId.c_str(), containerCode.c_str(), customerName.c_str());

                _oled.setState(STATE_READY_TO_SCAN, containerCode);
                beep(80);
                delay(60);
                beep(80);
            }
        } else {
            if (_hasActiveSession) {
                // Session expired or completed
                _hasActiveSession = false;
                _activeSessionId = "";
                _oled.setState(STATE_DEVICE_ONLINE);
            }
        }
    }
}

void DeviceManager::handleRFIDScan() {
    if (!_rfid.isTagPresent()) {
        return;
    }

    String uid = _rfid.readUID();
    if (uid.length() == 0) {
        return;
    }

    unsigned long now = millis();
    // Debounce duplicate scans
    if (uid == _lastScannedUID && (now - _lastScanTime < SCAN_DEBOUNCE_MS)) {
        return;
    }
    _lastScanTime = now;
    _lastScannedUID = uid;

    Serial.printf("[RFID] Physical Tag Scanned: %s\n", uid.c_str());
    beep(60);
    _oled.setState(STATE_RFID_DETECTED, uid);

    if (_hasActiveSession && _activeSessionId.length() > 0) {
        // Submit registration scan
        _oled.setState(STATE_REGISTERING_TAG);
        String msg, err;
        bool success = _api.submitRegistrationScan(_activeSessionId, uid, msg, err);

        if (success) {
            Serial.printf("[Registration] Tag %s registered successfully!\n", uid.c_str());
            _oled.setState(STATE_REGISTER_SUCCESS, uid);
            beep(150);
            delay(100);
            beep(150);
            _hasActiveSession = false;
            _activeSessionId = "";
            delay(3500);
            _oled.setState(STATE_DEVICE_ONLINE);
        } else {
            Serial.printf("[Registration Error] %s: %s\n", err.c_str(), msg.c_str());
            if (err == "DUPLICATE_RFID_TAG") {
                _oled.setState(STATE_DUPLICATE_TAG);
            } else if (err == "SESSION_EXPIRED") {
                _oled.setState(STATE_SESSION_EXPIRED);
            } else {
                _oled.showCustom("REGISTRATION FAILED", err, msg.substring(0, 20));
            }
            beep(400);
            delay(3000);
            _oled.setState(STATE_READY_TO_SCAN, _activeContainerCode);
        }
    } else {
        // Operational scan (Station Intake / Verification)
        String msg, err;
        bool success = _api.submitOperationalScan(uid, "VERIFICATION", "", msg, err);
        if (success) {
            _oled.showCustom("RFID VERIFIED", uid, "Active Container");
            beep(100);
        } else {
            _oled.showCustom("SCAN REJECTED", uid, err.substring(0, 20));
            beep(300);
        }
        delay(2500);
        _oled.setState(STATE_DEVICE_ONLINE);
    }
}

