#ifndef WIFI_MANAGER_H
#define WIFI_MANAGER_H

#include <Arduino.h>

class WiFiManager {
public:
    WiFiManager(const char* ssid, const char* password);
    void begin();
    bool isConnected();
    void loop();
    String getIP();
    int getRSSI();

private:
    const char* _ssid;
    const char* _password;
    unsigned long _lastReconnectAttempt;
    void connect();
};

#endif // WIFI_MANAGER_H

