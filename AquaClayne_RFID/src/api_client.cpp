#include "api_client.h"
#include <HTTPClient.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <ArduinoJson.h>
#include <string.h>
#if __has_include("config.h")
#include "config.h"
#elif __has_include("../include/config.h")
#include "../include/config.h"
#else
#error "config.h not found. Check the project include path."
#endif

APIClient::APIClient(const char* baseUrl, const char* deviceToken)
    : _baseUrl(baseUrl), _deviceToken(deviceToken) {}

namespace {
void setHttpStatusError(int statusCode, String& outError) {
    if (statusCode == 401) {
        outError = "UNAUTHORIZED";
    } else if (statusCode == 403) {
        outError = "FORBIDDEN";
    } else if (statusCode > 0) {
        outError = "HTTP_" + String(statusCode);
    }
}

bool parseJsonObject(const String& response, JsonDocument& document, String& outError) {
    if (response.length() == 0) {
        outError = "EMPTY_RESPONSE";
        return false;
    }

    DeserializationError error = deserializeJson(document, response);
    if (error) {
        outError = "INVALID_JSON: " + String(error.c_str());
        Serial.printf("[API] Invalid JSON response: %s\n", error.c_str());
        return false;
    }

    if (!document.is<JsonObject>()) {
        outError = "INVALID_JSON_OBJECT";
        Serial.println("[API] JSON response root is not an object.");
        return false;
    }

    return true;
}

void readApiMessage(JsonDocument& document, String& outMessage, String& outError) {
    if (document["message"].is<const char*>()) {
        outMessage = document["message"].as<String>();
    }

    JsonVariantConst errorValue = document["error"];
    if (errorValue.is<const char*>()) {
        outError = errorValue.as<String>();
    }

    if (outMessage == "The given data was invalid." && document["errors"].is<JsonObjectConst>()) {
        JsonObjectConst errors = document["errors"].as<JsonObjectConst>();
        for (JsonPairConst error : errors) {
            if (!error.value().is<JsonArrayConst>()) {
                continue;
            }
            JsonArrayConst messages = error.value().as<JsonArrayConst>();
            if (messages.size() > 0 && messages[0].is<const char*>()) {
                outMessage = messages[0].as<String>();
                break;
            }
        }
    }
}
}

String APIClient::executeRequest(
    const String& endpoint,
    const String* jsonBody,
    int& outStatusCode,
    String& outError
) {
    outError = "";
    outStatusCode = 0;
    if (!_baseUrl || !_deviceToken || strlen(_deviceToken) == 0) {
        outError = "CONFIG_ERROR";
        Serial.println("[API] Request skipped: base URL or device token is not configured.");
        return "";
    }
    if (WiFi.status() != WL_CONNECTED) {
        outError = "NETWORK_ERROR: WIFI_DISCONNECTED";
        return "";
    }

    String fullUrl = String(_baseUrl) + endpoint;
    WiFiClientSecure secureClient;
    WiFiClient client;
    HTTPClient http;

    secureClient.setTimeout(HTTP_TIMEOUT_MS);
    client.setTimeout(HTTP_TIMEOUT_MS);
    if (fullUrl.startsWith("https://")) {
        secureClient.setInsecure();
        if (!http.begin(secureClient, fullUrl)) {
            outError = "HTTP_INIT_FAILED";
            http.end();
            Serial.printf("[API] HTTP initialization failed for %s\n", endpoint.c_str());
            return "";
        }
    } else {
        if (!http.begin(client, fullUrl)) {
            outError = "HTTP_INIT_FAILED";
            http.end();
            Serial.printf("[API] HTTP initialization failed for %s\n", endpoint.c_str());
            return "";
        }
    }

    http.setConnectTimeout(HTTP_CONNECT_TIMEOUT_MS);
    http.setTimeout(HTTP_TIMEOUT_MS);
    http.addHeader("X-Device-Token", _deviceToken);
    http.addHeader("Accept", "application/json");
    if (jsonBody) {
        http.addHeader("Content-Type", "application/json");
    }

    outStatusCode = jsonBody ? http.POST(*jsonBody) : http.GET();
    String response;
    if (outStatusCode > 0) {
        response = http.getString();
        Serial.printf("[API] %s %s -> HTTP %d (%u response bytes)\n",
            jsonBody ? "POST" : "GET", endpoint.c_str(), outStatusCode,
            static_cast<unsigned int>(response.length()));
    } else {
        outError = "NETWORK_ERROR: " + http.errorToString(outStatusCode);
        Serial.printf("[API] %s %s failed (%d): %s\n",
            jsonBody ? "POST" : "GET", endpoint.c_str(), outStatusCode, outError.c_str());
    }

    http.end();
    return response;
}

String APIClient::executeGet(const String& endpoint, int& outStatusCode, String& outError) {
    return executeRequest(endpoint, nullptr, outStatusCode, outError);
}

String APIClient::executePost(
    const String& endpoint,
    const String& jsonBody,
    int& outStatusCode,
    String& outError
) {
    return executeRequest(endpoint, &jsonBody, outStatusCode, outError);
}

