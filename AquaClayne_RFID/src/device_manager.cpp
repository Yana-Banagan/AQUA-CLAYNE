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
    _lastHeartbeat(0), _lastSessionPoll(0),
    _heartbeatRetryInterval(HEARTBEAT_INTERVAL_MS),
    _sessionPollInterval(SESSION_POLL_MS),
    _scanRetryInterval(RFID_RETRY_INITIAL_MS),
    _lastStackReport(0),
    _hasActiveSession(false),
    _activeDeviceId(""), _activeSessionId(""), _activeContainerCode(""),
    _activeCustomerName(""), _sessionExpiresIn(0),
    _wasWifiConnected(false), _heartbeatAuthenticated(false),
    _sessionStateKnown(false) {}

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
    _scanQueue = xQueueCreate(SCAN_QUEUE_CAPACITY, sizeof(RFIDScanEvent));
    if (!_scanQueue) {
        Serial.println("[RFID] Failed to create scan queue.");
        _oled.showCustom("RFID QUEUE FAILED", "Restart device");
        return;
    }

    _wasWifiConnected = _wifi.isConnected();
    _lastHeartbeat = millis() - HEARTBEAT_INTERVAL_MS;
    _lastSessionPoll = millis() - SESSION_POLL_MS;
    if (_wifi.isConnected()) {
        _oled.setState(STATE_WIFI_CONNECTED, _wifi.getIP());
        delay(1200);
        beep(100);
    } else {
        _oled.setState(STATE_WIFI_DISCONNECTED);
    }

    const uint32_t scanTaskStackSize = 4096;
    BaseType_t scanTaskStarted = xTaskCreatePinnedToCore(
        rfidScanTask,
        "rfid_scan",
        scanTaskStackSize,
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
    } else {
        Serial.printf("[RFID] Reader task started (stack=%lu, queue capacity=%u scans).\n",
            static_cast<unsigned long>(scanTaskStackSize),
            static_cast<unsigned int>(SCAN_QUEUE_CAPACITY));
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
            _heartbeatAuthenticated = false;
            taskENTER_CRITICAL(&_scanMux);
            _sessionStateKnown = false;
            taskEXIT_CRITICAL(&_scanMux);
            _oled.setState(STATE_WIFI_DISCONNECTED);
        }
        handleRFIDScan();
        reportLoopStack();
        return;
    }

    if (!_wasWifiConnected) {
        _wasWifiConnected = true;
        _heartbeatAuthenticated = false;
        _lastHeartbeat = millis() - HEARTBEAT_INTERVAL_MS;
        Serial.printf("[WiFi] Reconnected. IP: %s\n", _wifi.getIP().c_str());
        _oled.setState(STATE_WIFI_CONNECTED, _wifi.getIP());
    }

    handleHeartbeat();
    handleSessionPoll();
    handleRFIDScan();
    reportLoopStack();
}

void DeviceManager::handleHeartbeat() {
    unsigned long now = millis();
    if (now - _lastHeartbeat >= _heartbeatRetryInterval) {
        _lastHeartbeat = now;
        String err = "";
        bool ok = _api.sendHeartbeat(FIRMWARE_VERSION, err);
        if (!ok) {
            _heartbeatAuthenticated = false;
            taskENTER_CRITICAL(&_scanMux);
            _sessionStateKnown = false;
            taskEXIT_CRITICAL(&_scanMux);
            if (_heartbeatRetryInterval < HEARTBEAT_MAX_RETRY_MS) {
                _heartbeatRetryInterval *= 2;
                if (_heartbeatRetryInterval > HEARTBEAT_MAX_RETRY_MS) {
                    _heartbeatRetryInterval = HEARTBEAT_MAX_RETRY_MS;
                }
            }
            Serial.printf("[Heartbeat] Failed: %s\n", err.c_str());
            if (err == "UNAUTHORIZED") {
                _oled.setState(STATE_UNAUTHORIZED);
            } else {
                _oled.setState(STATE_SERVER_ERROR, err);
            }
        } else {
            _heartbeatAuthenticated = true;
            _heartbeatRetryInterval = HEARTBEAT_INTERVAL_MS;
            Serial.println("[Heartbeat] Authenticated heartbeat confirmed.");
            if (hasActiveSession()) {
                _oled.setState(STATE_READY_TO_SCAN, _activeContainerCode);
            } else {
                _oled.setState(STATE_DEVICE_ONLINE);
            }
        }
    }
}

