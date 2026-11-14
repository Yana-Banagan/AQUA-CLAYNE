#include "rfid_manager.h"
#if __has_include("config.h")
#include "config.h"
#elif __has_include("../include/config.h")
#include "../include/config.h"
#else
#error "config.h not found. Check the project include path."
#endif

RFIDManager::RFIDManager(uint8_t ssPin, uint8_t rstPin)
    : _mfrc522(ssPin, rstPin) {}

void RFIDManager::begin() {
    SPI.begin(RC522_SCK_PIN, RC522_MISO_PIN, RC522_MOSI_PIN, RC522_SS_PIN);
    _mfrc522.PCD_Init();
    delay(10);
    byte version = _mfrc522.PCD_ReadRegister(MFRC522::VersionReg);
    Serial.printf("[RFID] Reader initialized. RC522 firmware version: 0x%02X\n", version);
    if (version == 0x00 || version == 0xFF) {
        Serial.println("[RFID] Warning: RC522 did not return a valid firmware version; check SPI wiring and 3.3V power.");
    }
}

bool RFIDManager::isTagPresent() {
    if (!_mfrc522.PICC_IsNewCardPresent()) {
        return false;
    }
    if (!_mfrc522.PICC_ReadCardSerial()) {
        return false;
    }
    return true;
}

bool RFIDManager::isCardStillPresent() {
    byte atqa[2];
    byte atqaSize = sizeof(atqa);
    return _mfrc522.PICC_WakeupA(atqa, &atqaSize) == MFRC522::STATUS_OK;
}

String RFIDManager::readUID() {
    String uid = formatUID(_mfrc522.uid.uidByte, _mfrc522.uid.size);
    halt();
    return uid;
}

String RFIDManager::formatUID(byte* buffer, byte bufferSize) {
    String formatted = "";
    for (byte i = 0; i < bufferSize; i++) {
        if (buffer[i] < 0x10) formatted += "0";
        formatted += String(buffer[i], HEX);
    }
    formatted.toUpperCase();
    return formatted;
}

void RFIDManager::halt() {
    _mfrc522.PICC_HaltA();
    _mfrc522.PCD_StopCrypto1();
}

