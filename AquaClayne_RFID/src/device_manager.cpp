#include "device_manager.h"
#include <esp_system.h>
#if __has_include("config.h")
#include "config.h"
#elif __has_include("../include/config.h")
#include "../include/config.h"
#else
#error "config.h not found. Check the project include path."
#endif

static String makeScanId();
static bool isRetryableError(const String& error);

DeviceManager::DeviceManager(
    WiFiManager& wifi,
    RFIDManager& rfid,
    OLEDManager& oled,
    APIClient& api
) : _wifi(wifi), _rfid(rfid), _oled(oled), _api(api),
    _lastHeartbeat(0), _lastSessionPoll(0), _hasActiveSession(false),
    _activeDeviceId(""), _activeSessionId(""), _activeContainerCode(""),
    _activeCustomerName(""), _sessionExpiresIn(0),
    _wasWifiConnected(false) {}

void DeviceManager::begin() {
    pinMode(BUZZER_1_PIN, OUTPUT);
    pinMode(BUZZER_2_PIN, OUTPUT);
    digitalWrite(BUZZER_1_PIN, LOW);
    digitalWrite(BUZZER_2_PIN, LOW);

    _oled.setState(STATE_BOOTING);
    _wifi.begin();

    _oled.setState(STATE_CONNECTING_WIFI, DEFAULT_WIFI_SSID);
    unsigned long startWait = millis();
    while (!_wifi.isConnected() && millis() - startWait < 12000) {
        delay(250);
        _wifi.loop();
    }

    _rfid.begin();
    _scanQueue = xQueueCreate(8, sizeof(RFIDScanEvent));
    if (!_scanQueue) {
        Serial.println("[RFID] Failed to create scan queue.");
        _oled.showCustom("RFID QUEUE FAILED", "Restart device");
        return;
    }

    _wasWifiConnected = _wifi.isConnected();
    if (_wifi.isConnected()) {
        _oled.setState(STATE_WIFI_CONNECTED, _wifi.getIP());
        delay(1200);
        _oled.setState(STATE_DEVICE_ONLINE);
        beep(100);
    } else {
        _oled.setState(STATE_WIFI_DISCONNECTED);
    }

    BaseType_t scanTaskStarted = xTaskCreatePinnedToCore(
        rfidScanTask,
        "rfid_scan",
        4096,
        this,
        2,
        nullptr,
        1
    );
    if (scanTaskStarted != pdPASS) {
        Serial.println("[RFID] Failed to start continuous reader polling task.");
        _oled.showCustom("RFID POLL FAILED", "Restart device");
        vQueueDelete(_scanQueue);
        _scanQueue = nullptr;
    }
}

void DeviceManager::beep(int ms) {
    digitalWrite(BUZZER_1_PIN, HIGH);
    digitalWrite(BUZZER_2_PIN, HIGH);
    delay(ms);
    digitalWrite(BUZZER_1_PIN, LOW);
    digitalWrite(BUZZER_2_PIN, LOW);
}