void DeviceManager::handleSessionPoll() {
    if (!_heartbeatAuthenticated) {
        return;
    }

    unsigned long now = millis();
    if (now - _lastSessionPoll >= _sessionPollInterval) {
        _lastSessionPoll = now;

        String deviceId, sessionId, containerCode, customerName, err;
        int expiresIn = 0;
        bool found = _api.checkActiveSession(deviceId, sessionId, containerCode, customerName, expiresIn, err);

        if (found && sessionId.length() > 0 && deviceId.length() > 0) {
            _sessionPollInterval = SESSION_POLL_MS;
            taskENTER_CRITICAL(&_scanMux);
            _sessionStateKnown = true;
            taskEXIT_CRITICAL(&_scanMux);
            if (_activeSessionId != sessionId) {
                // New registration session started on admin dashboard!
                _activeDeviceId = deviceId;
                _activeSessionId = sessionId;
                _activeContainerCode = containerCode;
                _activeCustomerName = customerName;
                _sessionExpiresIn = expiresIn;
                taskENTER_CRITICAL(&_scanMux);
                _hasActiveSession = true;
                _sessionStateKnown = true;
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
            _sessionPollInterval = SESSION_POLL_MS;
            taskENTER_CRITICAL(&_scanMux);
            _sessionStateKnown = true;
            taskEXIT_CRITICAL(&_scanMux);
            if (hasActiveSession()) {
                clearActiveSession();
                _oled.setState(_heartbeatAuthenticated ? STATE_DEVICE_ONLINE : STATE_WIFI_CONNECTED);
            }
        } else {
            taskENTER_CRITICAL(&_scanMux);
            _sessionStateKnown = false;
            taskEXIT_CRITICAL(&_scanMux);
            if (_sessionPollInterval < SESSION_POLL_MAX_MS) {
                _sessionPollInterval *= 2;
                if (_sessionPollInterval > SESSION_POLL_MAX_MS) {
                    _sessionPollInterval = SESSION_POLL_MAX_MS;
                }
            }
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

    bool sessionKnown;
    bool sessionActive;
    char currentDeviceId[sizeof(_scanDeviceId)] = {};
    char currentSessionId[sizeof(_scanSessionId)] = {};
    taskENTER_CRITICAL(&_scanMux);
    sessionKnown = _sessionStateKnown;
    sessionActive = _hasActiveSession;
    memcpy(currentDeviceId, _scanDeviceId, sizeof(currentDeviceId));
    memcpy(currentSessionId, _scanSessionId, sizeof(currentSessionId));
    taskEXIT_CRITICAL(&_scanMux);

    if (!sessionKnown && !sessionActive && !event.registrationMode) {
        Serial.println("[RFID] Scan retained: registration session status is unknown.");
        return;
    }

    const bool registrationMode = event.registrationMode || sessionActive;
    String uid(event.uid);

    Serial.printf("[RFID] Physical tag scanned: %s\n", uid.c_str());

    if (registrationMode) {
        // Submit registration scan
        _oled.setState(STATE_REGISTERING_TAG);
        String msg, err;
        const String deviceId = event.registrationMode
            ? String(event.deviceId)
            : String(currentDeviceId);
        const String sessionId = event.registrationMode
            ? String(event.sessionId)
            : String(currentSessionId);
        if (deviceId.length() == 0 || sessionId.length() == 0) {
            Serial.println("[RFID] Registration scan retained: session identifiers are unavailable.");
            return;
        }
        Serial.println("[RFID] Registration session active");
        Serial.printf("[RFID] Sending registration scan to Laravel. Session: %s\n", sessionId.c_str());
        bool success = _api.submitRegistrationScan(
            deviceId,
            sessionId,
            uid,
            String(event.scanId),
            msg,
            err
        );

        if (success) {
            finishPendingScan();
            Serial.printf("[RFID] Registration successful. UID: %s\n", uid.c_str());
            _oled.setState(STATE_REGISTER_SUCCESS, uid);
            beep(150);
            beep(150);
            clearActiveSession();
        } else {
            Serial.printf("[Registration] Failed (%s): %s\n", err.c_str(), msg.c_str());
            if (isRetryableError(err)) {
                _retryScanAt = millis() + _scanRetryInterval;
                _scanRetryInterval = min(_scanRetryInterval * 2, static_cast<unsigned long>(RFID_RETRY_MAX_MS));
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
            if (err == "SESSION_EXPIRED" || err == "SESSION_CANCELLED" ||
                err == "SESSION_COMPLETED" || err == "SESSION_FAILED" ||
                err == "SESSION_NOT_FOUND") {
                clearActiveSession();
            }
        }
    } else {
        // Operational scan (Station Intake / Verification)
        String msg, customerName, err;
        int gallonCount = 0;
        Serial.println("[RFID] Sending verification scan to Laravel");
        bool success = _api.submitOperationalScan(uid, "VERIFICATION", "", String(event.scanId), msg, customerName, gallonCount, err);
        if (!success && isRetryableError(err)) {
            _retryScanAt = millis() + _scanRetryInterval;
            _scanRetryInterval = min(_scanRetryInterval * 2, static_cast<unsigned long>(RFID_RETRY_MAX_MS));
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
        if (xQueueReceive(_scanQueue, &completed, 0) != pdTRUE) {
            Serial.println("[RFID] Queue changed before pending scan could be removed.");
        } else if (completed.pendingSlot < SCAN_QUEUE_CAPACITY) {
            taskENTER_CRITICAL(&_scanMux);
            _pendingUids[completed.pendingSlot][0] = '\0';
            taskEXIT_CRITICAL(&_scanMux);
        }
    }
    _retryScanAt = 0;
    _scanRetryInterval = RFID_RETRY_INITIAL_MS;
}

void DeviceManager::clearActiveSession() {
    _activeDeviceId = "";
    _activeSessionId = "";
    _activeContainerCode = "";
    _activeCustomerName = "";
    _sessionExpiresIn = 0;

    taskENTER_CRITICAL(&_scanMux);
    _hasActiveSession = false;
    _sessionStateKnown = true;
    _scanSessionId[0] = '\0';
    _scanDeviceId[0] = '\0';
    taskEXIT_CRITICAL(&_scanMux);
}

bool DeviceManager::hasActiveSession() {
    taskENTER_CRITICAL(&_scanMux);
    const bool active = _hasActiveSession;
    taskEXIT_CRITICAL(&_scanMux);
    return active;
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
    return error.startsWith("NETWORK_ERROR") || error.startsWith("HTTP_5") ||
        error == "HTTP_429" || error == "RATE_LIMITED" ||
        error == "TOO_MANY_REQUESTS" || error == "REGISTRATION_SESSION_ACTIVE";
}

void DeviceManager::reportLoopStack() {
    const unsigned long now = millis();
    if (now - _lastStackReport >= 60000) {
        _lastStackReport = now;
        Serial.printf("[DIAG] Arduino loop task minimum free stack: %u units\n",
            static_cast<unsigned int>(uxTaskGetStackHighWaterMark(nullptr)));
    }
}

void DeviceManager::rfidScanTask(void* parameter) {
    DeviceManager* manager = static_cast<DeviceManager*>(parameter);
    if (!manager || !manager->_scanQueue) {
        Serial.println("[RFID] Scan task received invalid manager or queue.");
        vTaskDelete(nullptr);
        return;
    }

    unsigned long lastStackReport = millis();
    for (;;) {
        if (manager->_rfid.isTagPresent()) {
            String uid = manager->_rfid.readUID();
            if (uid.length() > 0) {
                RFIDScanEvent event = {};
                event.pendingSlot = SCAN_QUEUE_CAPACITY;
                uid.toCharArray(event.uid, sizeof(event.uid));
                bool duplicateQueuedUid = false;
                uint8_t pendingSlot = SCAN_QUEUE_CAPACITY;
                taskENTER_CRITICAL(&manager->_scanMux);
                for (uint8_t i = 0; i < SCAN_QUEUE_CAPACITY; i++) {
                    if (manager->_pendingUids[i][0] == '\0') {
                        if (pendingSlot == SCAN_QUEUE_CAPACITY) {
                            pendingSlot = i;
                        }
                    } else if (strncmp(manager->_pendingUids[i], event.uid, sizeof(event.uid)) == 0) {
                        duplicateQueuedUid = true;
                        break;
                    }
                }
                if (!duplicateQueuedUid && pendingSlot < SCAN_QUEUE_CAPACITY) {
                    memcpy(manager->_pendingUids[pendingSlot], event.uid, sizeof(event.uid));
                    event.registrationMode = manager->_hasActiveSession;
                    if (event.registrationMode) {
                        memcpy(event.deviceId, manager->_scanDeviceId, sizeof(event.deviceId));
                        memcpy(event.sessionId, manager->_scanSessionId, sizeof(event.sessionId));
                    }
                }
                taskEXIT_CRITICAL(&manager->_scanMux);

                Serial.println("[RFID] Card detected");
                Serial.printf("[RFID] UID read successfully: %s\n", uid.c_str());
                manager->beep(80);
                if (duplicateQueuedUid) {
                    Serial.println("[RFID] Duplicate UID already pending; queued duplicate suppressed.");
                } else if (pendingSlot >= SCAN_QUEUE_CAPACITY) {
                    Serial.printf("[RFID] Scan queue full (%u pending scans); physical scan could not be queued.\n",
                        static_cast<unsigned int>(uxQueueMessagesWaiting(manager->_scanQueue)));
                } else {
                    event.pendingSlot = pendingSlot;
                    makeScanId().toCharArray(event.scanId, sizeof(event.scanId));
                    if (xQueueSend(manager->_scanQueue, &event, 0) != pdTRUE) {
                        taskENTER_CRITICAL(&manager->_scanMux);
                        manager->_pendingUids[pendingSlot][0] = '\0';
                        taskEXIT_CRITICAL(&manager->_scanMux);
                        Serial.println("[RFID] Scan queue full; physical scan could not be queued.");
                    } else {
                        Serial.printf("[RFID] Pending scan queue: %u/%u\n",
                            static_cast<unsigned int>(uxQueueMessagesWaiting(manager->_scanQueue)),
                            static_cast<unsigned int>(SCAN_QUEUE_CAPACITY));
                    }
                }
                while (manager->_rfid.isCardStillPresent()) {
                    vTaskDelay(pdMS_TO_TICKS(50));
                }
            }
        }

        const unsigned long now = millis();
        if (now - lastStackReport >= 60000) {
            lastStackReport = now;
            Serial.printf("[DIAG] RFID task minimum free stack: %u units\n",
                static_cast<unsigned int>(uxTaskGetStackHighWaterMark(nullptr)));
        }
        vTaskDelay(pdMS_TO_TICKS(2));
    }
}

