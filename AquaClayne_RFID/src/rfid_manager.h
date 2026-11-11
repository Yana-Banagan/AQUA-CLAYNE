#ifndef RFID_MANAGER_H
#define RFID_MANAGER_H

#include <Arduino.h>
#include <SPI.h>
#include <MFRC522.h>

class RFIDManager {
public:
    RFIDManager(uint8_t ssPin, uint8_t rstPin);
    void begin();
    bool isTagPresent();
    String readUID();
    void halt();

private:
    MFRC522 _mfrc522;
    String formatUID(byte* buffer, byte bufferSize);
};

#endif // RFID_MANAGER_H

