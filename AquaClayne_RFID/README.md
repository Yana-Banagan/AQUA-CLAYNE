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
| Green LED | **GPIO 32** | GPIO â†’ resistor â†’ LED anode; cathode to GND |
| Red LED | **GPIO 33** | GPIO â†’ resistor â†’ LED anode; cathode to GND |

---

## 3. Firmware Configuration

Hardware and server settings are in `AquaClayne_RFID/include/config.h`. Copy `include/secrets.example.h` to either `include/firmware_secrets.h` or `include/secrets.h`, then fill in the station Wi‑Fi credentials and device token from the Admin RFID device settings. Do not commit the local secrets file. The firmware connects to the configured Laravel backend URL.

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
1. In the Admin Dashboard (`/admin/rfid`), click **"Register New RFID Tag"**. Eligible customers (those with active, untagged containers) load immediately; search by name, phone, or email to filter.
2. Select a customer and one of their eligible containers (or click `+ New Container`), then select a scanner marked **ONLINE**.
3. Click **"Start RFID Registration"**. Laravel checks the selected customer/container, a recent authenticated device heartbeat, and that neither the container nor scanner has another active registration. It creates a device-bound 3-minute session.
4. The ESP32 polls the authenticated active-session endpoint every 1.5 seconds. RFID polling runs continuously in a separate task and queues scans while HTTPS requests run. The reader does not enqueue the same card again until it has been removed.
5. The ESP32 sends the actual UID, device ID, and session ID to `POST /api/iot/rfid/registration-scan`. Laravel validates the scanner, session, UID format, and duplicate assignment in a transaction.
6. Only after Laravel confirms success does the modal show the actual UID, customer, container, scanner, and registration time. It refreshes the registry and eligible-container data without reloading the page. Rejected UIDs show Laravel's reason and leave an unexpired registration session available for another physical tap. The rejected attempt is recorded in the existing RFID transaction/audit history.

### Step 3: Registered Tag Identification
- Outside a registration session, a customer tag is sent to authenticated `POST /api/iot/rfid/scan` with `scan_type: VERIFICATION`.
- Laravel looks up the current customer and counts their active customer-container records.
- The OLED displays only the customer name and current gallon count. An unregistered UID displays `RFID Tag Not Registered.` A registered tag without retrievable customer/gallon data displays an error.
- The RFID management dashboard receives each processed verification result through the existing `realtime_events` Supabase subscription and displays only status, customer name, and active gallon count. Scanner heartbeat status is not used as a substitute for scan results.
- Every verification request includes a unique `scan_id`. Laravel uses it to return the existing result rather than recording a second transaction if the ESP32 retries after a communication failure.

### Step 4: Station Intake Verification
- When empty gallons arrive at the station, staff scans the gallon tag.
- The system checks that the UID belongs to an active customer container matching the order.
- Prevents duplicate intake scans and detects unlinked or foreign containers.

### Device API Contract
All ESP32 requests use `X-Device-Token` from the local, ignored `include/secrets.h` or `include/firmware_secrets.h`. Never commit the token. New hardware records remain **OFFLINE** until a real authenticated heartbeat is received.

The modal loads eligible customers from `GET /admin/rfid/search-customers` and containers from `GET /admin/rfid/customers/{customer}/containers`. It starts a session with `POST /admin/rfid/registration-sessions` and polls `GET /admin/rfid/registration-sessions/{session}/status`:

```json
{
  "customer_id": "<customer UUID>",
  "customer_container_id": "<container UUID>",
  "station_device_id": "<online ESP32 UUID>"
}
```

`GET /api/iot/rfid/registration-session/active` returns the assigned `device_id`, `session_id`, container display information, and server-computed expiry. After a physical tap, the scanner submits:

```json
{
  "device_id": "<authenticated device UUID>",
  "session_id": "<active registration session UUID>",
  "uid": "<actual RC522 hexadecimal UID>"
}
```

to `POST /api/iot/rfid/registration-scan`. The backend compares the submitted device ID with the device authenticated by the token; the browser never supplies a UID.

### Build, Flash, and Physical Test
- Register the ESP32 in Admin → RFID & Gallon and copy its one-time device token to the ignored firmware secrets file.
- From the repository root, build with `python -m platformio run --project-dir AquaClayne_RFID`; flash with `python -m platformio run --project-dir AquaClayne_RFID --target upload`. Select the correct serial port if needed.
- Open the serial monitor at 115200 baud. Confirm heartbeat and registration-session polling.
- Open the registration modal, select an online scanner and an untagged container, start registration, and tap a physical tag. Verify the UID and success details in the modal and registry.
- During registration, tap a previously registered UID and confirm the modal displays the failure reason while remaining open for another tap; then tap a new UID and confirm registration succeeds.
- Tap the registered tag again in normal mode. Confirm the OLED displays only the customer name and current active gallon count. Test an unregistered tag and confirm `RFID Tag Not Registered.`
- Keep a tag on the reader and confirm it is processed once; remove it and tap it again to confirm it can be scanned again. Verify dashboard scan results update without a page refresh.
- Apply pending Laravel migrations before deploying the updated backend. Do not manually mark a scanner online as a substitute for its heartbeat.

