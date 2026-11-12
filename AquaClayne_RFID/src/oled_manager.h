#ifndef OLED_MANAGER_H
#define OLED_MANAGER_H

#include <Arduino.h>
#include <Wire.h>
#include <Adafruit_GFX.h>
#include <Adafruit_SSD1306.h>
#include <LiquidCrystal_I2C.h>

enum DisplayState {
    STATE_BOOTING,
    STATE_CONNECTING_WIFI,
    STATE_WIFI_CONNECTED,
    STATE_CONNECTING_SERVER,
    STATE_DEVICE_ONLINE,
    STATE_WAITING_FOR_ADMIN,
    STATE_READY_TO_SCAN,
    STATE_RFID_DETECTED,
    STATE_REGISTERING_TAG,
    STATE_REGISTER_SUCCESS,
    STATE_DUPLICATE_TAG,
    STATE_SESSION_EXPIRED,
    STATE_UNAUTHORIZED,
    STATE_SERVER_ERROR,
    STATE_WIFI_DISCONNECTED
};

class OLEDManager {
public:
    OLEDManager(uint8_t width, uint8_t height, int8_t resetPin);
    bool begin(uint8_t i2cAddr);
    void setState(DisplayState state, const String& extraText = "");
    void showCustom(const String& line1, const String& line2 = "", const String& line3 = "");
    void clear();

private:
    Adafruit_SSD1306 _display;
    LiquidCrystal_I2C _lcd;
    bool _isAvailable;
    bool _lcdAvailable;
    DisplayState _currentState;
    void drawHeader();
    void writeLcdLine(uint8_t row, const String& text);
    void updateStatusLeds(DisplayState state);
};

#endif // OLED_MANAGER_H

