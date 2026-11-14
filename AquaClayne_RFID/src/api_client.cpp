#include "api_client.h"
#include <HTTPClient.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <ArduinoJson.h>
#if __has_include("config.h")
#include "config.h"
#elif __has_include("../include/config.h")
#include "../include/config.h"
#else
#error "config.h not found. Check the project include path."
#endif

APIClient::APIClient(const char* baseUrl, const char* deviceToken)
    : _baseUrl(baseUrl), _deviceToken(deviceToken) {}

String APIClient::executeGet(const String& endpoint, int& outStatusCode, String& outError) {
    outError = "";
    String fullUrl = String(_baseUrl) + endpoint;
    HTTPClient http;
    WiFiClientSecure secureClient;
    WiFiClient client;

    if (fullUrl.startsWith("https://")) {
        secureClient.setInsecure(); // For production with strict CA, replace with root cert
        http.begin(secureClient, fullUrl);
    } else {
        http.begin(client, fullUrl);
    }

    http.setTimeout(HTTP_TIMEOUT_MS);
    http.addHeader("X-Device-Token", _deviceToken);
    http.addHeader("Accept", "application/json");

    outStatusCode = http.GET();
    String response = "";

    Serial.printf("[API] HTTP response: %d\n", outStatusCode);
    if (outStatusCode > 0) {
        response = http.getString();
    } else {
        outError = "NETWORK_ERROR: " + http.errorToString(outStatusCode);
        Serial.printf("[HTTP GET Error] %s -> %s\n", fullUrl.c_str(), outError.c_str());
    }

    http.end();
    return response;
}

String APIClient::executePost(const String& endpoint, const String& jsonBody, int& outStatusCode, String& outError) {
    outError = "";
    String fullUrl = String(_baseUrl) + endpoint;
    HTTPClient http;
    WiFiClientSecure secureClient;
    WiFiClient client;

    if (fullUrl.startsWith("https://")) {
        secureClient.setInsecure();
        http.begin(secureClient, fullUrl);
    } else {
        http.begin(client, fullUrl);
    }

    http.setTimeout(HTTP_TIMEOUT_MS);
    http.addHeader("X-Device-Token", _deviceToken);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("Accept", "application/json");

    outStatusCode = http.POST(jsonBody);
    String response = "";

    Serial.printf("[API] HTTP response: %d\n", outStatusCode);
    if (outStatusCode > 0) {
        response = http.getString();
    } else {
        outError = "NETWORK_ERROR: " + http.errorToString(outStatusCode);
        Serial.printf("[HTTP POST Error] %s -> %s\n", fullUrl.c_str(), outError.c_str());
    }

    http.end();
    return response;
}

bool APIClient::sendHeartbeat(const String& firmwareVersion, String& outError) {
    StaticJsonDocument<128> doc;
    doc["firmware_version"] = firmwareVersion;
    String payload;
    serializeJson(doc, payload);

    int statusCode = 0;
    String response = executePost("/api/iot/rfid/heartbeat", payload, statusCode, outError);

    if (statusCode == 200) {
        return true;
    }
    if (statusCode == 401 || statusCode == 403) {
        outError = "UNAUTHORIZED";
    } else if (statusCode > 0) {
        outError = "HTTP_" + String(statusCode);
    }
    return false;
}

bool APIClient::checkActiveSession(
    String& outDeviceId,
    String& outSessionId,
    String& outContainerCode,
    String& outCustomerName,
    int& outExpiresIn,
    String& outError
) {
    int statusCode = 0;
    String response = executeGet("/api/iot/rfid/registration-session/active", statusCode, outError);

    if (statusCode != 200 || response.length() == 0) {
        if (outError.length() == 0) {
            outError = "HTTP_" + String(statusCode);
        }
        return false;
    }

    StaticJsonDocument<512> doc;
    DeserializationError err = deserializeJson(doc, response);
    if (err) {
        outError = "JSON_PARSE_ERROR";
        return false;
    }

    bool hasSession = doc["has_session"] | false;
    if (!hasSession) {
        outDeviceId = "";
        outSessionId = "";
        return false;
    }

    JsonObject session = doc["session"];
    outDeviceId = session["device_id"].as<String>();
    outSessionId = session["session_id"].as<String>();
    outContainerCode = session["container_code"].as<String>();
    outCustomerName = session["customer_name"].as<String>();
    outExpiresIn = session["expires_in_seconds"] | 0;
    if (outDeviceId.length() == 0 || outSessionId.length() == 0) {
        outError = "SESSION_RESPONSE_INCOMPLETE";
        return false;
    }

    return true;
}

bool APIClient::submitRegistrationScan(
    const String& deviceId,
    const String& sessionId,
    const String& uid,
    String& outMessage,
    String& outError
) {
    StaticJsonDocument<256> doc;
    doc["device_id"] = deviceId;
    doc["session_id"] = sessionId;
    doc["uid"] = uid;
    String payload;
    serializeJson(doc, payload);

    int statusCode = 0;
    String response = executePost("/api/iot/rfid/registration-scan", payload, statusCode, outError);

    StaticJsonDocument<512> resDoc;
    deserializeJson(resDoc, response);

    outMessage = resDoc["message"] | "";
    if (outMessage == "The given data was invalid." && resDoc["errors"].is<JsonObject>()) {
        JsonObject errors = resDoc["errors"].as<JsonObject>();
        for (JsonPair error : errors) {
            JsonArray messages = error.value().as<JsonArray>();
            if (!messages.isNull() && messages.size() > 0) {
                outMessage = messages[0].as<String>();
                break;
            }
        }
    }
    String errCode = resDoc["error"] | "";

    if (statusCode == 200 && resDoc["success"] == true) {
        return true;
    }

    if (errCode.length() > 0) {
        outError = errCode;
    } else if (statusCode > 0) {
        outError = "HTTP_" + String(statusCode);
    }
    return false;
}

bool APIClient::submitOperationalScan(
    const String& uid,
    const String& scanType,
    const String& orderId,
    const String& scanId,
    String& outMessage,
    String& outCustomerName,
    int& outGallonCount,
    String& outError
) {
    StaticJsonDocument<256> doc;
    doc["uid"] = uid;
    doc["scan_type"] = scanType;
    if (scanId.length() > 0) {
        doc["scan_id"] = scanId;
    }
    if (orderId.length() > 0) {
        doc["order_id"] = orderId;
    }
    String payload;
    serializeJson(doc, payload);

    int statusCode = 0;
    String response = executePost("/api/iot/rfid/scan", payload, statusCode, outError);

    StaticJsonDocument<512> resDoc;
    deserializeJson(resDoc, response);

    outMessage = resDoc["message"] | "";
    if (outMessage == "The given data was invalid." && resDoc["errors"].is<JsonObject>()) {
        JsonObject errors = resDoc["errors"].as<JsonObject>();
        for (JsonPair error : errors) {
            JsonArray messages = error.value().as<JsonArray>();
            if (!messages.isNull() && messages.size() > 0) {
                outMessage = messages[0].as<String>();
                break;
            }
        }
    }
    if (statusCode == 200 && resDoc["success"] == true) {
        outCustomerName = resDoc["customer_name"] | "";
        outGallonCount = resDoc["gallon_count"] | 0;
        return true;
    }

    String errCode = resDoc["error"].as<String>();
    if (errCode.length() > 0) {
        outError = errCode;
    } else if (statusCode > 0) {
        outError = String("HTTP_") + String(statusCode);
    }
    return false;
}

