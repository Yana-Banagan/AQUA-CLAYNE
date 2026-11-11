#include "oled_manager.h"

OLEDManager::OLEDManager(uint8_t width, uint8_t height, int8_t resetPin)
    : _display(width, height, &Wire, resetPin), _isAvailable(false), _currentState(STATE_BOOTING) {}

bool OLEDManager::begin(uint8_t i2cAddr) {
    if (!_display.begin(SSD1306_SWITCHCAPVCC, i2cAddr)) {
        Serial.println("[OLED] Warning: SSD1306 display not detected on I2C bus.");
        _isAvailable = false;
        return false;
    }
    _isAvailable = true;
    _display.clearDisplay();
    _display.setTextColor(SSD1306_WHITE);
    setState(STATE_BOOTING);
    return true;
}

void OLEDManager::drawHeader() {
    _display.setTextSize(1);
    _display.setCursor(0, 0);
    _display.print("AQUA CLAYNE RFID");
    _display.drawLine(0, 9, 128, 9, SSD1306_WHITE);
}

void OLEDManager::setState(DisplayState state, const String& extraText) {
    _currentState = state;
    if (!_isAvailable) return;

    _display.clearDisplay();
    drawHeader();
    _display.setCursor(0, 16);

    switch (state) {
        case STATE_BOOTING:
            _display.setTextSize(1);
            _display.println("STARTING DEVICE...");
            _display.println("Initializing sensors");
            break;

        case STATE_CONNECTING_WIFI:
            _display.setTextSize(1);
            _display.println("CONNECTING TO WIFI...");
            if (extraText.length() > 0) _display.println(extraText);
            break;

        case STATE_WIFI_CONNECTED:
            _display.setTextSize(1);
            _display.println("WIFI CONNECTED");
            if (extraText.length() > 0) _display.println(extraText);
            break;

        case STATE_CONNECTING_SERVER:
            _display.setTextSize(1);
            _display.println("CONNECTING TO SERVER...");
            break;

        case STATE_DEVICE_ONLINE:
        case STATE_WAITING_FOR_ADMIN:
            _display.setTextSize(1);
            _display.println("STATUS: ONLINE");
            _display.println("");
            _display.println("WAITING FOR ADMIN...");
            _display.println("Scanner ready");
            break;

        case STATE_READY_TO_SCAN:
            _display.setTextSize(1);
            _display.println(">> RFID REGISTRATION <<");
            _display.setTextSize(2);
            _display.println("TAP TAG");
            _display.setTextSize(1);
            if (extraText.length() > 0) _display.println(extraText);
            break;

        case STATE_RFID_DETECTED:
            _display.setTextSize(1);
            _display.println("RFID DETECTED");
            _display.setTextSize(1);
            _display.print("UID: ");
            _display.println(extraText);
            break;

        case STATE_REGISTERING_TAG:
            _display.setTextSize(1);
            _display.println("REGISTERING TAG...");
            _display.println("Contacting server...");
            break;

        case STATE_REGISTER_SUCCESS:
            _display.setTextSize(1);
            _display.println("SUCCESS!");
            _display.println("TAG REGISTERED");
            _display.println("SUCCESSFULLY");
            if (extraText.length() > 0) {
                _display.print("UID: ");
                _display.println(extraText);
            }
            break;

        case STATE_DUPLICATE_TAG:
            _display.setTextSize(1);
            _display.println("ERROR: REJECTED");
            _display.println("DUPLICATE RFID TAG");
            _display.println("Already assigned");
            break;

        case STATE_SESSION_EXPIRED:
            _display.setTextSize(1);
            _display.println("REGISTRATION");
            _display.println("SESSION EXPIRED");
            _display.println("Restart on dashboard");
            break;

        case STATE_UNAUTHORIZED:
            _display.setTextSize(1);
            _display.println("ERROR:");
            _display.println("DEVICE UNAUTHORIZED");
            _display.println("Check token in config");
            break;

        case STATE_SERVER_ERROR:
            _display.setTextSize(1);
            _display.println("SERVER CONNECTION");
            _display.println("FAILED");
            if (extraText.length() > 0) _display.println(extraText);
            break;

        case STATE_WIFI_DISCONNECTED:
            _display.setTextSize(1);
            _display.println("WIFI DISCONNECTED");
            _display.println("Attempting reconnect...");
            break;
    }

    _display.display();
}

void OLEDManager::showCustom(const String& line1, const String& line2, const String& line3) {
    if (!_isAvailable) return;
    _display.clearDisplay();
    drawHeader();
    _display.setCursor(0, 16);
    _display.setTextSize(1);
    _display.println(line1);
    if (line2.length() > 0) _display.println(line2);
    if (line3.length() > 0) _display.println(line3);
    _display.display();
}

void OLEDManager::clear() {
    if (!_isAvailable) return;
    _display.clearDisplay();
    _display.display();
}

