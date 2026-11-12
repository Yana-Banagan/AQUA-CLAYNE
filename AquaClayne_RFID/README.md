# Aqua Clayne - ESP32 RFID Reader & Gallon Tracking System

Hardware integration and firmware for the Aqua Clayne Water Refilling Station Gallon Management System.

---

## 1. Hardware Components

| Component | Specification | Description |
|---|---|---|
| **Microcontroller** | ESP32 DevKit V1 (30-pin or 38-pin) | 240MHz Dual-Core, 2.4GHz Wi-Fi, SPI, I2C |
| **RFID Reader** | RC522 (13.56 MHz RFID Reader/Writer) | Reads Mifare Classic 1K / Ultralight / NTAG tags |
| **Display** | 16x2 I2C LCD (0x27); optional SSD1306 OLED (0x3C) | Displays device state and scan feedback |
| **Buzzers** | Two active 3.3V buzzers | Audio feedback on GPIO 25 and 26 |
| **Status LEDs** | Green and red LEDs with current-limiting resistors | Status indicators on GPIO 32 and 33 |
| **Power Supply** | 5V 1A Micro-USB / 3.3V Stable Regulator | Dedicated clean power for RC522 |
| **RFID Tags** | 13.56MHz Mifare / Anti-metal RFID Tags | Waterproof tags affixed to customer gallons |

---

## 2. Hardware Wiring Diagram

### A. RC522 RFID Reader to ESP32 (SPI Interface)

> [!WARNING]
> Connect RC522 **VCC to 3.3V ONLY**. Connecting 5V will permanently damage the RC522 IC.

| RC522 Pin | ESP32 GPIO Pin | Wire Color (Suggested) | Notes |
|---|---|---|---|
| **VCC** | **3.3V** | Red | Must be clean 3.3V |
| **RST** | **GPIO 27** | Gray | Configurable in `config.h` |
| **GND** | **GND** | Black | Ground |
| **MISO** | **GPIO 19** | Blue | SPI Master In / Slave Out |
| **MOSI** | **GPIO 23** | Green | SPI Master Out / Slave In |
| **SCK** | **GPIO 18** | Yellow | SPI Clock |
| **SDA / SS** | **GPIO 5** | White / Orange | SPI Chip Select |

### B. 16x2 I2C LCD to ESP32

| LCD Pin | ESP32 GPIO Pin | Description |
|---|---|---|
| **VCC** | **5V** | Check the LCD backpack's rated voltage |
| **GND** | **GND** | Ground |
| **SCL** | **GPIO 22** | I2C Clock |
| **SDA** | **GPIO 21** | I2C Data |
| I2C address | **0x27** | Configured in `config.h` |

Optional SSD1306 OLEDs share SDA 21 and SCL 22, and use address `0x3C`. The LCD and OLED can share the bus because their addresses differ.
If the LCD backpack is powered from 5V, make sure its SDA/SCL pull-ups do not send 5V into ESP32 GPIOs; use a 3.3V-compatible I2C backpack or a bidirectional I2C level shifter.

### C. Buzzers and Status LEDs

| Component | ESP32 GPIO Pin | Wiring |
|---|---|---|
| Buzzer 1 (+) | **GPIO 25** | Buzzer 1 (-) to GND |
| Buzzer 2 (+) | **GPIO 26** | Buzzer 2 (-) to GND |
| Green LED | **GPIO 32** | GPIO → resistor → LED anode; cathode to GND |
| Red LED | **GPIO 33** | GPIO → resistor → LED anode; cathode to GND |

---

## 3. Firmware Configuration

Hardware and server settings are in `AquaClayne_RFID/include/config.h`. Local Wi-Fi and device credentials belong in the ignored `include/secrets.h` file. Copy `include/secrets.example.h` to `include/secrets.h` on a fresh checkout.

```cpp
// 1. Wi-Fi Settings
#define DEFAULT_WIFI_SSID       "Your_Station_WiFi_SSID"
#define DEFAULT_WIFI_PASS       "Your_Station_WiFi_Password"

// 2. Aqua Clayne Backend Base URL
// Local dev: "http://192.168.1.108:8000"
// Production: "https://aqua-clayne-production.up.railway.app"
#define API_BASE_URL            "https://aqua-clayne-production.up.railway.app"

// 3. Security Device Credential
// Must match device_token_hash in the database (or the default pre-seeded token)
#define DEVICE_AUTH_TOKEN       "ac_dev_rfid_station_bay1_secret2026"
#define DEVICE_CODE             "ESP32-STA-01"
```

---

## 4. Building and Flashing via PlatformIO

1. Install **VS Code** with the **PlatformIO IDE** extension.
2. Open the folder `AquaClayne_RFID` in VS Code / PlatformIO.
3. Connect the ESP32 to your computer using a Micro-USB data cable.
4. Select the environment: `[env:esp32dev]`.
5. Click **Build** (checkmark icon) to compile.
6. Click **Upload** (right arrow icon) to flash the firmware.
7. Open **Serial Monitor** at **115200 baud** to view real-time diagnostics.

---

## 5. End-to-End Operational Workflow

### Step 1: Device Boot & Heartbeat
- On startup, ESP32 connects to Wi-Fi.
- OLED displays: `STARTING DEVICE...` -> `CONNECTING TO WIFI...` -> `WIFI CONNECTED` -> `DEVICE ONLINE`.
- ESP32 sends periodic heartbeats to `POST /api/iot/rfid/heartbeat` every 15 seconds.
- The Admin Web Dashboard reflects the scanner status as **ONLINE**.

### Step 2: RFID Registration
1. In the Admin Dashboard (`/admin/rfid`), click **"Register New RFID Tag"**.
2. Search and select the customer.
3. Select an untagged customer container (or click `+ New Container`).
4. Click **"Start RFID Registration"**.
5. Laravel creates a temporary 3-minute registration session.
6. ESP32 detects the pending session and displays:
   ```
   >> RFID REGISTRATION <<
          TAP TAG
      AC-JUA-001
   ```
7. Admin or station staff taps the physical RFID tag onto the RC522 antenna.
8. ESP32 transmits UID to `POST /api/iot/rfid/registration-scan`.
9. Laravel validates uniqueness, assigns the tag in Supabase PostgreSQL, creates audit logs, and emits a Realtime event.
10. ESP32 beeps twice and displays:
    ```
    TAG REGISTERED
     SUCCESSFULLY
    ```
11. Admin dashboard automatically refreshes and displays the new RFID tag.

### Step 3: Station Intake Verification
- When empty gallons arrive at the station, staff scans the gallon tag.
- The system checks that the UID belongs to an active customer container matching the order.
- Prevents duplicate intake scans and detects unlinked or foreign containers.