void DeviceManager::loop() {
    _wifi.loop();

    if (!_wifi.isConnected()) {
        if (_wasWifiConnected) {
            Serial.println("[WiFi] Connection lost.");
            _wasWifiConnected = false;
            _oled.setState(STATE_WIFI_DISCONNECTED);
        }
        return;
    }

    if (!_wasWifiConnected) {
        _wasWifiConnected = true;
        Serial.printf("[WiFi] Reconnected. IP: %s\n", _wifi.getIP().c_str());
        _oled.setState(STATE_DEVICE_ONLINE);
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

        String deviceId, sessionId, containerCode, customerName, err;
        int expiresIn = 0;
        bool found = _api.checkActiveSession(deviceId, sessionId, containerCode, customerName, expiresIn, err);

        if (found && sessionId.length() > 0 && deviceId.length() > 0) {
            if (!_hasActiveSession || _activeSessionId != sessionId) {
                // New registration session started on admin dashboard!
                _activeDeviceId = deviceId;
                _activeSessionId = sessionId;
                _activeContainerCode = containerCode;
                _activeCustomerName = customerName;
                _sessionExpiresIn = expiresIn;
                taskENTER_CRITICAL(&_scanMux);
                _hasActiveSession = true;
                sessionId.toCharArray(_scanSessionId, sizeof(_scanSessionId));
                deviceId.toCharArray(_scanDeviceId, sizeof(_scanDeviceId));
                taskEXIT_CRITICAL(&_scanMux);

                Serial.printf("[Registration] Active session: %s for %s (%s)\n",
                    sessionId.c_str(), containerCode.c_str(), customerName.c_str());

                _oled.setState(STATE_READY_TO_SCAN, containerCode);
                beep(80);
                delay(60);
                beep(80);
            }
        } else if (err.length() == 0) {
            if (_hasActiveSession) {
                // Session expired or completed
                _hasActiveSession = false;
                _activeDeviceId = "";
                _activeSessionId = "";
                taskENTER_CRITICAL(&_scanMux);
                _hasActiveSession = false;
                _scanSessionId[0] = '\0';
                _scanDeviceId[0] = '\0';
                taskEXIT_CRITICAL(&_scanMux);
                _oled.setState(STATE_DEVICE_ONLINE);
            }
        } else {
            Serial.printf("[Registration Poll] Failed: %s\n", err.c_str());
            if (err == "UNAUTHORIZED") {
                _oled.setState(STATE_UNAUTHORIZED);
            }
        }
    }
}

void DeviceManager::handleRFIDScan() {
    if (!_scanQueue || millis() < _retryScanAt) {
        return;
    }

    RFIDScanEvent event = {};
    if (xQueuePeek(_scanQueue, &event, 0) != pdTRUE || event.uid[0] == '\0') {
        return;
    }

    String uid(event.uid);

    Serial.printf("[RFID] Physical tag scanned: %s\n", uid.c_str());
    beep(60);

    if (event.registrationMode) {
        // Submit registration scan
        _oled.setState(STATE_REGISTERING_TAG);
        String msg, err;
        Serial.println("[RFID] Registration session active");
        Serial.println("[RFID] Sending registration scan to Laravel");
        bool success = _api.submitRegistrationScan(String(event.deviceId), String(event.sessionId), uid, msg, err);

        if (success) {
            finishPendingScan();
            Serial.printf("[RFID] Registration successful. UID: %s\n", uid.c_str());
            _oled.setState(STATE_REGISTER_SUCCESS, uid);
            beep(150);
            beep(150);
            _activeDeviceId = "";
            _activeSessionId = "";
            taskENTER_CRITICAL(&_scanMux);
            _hasActiveSession = false;
            _scanSessionId[0] = '\0';
            _scanDeviceId[0] = '\0';
            taskEXIT_CRITICAL(&_scanMux);
        } else {
            Serial.printf("[Registration] Failed (%s): %s\n", err.c_str(), msg.c_str());
            if (isRetryableError(err)) {
                _retryScanAt = millis() + 2000;
                _oled.showCustom("COMMUNICATION", "FAILURE", err.substring(0, 20));
                return;
            }
            finishPendingScan();
            if (err == "DUPLICATE_RFID_TAG") {
                _oled.setState(STATE_DUPLICATE_TAG);
            } else if (err == "SESSION_EXPIRED") {
                _oled.setState(STATE_SESSION_EXPIRED);
            } else {
                _oled.showCustom("REGISTRATION FAILED", msg.length() ? msg : err, "");
            }
            beep(400);
            if (err == "SESSION_EXPIRED" || err.startsWith("SESSION_")) {
                _activeDeviceId = "";
                _activeSessionId = "";
                taskENTER_CRITICAL(&_scanMux);
                _hasActiveSession = false;
                _scanSessionId[0] = '\0';
                _scanDeviceId[0] = '\0';
                taskEXIT_CRITICAL(&_scanMux);
            } else {
                _oled.setState(STATE_READY_TO_SCAN, _activeContainerCode);
            }
        }
    } else {
        // Operational scan (Station Intake / Verification)
        String msg, customerName, err;
        int gallonCount = 0;
        Serial.println("[RFID] Sending verification scan to Laravel");
        bool success = _api.submitOperationalScan(uid, "VERIFICATION", "", String(event.scanId), msg, customerName, gallonCount, err);
        if (!success && isRetryableError(err)) {
            _retryScanAt = millis() + 2000;
            Serial.printf("[RFID] Scan retained for retry: %s\n", err.c_str());
            _oled.showCustom("COMMUNICATION", "FAILURE", err.substring(0, 20));
            return;
        }
        finishPendingScan();
        if (success) {
            const String gallonLabel = gallonCount == 1 ? " Gallon" : " Gallons";
            _oled.showCustom("Customer Name:", customerName, "Number of Gallons: " + String(gallonCount) + gallonLabel);
            beep(100);
        } else if (err == "TAG_NOT_FOUND") {
            _oled.showCustom("RFID TAG NOT", "REGISTERED");
            beep(300);
        } else if (err == "CUSTOMER_DETAILS_NOT_FOUND") {
            _oled.showCustom("Customer or gallon", "details unavailable.");
            beep(300);
        } else {
            _oled.showCustom("SCAN REJECTED", msg.length() ? msg : err);
            beep(300);
        }
    }
}