bool APIClient::sendHeartbeat(const String& firmwareVersion, String& outError) {
    StaticJsonDocument<128> doc;
    doc["firmware_version"] = firmwareVersion;
    String payload;
    serializeJson(doc, payload);

    int statusCode = 0;
    String response = executePost("/api/iot/rfid/heartbeat", payload, statusCode, outError);

    if (statusCode != 200) {
        if (statusCode > 0) {
            setHttpStatusError(statusCode, outError);
        }
        return false;
    }

    StaticJsonDocument<256> responseDoc;
    if (!parseJsonObject(response, responseDoc, outError)) {
        return false;
    }
    if (!responseDoc["success"].is<bool>() || !responseDoc["success"].as<bool>()) {
        outError = "HEARTBEAT_REJECTED";
        return false;
    }
    return true;
}

bool APIClient::checkActiveSession(
    String& outDeviceId,
    String& outSessionId,
    String& outContainerCode,
    String& outCustomerName,
    int& outExpiresIn,
    String& outError
) {
    outDeviceId = "";
    outSessionId = "";
    outContainerCode = "";
    outCustomerName = "";
    outExpiresIn = 0;

    int statusCode = 0;
    String response = executeGet("/api/iot/rfid/registration-session/active", statusCode, outError);

    if (statusCode != 200) {
        if (statusCode > 0) {
            setHttpStatusError(statusCode, outError);
        }
        return false;
    }

    StaticJsonDocument<512> doc;
    if (!parseJsonObject(response, doc, outError)) {
        return false;
    }
    if (!doc["success"].is<bool>() || !doc["success"].as<bool>() ||
        !doc["has_session"].is<bool>()) {
        outError = "SESSION_RESPONSE_INVALID";
        return false;
    }

    bool hasSession = doc["has_session"].as<bool>();
    if (!hasSession) {
        return false;
    }

    if (!doc["session"].is<JsonObject>()) {
        outError = "SESSION_RESPONSE_INVALID";
        return false;
    }

    JsonObject session = doc["session"].as<JsonObject>();
    if (session["device_id"].is<const char*>()) {
        outDeviceId = session["device_id"].as<String>();
    }
    if (session["session_id"].is<const char*>()) {
        outSessionId = session["session_id"].as<String>();
    }
    if (session["container_code"].is<const char*>()) {
        outContainerCode = session["container_code"].as<String>();
    }
    if (session["customer_name"].is<const char*>()) {
        outCustomerName = session["customer_name"].as<String>();
    }
    if (session["expires_in_seconds"].is<int>()) {
        outExpiresIn = session["expires_in_seconds"].as<int>();
    }

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

    outMessage = "";
    if (statusCode <= 0) {
        return false;
    }

    StaticJsonDocument<512> resDoc;
    String parseError;
    if (parseJsonObject(response, resDoc, parseError)) {
        readApiMessage(resDoc, outMessage, outError);
        if (statusCode == 200 && resDoc["success"].is<bool>() && resDoc["success"].as<bool>()) {
            return true;
        }
    } else if (statusCode == 200) {
        outError = parseError;
    }

    if (statusCode >= 500 || statusCode == 401 || statusCode == 403) {
        setHttpStatusError(statusCode, outError);
    } else if (statusCode == 422 && outError.length() == 0) {
        outError = "HTTP_422";
    } else if (statusCode != 200 && outError.length() == 0) {
        setHttpStatusError(statusCode, outError);
    } else if (statusCode == 200 && outError.length() == 0) {
        outError = "REGISTRATION_REJECTED";
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

    outMessage = "";
    outCustomerName = "";
    outGallonCount = 0;
    if (statusCode <= 0) {
        return false;
    }

    StaticJsonDocument<512> resDoc;
    String parseError;
    if (parseJsonObject(response, resDoc, parseError)) {
        readApiMessage(resDoc, outMessage, outError);
        if (statusCode == 200 && resDoc["success"].is<bool>() && resDoc["success"].as<bool>()) {
            if (!resDoc["customer_name"].is<const char*>() ||
                !resDoc["gallon_count"].is<int>()) {
                outError = "CUSTOMER_DETAILS_NOT_FOUND";
                return false;
            }

            outCustomerName = resDoc["customer_name"].as<String>();
            outGallonCount = resDoc["gallon_count"].as<int>();
            if (outCustomerName.length() == 0 || outGallonCount < 0) {
                outCustomerName = "";
                outGallonCount = 0;
                outError = "CUSTOMER_DETAILS_NOT_FOUND";
                return false;
            }
            return true;
        }
    } else if (statusCode == 200) {
        outError = parseError;
    }

    if (statusCode >= 500 || statusCode == 401 || statusCode == 403) {
        setHttpStatusError(statusCode, outError);
    } else if (statusCode == 422 && outError.length() == 0) {
        outError = "HTTP_422";
    } else if (statusCode != 200 && outError.length() == 0) {
        setHttpStatusError(statusCode, outError);
    } else if (statusCode == 200 && outError.length() == 0) {
        outError = "SCAN_REJECTED";
    }

    return false;
}

