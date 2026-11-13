#ifndef API_CLIENT_H
#define API_CLIENT_H

#include <Arduino.h>

class APIClient {
public:
    APIClient(const char* baseUrl, const char* deviceToken);

    bool sendHeartbeat(const String& firmwareVersion, String& outError);
    bool checkActiveSession(String& outDeviceId, String& outSessionId, String& outContainerCode, String& outCustomerName, int& outExpiresIn, String& outError);
    bool submitRegistrationScan(const String& deviceId, const String& sessionId, const String& uid, String& outMessage, String& outError);
    bool submitOperationalScan(const String& uid, const String& scanType, const String& orderId, String& outMessage, String& outCustomerName, int& outGallonCount, String& outError);

private:
    const char* _baseUrl;
    const char* _deviceToken;
    String executeGet(const String& endpoint, int& outStatusCode, String& outError);
    String executePost(const String& endpoint, const String& jsonBody, int& outStatusCode, String& outError);
};

#endif // API_CLIENT_H