void DeviceManager::finishPendingScan() {
    RFIDScanEvent completed = {};
    if (_scanQueue) {
        xQueueReceive(_scanQueue, &completed, 0);
    }
    _retryScanAt = 0;
}

static String makeScanId() {
    char scanId[37];
    const uint32_t first = esp_random();
    const uint32_t second = esp_random();
    const uint32_t third = esp_random();
    const uint16_t fourth = static_cast<uint16_t>(esp_random());
    snprintf(scanId, sizeof(scanId), "%08lx-%04x-4%03x-%04x-%08lx%04x",
        static_cast<unsigned long>(first),
        static_cast<unsigned int>(second >> 16),
        static_cast<unsigned int>(second & 0x0fff),
        static_cast<unsigned int>((third >> 16) & 0x3fff) | 0x8000,
        static_cast<unsigned long>(third),
        static_cast<unsigned int>(fourth));
    return String(scanId);
}

static bool isRetryableError(const String& error) {
    return error.startsWith("NETWORK_ERROR") || error.startsWith("HTTP_5");
}

void DeviceManager::rfidScanTask(void* parameter) {
    DeviceManager* manager = static_cast<DeviceManager*>(parameter);

    for (;;) {
        if (manager->_rfid.isTagPresent()) {
            String uid = manager->_rfid.readUID();
            if (uid.length() > 0) {
                RFIDScanEvent event = {};
                uid.toCharArray(event.uid, sizeof(event.uid));
                makeScanId().toCharArray(event.scanId, sizeof(event.scanId));
                taskENTER_CRITICAL(&manager->_scanMux);
                event.registrationMode = manager->_hasActiveSession;
                if (event.registrationMode) {
                    memcpy(event.deviceId, manager->_scanDeviceId, sizeof(event.deviceId));
                    memcpy(event.sessionId, manager->_scanSessionId, sizeof(event.sessionId));
                }
                taskEXIT_CRITICAL(&manager->_scanMux);

                Serial.println("[RFID] Card detected");
                Serial.printf("[RFID] UID read successfully: %s\n", uid.c_str());
                if (xQueueSend(manager->_scanQueue, &event, 0) != pdTRUE) {
                    Serial.println("[RFID] Scan queue full; physical scan could not be queued.");
                }
                while (manager->_rfid.isCardStillPresent()) {
                    vTaskDelay(pdMS_TO_TICKS(50));
                }
            }
        }

        vTaskDelay(pdMS_TO_TICKS(2));
    }
}

