// ============================================================
// IOT RUMAH PENGERING DAUN TALAS BENENG — DISPLAY TOUCHSCREEN
// Board: ESP32-8048S043R (ESP32-S3, 4.3" 800x480, Resistive Touch)
// Library: LovyanGFX
// Komunikasi: UART Serial ke Sensor Board (ESP32)
//             WiFi HTTP Client ke ESP32-CAM (MJPEG Stream)
// ============================================================

#define LGFX_USE_V1
#include <LovyanGFX.hpp>
#include <lgfx/v1/platforms/esp32s3/Panel_RGB.hpp>
#include <lgfx/v1/platforms/esp32s3/Bus_RGB.hpp>
#include <HardwareSerial.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <esp_now.h>
#include <esp_wifi.h>
#include <Preferences.h>

// ============================================================
// KONFIGURASI WiFi — GANTI SESUAI JARINGAN ANDA
// ============================================================
const char* wifi_ssid     = "rifat";
const char* wifi_password = "12345678";

Preferences preferences;
String activeSSID = wifi_ssid;
String activePASS = wifi_password;

// ============================================================
// KONFIGURASI ESP-NOW & STRUKTUR FOTO
// ============================================================
#define IMG_PKT_HEADER  0x01  // Paket header (informasi gambar: ukuran, CRC)
#define IMG_PKT_DATA    0x02  // Paket data (chunk payload)
#define IMG_PKT_END     0x03  // Paket akhir (konfirmasi transfer selesai)
#define IMG_CHUNK_SIZE  200

// Struktur paket — harus identik dengan pengirim (ESP32-CAM)
typedef struct __attribute__((packed)) {
    uint8_t  pktType;         // Tipe paket (HEADER/DATA/END)
    uint32_t totalSize;       // Ukuran total gambar dalam bytes
    uint16_t totalChunks;     // Jumlah total chunk yang akan dikirim
    uint16_t chunkIndex;      // Nomor urut chunk saat ini (0-based)
    uint16_t chunkSize;       // Ukuran data aktual di chunk ini
    uint32_t crc32;           // CRC32: di HEADER = CRC seluruh gambar, di DATA = CRC chunk
    uint8_t  data[IMG_CHUNK_SIZE]; // Payload data gambar
} ImagePacket;

// Fungsi hitung CRC32 untuk verifikasi integritas data
uint32_t calculateCRC32(const uint8_t *data, size_t length) {
    uint32_t crc = 0xFFFFFFFF;
    for (size_t i = 0; i < length; i++) {
        crc ^= data[i];
        for (int j = 0; j < 8; j++) {
            crc = (crc >> 1) ^ (0xEDB88320 & -(crc & 1));
        }
    }
    return ~crc;
}

// ============================================================
// KONFIGURASI DISPLAY — LGFX untuk ESP32-8048S043R
// ============================================================
class LGFX : public lgfx::LGFX_Device {
public:
  lgfx::Bus_RGB      _bus_instance;
  lgfx::Panel_RGB    _panel_instance;
  lgfx::Light_PWM    _light_instance;
  lgfx::Touch_XPT2046 _touch_instance;

  LGFX(void) {
    // ---- Bus RGB Parallel ----
    {
      auto cfg = _bus_instance.config();
      cfg.panel = &_panel_instance;

      cfg.pin_d0  = 8;   // B0
      cfg.pin_d1  = 3;   // B1
      cfg.pin_d2  = 46;  // B2
      cfg.pin_d3  = 9;   // B3
      cfg.pin_d4  = 1;   // B4
      cfg.pin_d5  = 5;   // G0
      cfg.pin_d6  = 6;   // G1
      cfg.pin_d7  = 7;   // G2
      cfg.pin_d8  = 15;  // G3
      cfg.pin_d9  = 16;  // G4
      cfg.pin_d10 = 4;   // G5
      cfg.pin_d11 = 45;  // R0
      cfg.pin_d12 = 48;  // R1
      cfg.pin_d13 = 47;  // R2
      cfg.pin_d14 = 21;  // R3
      cfg.pin_d15 = 14;  // R4

      cfg.pin_henable = 40;
      cfg.pin_vsync   = 41;
      cfg.pin_hsync   = 39;
      cfg.pin_pclk    = 42;

      cfg.freq_write = 16000000;

      cfg.hsync_polarity    = 0;
      cfg.hsync_front_porch = 8;
      cfg.hsync_pulse_width = 4;
      cfg.hsync_back_porch  = 8;

      cfg.vsync_polarity    = 0;
      cfg.vsync_front_porch = 8;
      cfg.vsync_pulse_width = 4;
      cfg.vsync_back_porch  = 8;

      cfg.pclk_active_neg   = 1;
      cfg.de_idle_high      = 0;
      cfg.pclk_idle_high    = 0;

      _bus_instance.config(cfg);
    }

    // ---- Panel ----
    {
      auto cfg = _panel_instance.config();
      cfg.memory_width  = 800;
      cfg.memory_height = 480;
      cfg.panel_width   = 800;
      cfg.panel_height  = 480;
      cfg.offset_x = 0;
      cfg.offset_y = 0;
      _panel_instance.config(cfg);
    }

    _panel_instance.setBus(&_bus_instance);

    // ---- Backlight ----
    {
      auto cfg = _light_instance.config();
      cfg.pin_bl = 2;
      cfg.invert = false;
      cfg.freq   = 44100;
      cfg.pwm_channel = 7;
      _light_instance.config(cfg);
      _panel_instance.light(&_light_instance);
    }

    // ---- Touch XPT2046 (Resistive) ----
    {
      auto cfg = _touch_instance.config();
      cfg.x_min      = 300;
      cfg.x_max      = 3900;
      cfg.y_min      = 400;
      cfg.y_max      = 3900;
      cfg.pin_int    = 18;
      cfg.pin_cs     = 38;
      cfg.bus_shared = false;
      cfg.spi_host   = SPI2_HOST;
      cfg.pin_sclk   = 12;
      cfg.pin_mosi   = 11;
      cfg.pin_miso   = 13;
      cfg.freq       = 1000000;
      _touch_instance.config(cfg);
      _panel_instance.setTouch(&_touch_instance);
    }

    setPanel(&_panel_instance);
  }
};

LGFX lcd;

// ============================================================
// UART ke Sensor Board
// ============================================================
#define SENSOR_RX_PIN 44
#define SENSOR_TX_PIN 43

HardwareSerial SensorSerial(2);

// ============================================================
// WARNA TEMA (Dark Theme)
// ============================================================
#define COL_BG           lcd.color565(18, 18, 24)
#define COL_PANEL        lcd.color565(28, 30, 40)
#define COL_PANEL_BORDER lcd.color565(50, 55, 70)
#define COL_HEADER_BG    lcd.color565(15, 25, 50)
#define COL_HEADER_TEXT  lcd.color565(120, 180, 255)
#define COL_TEXT_WHITE   lcd.color565(230, 235, 245)
#define COL_TEXT_DIM     lcd.color565(140, 145, 160)
#define COL_TEXT_LABEL   lcd.color565(100, 110, 135)

#define COL_SUHU_DS      lcd.color565(255, 120, 50)   // Orange
#define COL_SUHU_SHT     lcd.color565(50, 200, 255)   // Cyan
#define COL_HUMIDITY     lcd.color565(80, 220, 160)    // Teal Green
#define COL_SUHU_LINE    lcd.color565(200, 50, 50)     // Merah untuk batas suhu

#define COL_RELAY_ON     lcd.color565(50, 205, 100)    // Hijau
#define COL_RELAY_OFF    lcd.color565(180, 50, 50)     // Merah
#define COL_RELAY_BTN_ON lcd.color565(30, 120, 60)     // Hijau gelap
#define COL_RELAY_BTN_OFF lcd.color565(100, 30, 30)    // Merah gelap

#define COL_MODE_AUTO    lcd.color565(50, 150, 255)    // Biru
#define COL_MODE_MANUAL  lcd.color565(255, 180, 50)    // Kuning
#define COL_MODE_CAM     lcd.color565(50, 200, 180)    // Teal/Cyan untuk CAM

#define COL_WARNA_HIJAU  lcd.color565(50, 200, 80)
#define COL_WARNA_KH     lcd.color565(200, 220, 50)
#define COL_WARNA_KC     lcd.color565(230, 150, 50)
#define COL_WARNA_COKLAT lcd.color565(160, 90, 40)
#define COL_WARNA_UNKNOWN lcd.color565(120, 120, 120)

#define COL_STATUS_PROSES  lcd.color565(50, 200, 120)
#define COL_STATUS_OVERTEMP lcd.color565(255, 60, 60)
#define COL_STATUS_SELESAI lcd.color565(100, 180, 255)

#define COL_GRAPH_BG     lcd.color565(22, 24, 32)
#define COL_GRAPH_GRID   lcd.color565(40, 45, 55)

// ============================================================
// DATA DARI SENSOR BOARD
// ============================================================
struct SensorData {
  float suhuDS  = 0;
  float suhuSHT = 0;
  float hum     = 0;
  float suhuMax = 0;
  int   warna   = 4;  // WARNA_TIDAK_DIKETAHUI
  int   r = 0, g = 0, b = 0;
  bool  relay[4] = {false, false, false, false};
  int   status = 0;   // StatusSistem
  int   mode   = 0;   // ModeKontrol (0=AUTO, 1=MANUAL)
  bool  dataReceived = false;
};

SensorData sensorData;

// ============================================================
// GRAFIK DATA (Ring Buffer)
// ============================================================
#define GRAPH_POINTS 120   // 120 titik = 4 menit (setiap 2 detik)
float graphSuhuDS[GRAPH_POINTS];
float graphSuhuSHT[GRAPH_POINTS];
float graphHum[GRAPH_POINTS];
int   graphIndex = 0;
int   graphCount = 0;

// ============================================================
// LAYOUT KOORDINAT
// ============================================================
// Header
#define HEADER_Y 0
#define HEADER_H 44

// Panel Sensor (Kiri)
#define PANEL_X      8
#define PANEL_Y      52
#define PANEL_W      280
#define PANEL_H      310

// Grafik (Kanan)
#define GRAPH_X      296
#define GRAPH_Y      52
#define GRAPH_W      496
#define GRAPH_H      310

// Area grafik dalam (dengan padding)
#define GRAPH_INNER_X  (GRAPH_X + 48)
#define GRAPH_INNER_Y  (GRAPH_Y + 30)
#define GRAPH_INNER_W  (GRAPH_W - 60)
#define GRAPH_INNER_H  (GRAPH_H - 56)

// Relay Buttons (Bawah)
#define RELAY_Y      370
#define RELAY_H      65
#define RELAY_BTN_W  170
#define RELAY_BTN_H  55
#define RELAY_GAP    12

// Mode Buttons (Header area)
#define MODE_BTN_W   80
#define MODE_BTN_H   30

// Camera View Layout
#define CAM_HEADER_H    40
#define CAM_VIDEO_X     0
#define CAM_VIDEO_Y     CAM_HEADER_H
#define CAM_VIDEO_W     800
#define CAM_VIDEO_H     (480 - CAM_HEADER_H - 30)  // 410px untuk video
#define CAM_STATUS_Y    (480 - 30)
#define CAM_STATUS_H    30
#define CAM_BACK_BTN_X  10
#define CAM_BACK_BTN_Y  5
#define CAM_BACK_BTN_W  80
#define CAM_BACK_BTN_H  30

// ============================================================
// STATUS UI
// ============================================================
String serialBuffer = "";
unsigned long lastDataTime = 0;
bool needFullRedraw = true;
bool prevRelay[4] = {false, false, false, false};
int  prevMode = -1;
int  prevStatus = -1;
int  prevWarna = -1;
unsigned long lastCommandTime = 0; // Waktu perintah terakhir dikirim ke sensor board

// Debounce touch
unsigned long lastTouchTime = 0;
#define TOUCH_DEBOUNCE 300

// ============================================================
// STATE FOTO & ESP-NOW RECEIVER
// ============================================================
bool showCamera = false;        // Toggle dashboard ↔ camera view
bool wifiConnected = false;     // WiFi connection status
unsigned long lastPacketTime = 0; // Waktu paket terakhir diterima
unsigned long lastPhotoTime = 0;  // Waktu foto terakhir dirender

// ESP-NOW Image Receiver State
volatile bool newPhotoAvailable = false;
volatile uint32_t receivedPhotoSize = 0;
volatile bool isReceivingPhoto = false;
volatile uint16_t chunksReceivedCount = 0;
volatile bool photoCorrupted = false;
uint32_t expectedPhotoCRC = 0;
uint16_t expectedChunks = 0;
uint32_t expectedSize = 0;

// JPEG frame buffer
#define CAM_BUF_SIZE 32768       // 32KB max JPEG frame size (QVGA)
uint8_t* camFrameBuf = NULL;     // Allocated in setup()

// ============================================================
// CALLBACK & INIT ESP-NOW
// ============================================================
void onDataRecv(const uint8_t * mac, const uint8_t *incomingData, int len) {
  if (len < 15 || len > sizeof(ImagePacket)) return;

  ImagePacket packet;
  memcpy(&packet, incomingData, len);

  lastPacketTime = millis();

  if (packet.pktType == IMG_PKT_HEADER) {
    isReceivingPhoto = true;
    newPhotoAvailable = false;
    expectedSize = packet.totalSize;
    expectedChunks = packet.totalChunks;
    expectedPhotoCRC = packet.crc32;
    chunksReceivedCount = 0;
    photoCorrupted = false;
    Serial.printf("[ESP-NOW] Header received. Size: %d, Chunks: %d, CRC: %08X\n", expectedSize, expectedChunks, expectedPhotoCRC);
  }
  else if (packet.pktType == IMG_PKT_DATA) {
    if (!isReceivingPhoto) return;
    
    // Validasi offset dan ukuran chunk
    uint32_t offset = (uint32_t)packet.chunkIndex * IMG_CHUNK_SIZE;
    if (offset + packet.chunkSize > CAM_BUF_SIZE) {
      photoCorrupted = true;
      return;
    }

    // Verifikasi CRC chunk
    uint32_t chunkCRC = calculateCRC32(packet.data, packet.chunkSize);
    if (chunkCRC != packet.crc32) {
      Serial.printf("[ESP-NOW] Chunk %d CRC Mismatch!\n", packet.chunkIndex);
      photoCorrupted = true;
    }

    // Salin ke buffer
    if (camFrameBuf) {
      memcpy(camFrameBuf + offset, packet.data, packet.chunkSize);
    }
    chunksReceivedCount++;
  }
  else if (packet.pktType == IMG_PKT_END) {
    if (!isReceivingPhoto) return;
    isReceivingPhoto = false;

    // Verifikasi CRC akhir dari keseluruhan gambar
    if (camFrameBuf && !photoCorrupted) {
      uint32_t calculatedCRC = calculateCRC32(camFrameBuf, expectedSize);
      if (calculatedCRC == expectedPhotoCRC) {
        receivedPhotoSize = expectedSize;
        newPhotoAvailable = true;
        Serial.printf("[ESP-NOW] Photo received successfully! Size: %d bytes, CRC: %08X\n", receivedPhotoSize, calculatedCRC);
      } else {
        Serial.printf("[ESP-NOW] Photo CRC Mismatch! Expected: %08X, Got: %08X\n", expectedPhotoCRC, calculatedCRC);
      }
    } else {
      Serial.println("[ESP-NOW] Photo corrupted during transmission.");
    }
  }
}

void initESPNow() {
  // Jika tidak terkoneksi ke AP, paksa WiFi Channel ke 1 agar sama dengan ESP32-CAM
  if (WiFi.status() != WL_CONNECTED) {
    esp_wifi_set_promiscuous(true);
    esp_wifi_set_channel(1, WIFI_SECOND_CHAN_NONE);
    esp_wifi_set_promiscuous(false);
    Serial.println("[ESP-NOW] WiFi tidak terhubung ke AP. WiFi Channel dipaksa ke 1.");
  }

  if (esp_now_init() != ESP_OK) {
    Serial.println("[ESP-NOW] Gagal inisialisasi ESP-NOW");
    return;
  }

  esp_now_register_recv_cb(onDataRecv);
  Serial.println("[ESP-NOW] ESP-NOW berhasil diinisialisasi dan callback didaftarkan.");
}

// ============================================================
// FUNGSI: Parse data dari Sensor Board
// ============================================================
void parseData(String data) {
  data.trim();
  if (data.startsWith("WIFI_SET:")) {
    int firstColon = data.indexOf(':');
    int secondColon = data.indexOf(':', firstColon + 1);
    if (firstColon != -1 && secondColon != -1) {
      String new_ssid = data.substring(firstColon + 1, secondColon);
      String new_pass = data.substring(secondColon + 1);
      new_ssid.trim();
      new_pass.trim();
      if (new_ssid.length() > 0 && (new_ssid != activeSSID || new_pass != activePASS)) {
        Serial.printf("[WiFi] UART WiFi update received: SSID='%s', Pass='%s'\n", new_ssid.c_str(), new_pass.c_str());
        
        preferences.begin("wifi-config", false);
        preferences.putString("ssid", new_ssid);
        preferences.putString("pass", new_pass);
        preferences.end();
        
        Serial.println("[WiFi] Restarting Display ESP32 to apply new WiFi settings...");
        delay(1500);
        ESP.restart();
      }
    }
    return;
  }

  if (!data.startsWith("DATA:")) return;
  data = data.substring(5); // Hapus "DATA:"

  // Parse key:value pairs
  while (data.length() > 0) {
    int commaIdx = data.indexOf(',');
    String pair;
    if (commaIdx == -1) {
      pair = data;
      data = "";
    } else {
      pair = data.substring(0, commaIdx);
      data = data.substring(commaIdx + 1);
    }

    int colonIdx = pair.indexOf(':');
    if (colonIdx == -1) continue;

    String key = pair.substring(0, colonIdx);
    String val = pair.substring(colonIdx + 1);

    if      (key == "suhuDS")  sensorData.suhuDS  = val.toFloat();
    else if (key == "suhuSHT") sensorData.suhuSHT = val.toFloat();
    else if (key == "hum")     sensorData.hum     = val.toFloat();
    else if (key == "suhuMax") sensorData.suhuMax = val.toFloat();
    else if (key == "warna")   sensorData.warna   = val.toInt();
    else if (key == "r")       sensorData.r       = val.toInt();
    else if (key == "g")       sensorData.g       = val.toInt();
    else if (key == "b")       sensorData.b       = val.toInt();
    else if (key == "relay1")  { if (millis() - lastCommandTime > 2000) sensorData.relay[0] = val.toInt() == 1; }
    else if (key == "relay2")  { if (millis() - lastCommandTime > 2000) sensorData.relay[1] = val.toInt() == 1; }
    else if (key == "relay3")  { if (millis() - lastCommandTime > 2000) sensorData.relay[2] = val.toInt() == 1; }
    else if (key == "relay4")  { if (millis() - lastCommandTime > 2000) sensorData.relay[3] = val.toInt() == 1; }
    else if (key == "status")  sensorData.status  = val.toInt();
    else if (key == "mode")    { if (millis() - lastCommandTime > 2000) sensorData.mode    = val.toInt(); }
  }

  sensorData.dataReceived = true;

  // Simpan ke graph buffer
  graphSuhuDS[graphIndex]  = sensorData.suhuDS;
  graphSuhuSHT[graphIndex] = sensorData.suhuSHT;
  graphHum[graphIndex]     = sensorData.hum;
  graphIndex = (graphIndex + 1) % GRAPH_POINTS;
  if (graphCount < GRAPH_POINTS) graphCount++;
}

// ============================================================
// FUNGSI: Kirim perintah ke Sensor Board
// ============================================================
void sendCommand(String cmd) {
  lastCommandTime = millis();
  SensorSerial.println("CMD:" + cmd);
}

// ============================================================
// FUNGSI: Gambar rounded rectangle
// ============================================================
void drawRoundPanel(int x, int y, int w, int h, uint16_t bgCol, uint16_t borderCol) {
  lcd.fillRoundRect(x, y, w, h, 8, bgCol);
  lcd.drawRoundRect(x, y, w, h, 8, borderCol);
}

// ============================================================
// FUNGSI: Gambar Header
// ============================================================
void drawHeader() {
  // Background header
  lcd.fillRect(0, HEADER_Y, 800, HEADER_H, COL_HEADER_BG);

  // Judul
  lcd.setTextColor(COL_HEADER_TEXT);
  lcd.setTextSize(1);
  lcd.setFont(&fonts::FreeSansBold12pt7b);
  lcd.setCursor(15, HEADER_Y + 10);
  lcd.print("RUMAH PENGERING DAUN TALAS BENENG");

  // Garis bawah header (accent)
  lcd.fillRect(0, HEADER_Y + HEADER_H - 2, 800, 2, COL_MODE_AUTO);

  // Tombol mode
  drawModeButtons();
}

// ============================================================
// FUNGSI: Gambar Tombol Mode (FOTO / AUTO / MANUAL)
// ============================================================
void drawModeButtons() {
  int btnY = HEADER_Y + 7;

  // Tombol FOTO (paling kiri dari group)
  int camX = 800 - MODE_BTN_W * 3 - 30;
  lcd.fillRoundRect(camX, btnY, MODE_BTN_W, MODE_BTN_H, 6,
                    showCamera ? COL_MODE_CAM : COL_PANEL);
  lcd.drawRoundRect(camX, btnY, MODE_BTN_W, MODE_BTN_H, 6, COL_MODE_CAM);
  lcd.setFont(&fonts::FreeSansBold9pt7b);
  lcd.setTextColor(showCamera ? COL_BG : COL_TEXT_DIM);
  lcd.setCursor(camX + 16, btnY + 7);
  lcd.print("FOTO");

  // Tombol AUTO
  int autoX = 800 - MODE_BTN_W * 2 - 20;
  bool isAuto = sensorData.mode == 0;
  uint16_t autoCol = isAuto ? COL_MODE_AUTO : COL_PANEL;
  lcd.fillRoundRect(autoX, btnY, MODE_BTN_W, MODE_BTN_H, 6, autoCol);
  lcd.drawRoundRect(autoX, btnY, MODE_BTN_W, MODE_BTN_H, 6, COL_MODE_AUTO);
  lcd.setFont(&fonts::FreeSansBold9pt7b);
  lcd.setTextColor(isAuto ? COL_TEXT_WHITE : COL_TEXT_DIM);
  lcd.setCursor(autoX + 14, btnY + 7);
  lcd.print("AUTO");

  // Tombol MANUAL
  int manX = 800 - MODE_BTN_W - 10;
  bool isManual = sensorData.mode == 1;
  uint16_t manCol = isManual ? COL_MODE_MANUAL : COL_PANEL;
  lcd.fillRoundRect(manX, btnY, MODE_BTN_W, MODE_BTN_H, 6, manCol);
  lcd.drawRoundRect(manX, btnY, MODE_BTN_W, MODE_BTN_H, 6, COL_MODE_MANUAL);
  lcd.setTextColor(isManual ? COL_BG : COL_TEXT_DIM);
  lcd.setCursor(manX + 5, btnY + 7);
  lcd.print("MANUAL");
}

// ============================================================
// FUNGSI: Gambar Panel Sensor (Kiri)
// ============================================================
void drawSensorPanel() {
  drawRoundPanel(PANEL_X, PANEL_Y, PANEL_W, PANEL_H, COL_PANEL, COL_PANEL_BORDER);

  int cx = PANEL_X + 12;
  int cy = PANEL_Y + 12;

  // Judul Panel
  lcd.setFont(&fonts::FreeSansBold9pt7b);
  lcd.setTextColor(COL_HEADER_TEXT);
  lcd.setCursor(cx, cy);
  lcd.print("DATA SENSOR");
  cy += 28;

  // Garis separator
  lcd.drawFastHLine(PANEL_X + 8, cy, PANEL_W - 16, COL_PANEL_BORDER);
  cy += 10;

  // --- Suhu DS18B20 ---
  lcd.setFont(&fonts::Font2);
  lcd.setTextColor(COL_TEXT_LABEL);
  lcd.setCursor(cx, cy);
  lcd.print("DS18B20");
  lcd.setFont(&fonts::FreeSansBold12pt7b);
  lcd.setTextColor(COL_SUHU_DS);
  lcd.setCursor(cx, cy + 18);
  lcd.printf("%.1f", sensorData.suhuDS);
  lcd.setFont(&fonts::Font2);
  lcd.setTextColor(COL_TEXT_DIM);
  lcd.print(" *C");
  cy += 50;

  // --- Suhu SHT31 ---
  lcd.setFont(&fonts::Font2);
  lcd.setTextColor(COL_TEXT_LABEL);
  lcd.setCursor(cx, cy);
  lcd.print("SHT31");
  lcd.setFont(&fonts::FreeSansBold12pt7b);
  lcd.setTextColor(COL_SUHU_SHT);
  lcd.setCursor(cx, cy + 18);
  lcd.printf("%.1f", sensorData.suhuSHT);
  lcd.setFont(&fonts::Font2);
  lcd.setTextColor(COL_TEXT_DIM);
  lcd.print(" *C");
  cy += 50;

  // --- Kelembapan ---
  lcd.setFont(&fonts::Font2);
  lcd.setTextColor(COL_TEXT_LABEL);
  lcd.setCursor(cx, cy);
  lcd.print("KELEMBAPAN");
  lcd.setFont(&fonts::FreeSansBold12pt7b);
  lcd.setTextColor(COL_HUMIDITY);
  lcd.setCursor(cx, cy + 18);
  lcd.printf("%.1f", sensorData.hum);
  lcd.setFont(&fonts::Font2);
  lcd.setTextColor(COL_TEXT_DIM);
  lcd.print(" %RH");
  cy += 50;

  // Garis separator
  lcd.drawFastHLine(PANEL_X + 8, cy, PANEL_W - 16, COL_PANEL_BORDER);
  cy += 10;

  // --- Warna Daun ---
  lcd.setFont(&fonts::Font2);
  lcd.setTextColor(COL_TEXT_LABEL);
  lcd.setCursor(cx, cy);
  lcd.print("WARNA DAUN");
  cy += 18;

  // Indikator warna (filled rect)
  uint16_t warnaCol;
  const char* warnaText;
  switch (sensorData.warna) {
    case 0: warnaCol = COL_WARNA_HIJAU;  warnaText = "HIJAU (Segar)";      break;
    case 1: warnaCol = COL_WARNA_KH;     warnaText = "KUNING-HIJAU";       break;
    case 2: warnaCol = COL_WARNA_KC;     warnaText = "KUNING-COKLAT";      break;
    case 3: warnaCol = COL_WARNA_COKLAT; warnaText = "COKLAT (Kering)";    break;
    default: warnaCol = COL_WARNA_UNKNOWN; warnaText = "Tidak Diketahui";  break;
  }

  // Color bar
  lcd.fillRoundRect(cx, cy, PANEL_W - 32, 22, 4, warnaCol);
  lcd.setFont(&fonts::FreeSansBold9pt7b);
  lcd.setTextColor(sensorData.warna == 1 ? COL_BG : COL_TEXT_WHITE);
  lcd.setCursor(cx + 8, cy + 3);
  lcd.print(warnaText);
  cy += 32;

  // --- Status Sistem ---
  lcd.setFont(&fonts::Font2);
  lcd.setTextColor(COL_TEXT_LABEL);
  lcd.setCursor(cx, cy);
  lcd.print("STATUS SISTEM");
  cy += 18;

  uint16_t statusCol;
  const char* statusText;
  switch (sensorData.status) {
    case 0: statusCol = COL_STATUS_PROSES;   statusText = "PROSES PENGERINGAN"; break;
    case 1: statusCol = COL_STATUS_OVERTEMP;  statusText = "OVERTEMP!";          break;
    case 2: statusCol = COL_STATUS_SELESAI;   statusText = "SELESAI KERING";     break;
    default: statusCol = COL_WARNA_UNKNOWN;   statusText = "ERROR";              break;
  }

  lcd.fillRoundRect(cx, cy, PANEL_W - 32, 22, 4, statusCol);
  lcd.setFont(&fonts::FreeSansBold9pt7b);
  lcd.setTextColor(sensorData.status == 1 ? COL_TEXT_WHITE : COL_BG);
  lcd.setCursor(cx + 8, cy + 3);
  lcd.print(statusText);
}

// ============================================================
// FUNGSI: Gambar Grafik (Kanan)
// ============================================================
void drawGraph() {
  drawRoundPanel(GRAPH_X, GRAPH_Y, GRAPH_W, GRAPH_H, COL_PANEL, COL_PANEL_BORDER);

  // Judul
  lcd.setFont(&fonts::FreeSansBold9pt7b);
  lcd.setTextColor(COL_HEADER_TEXT);
  lcd.setCursor(GRAPH_X + 12, GRAPH_Y + 10);
  lcd.print("GRAFIK PENGERING");

  // Legend
  int legX = GRAPH_X + GRAPH_W - 290;
  int legY = GRAPH_Y + 8;
  lcd.setFont(&fonts::Font2);

  // DS18B20 legend
  lcd.fillRect(legX, legY + 4, 12, 3, COL_SUHU_DS);
  lcd.setTextColor(COL_SUHU_DS);
  lcd.setCursor(legX + 16, legY);
  lcd.print("DS18B20");

  // SHT31 legend
  legX += 80;
  lcd.fillRect(legX, legY + 4, 12, 3, COL_SUHU_SHT);
  lcd.setTextColor(COL_SUHU_SHT);
  lcd.setCursor(legX + 16, legY);
  lcd.print("SHT31");

  // Kelembapan legend
  legX += 68;
  lcd.fillRect(legX, legY + 4, 12, 3, COL_HUMIDITY);
  lcd.setTextColor(COL_HUMIDITY);
  lcd.setCursor(legX + 16, legY);
  lcd.print("Hum%");

  // Batas suhu legend
  legX += 55;
  lcd.fillRect(legX, legY + 4, 12, 3, COL_SUHU_LINE);
  lcd.setTextColor(COL_SUHU_LINE);
  lcd.setCursor(legX + 16, legY);
  lcd.print("Maks");

  // Area grafik background
  lcd.fillRect(GRAPH_INNER_X, GRAPH_INNER_Y, GRAPH_INNER_W, GRAPH_INNER_H, COL_GRAPH_BG);
  lcd.drawRect(GRAPH_INNER_X, GRAPH_INNER_Y, GRAPH_INNER_W, GRAPH_INNER_H, COL_PANEL_BORDER);

  // Skala Y: 0-100 (suhu °C / kelembapan %RH)
  float yMin = 0;
  float yMax = 100;

  // Grid horizontal + label Y
  lcd.setFont(&fonts::Font0);
  lcd.setTextColor(COL_TEXT_DIM);
  for (int i = 0; i <= 5; i++) {
    float val = yMin + (yMax - yMin) * (5 - i) / 5.0;
    int y = GRAPH_INNER_Y + (GRAPH_INNER_H * i / 5);
    lcd.drawFastHLine(GRAPH_INNER_X, y, GRAPH_INNER_W, COL_GRAPH_GRID);
    lcd.setCursor(GRAPH_X + 10, y - 3);
    lcd.printf("%.0f", val);
  }

  // Garis batas suhu maksimal 50°C
  float suhuMaksRatio = (50.0 - yMin) / (yMax - yMin);
  int suhuMaksY = GRAPH_INNER_Y + GRAPH_INNER_H - (int)(suhuMaksRatio * GRAPH_INNER_H);
  // Garis putus-putus
  for (int dx = 0; dx < GRAPH_INNER_W; dx += 8) {
    lcd.drawFastHLine(GRAPH_INNER_X + dx, suhuMaksY, 4, COL_SUHU_LINE);
  }

  // Label X (waktu)
  lcd.setFont(&fonts::Font0);
  lcd.setTextColor(COL_TEXT_DIM);
  lcd.setCursor(GRAPH_INNER_X, GRAPH_INNER_Y + GRAPH_INNER_H + 6);
  lcd.print("0");
  lcd.setCursor(GRAPH_INNER_X + GRAPH_INNER_W / 4, GRAPH_INNER_Y + GRAPH_INNER_H + 6);
  lcd.print("60s");
  lcd.setCursor(GRAPH_INNER_X + GRAPH_INNER_W / 2, GRAPH_INNER_Y + GRAPH_INNER_H + 6);
  lcd.print("120s");
  lcd.setCursor(GRAPH_INNER_X + GRAPH_INNER_W * 3 / 4, GRAPH_INNER_Y + GRAPH_INNER_H + 6);
  lcd.print("180s");
  lcd.setCursor(GRAPH_INNER_X + GRAPH_INNER_W - 22, GRAPH_INNER_Y + GRAPH_INNER_H + 6);
  lcd.print("240s");

  // Plot data
  if (graphCount < 2) return;

  int numPoints = min(graphCount, GRAPH_POINTS);
  float xStep = (float)GRAPH_INNER_W / (GRAPH_POINTS - 1);

  for (int i = 1; i < numPoints; i++) {
    int idx0 = (graphIndex - numPoints + i - 1 + GRAPH_POINTS) % GRAPH_POINTS;
    int idx1 = (graphIndex - numPoints + i + GRAPH_POINTS) % GRAPH_POINTS;

    int x0 = GRAPH_INNER_X + (int)((i - 1) * xStep * GRAPH_POINTS / numPoints);
    int x1 = GRAPH_INNER_X + (int)(i * xStep * GRAPH_POINTS / numPoints);

    // Clamp ke area grafik
    if (x0 < GRAPH_INNER_X) x0 = GRAPH_INNER_X;
    if (x1 > GRAPH_INNER_X + GRAPH_INNER_W) x1 = GRAPH_INNER_X + GRAPH_INNER_W;

    // DS18B20
    int y0_ds = GRAPH_INNER_Y + GRAPH_INNER_H - (int)(constrain(graphSuhuDS[idx0], yMin, yMax) / yMax * GRAPH_INNER_H);
    int y1_ds = GRAPH_INNER_Y + GRAPH_INNER_H - (int)(constrain(graphSuhuDS[idx1], yMin, yMax) / yMax * GRAPH_INNER_H);
    lcd.drawLine(x0, y0_ds, x1, y1_ds, COL_SUHU_DS);

    // SHT31
    int y0_sht = GRAPH_INNER_Y + GRAPH_INNER_H - (int)(constrain(graphSuhuSHT[idx0], yMin, yMax) / yMax * GRAPH_INNER_H);
    int y1_sht = GRAPH_INNER_Y + GRAPH_INNER_H - (int)(constrain(graphSuhuSHT[idx1], yMin, yMax) / yMax * GRAPH_INNER_H);
    lcd.drawLine(x0, y0_sht, x1, y1_sht, COL_SUHU_SHT);

    // Kelembapan
    int y0_h = GRAPH_INNER_Y + GRAPH_INNER_H - (int)(constrain(graphHum[idx0], yMin, yMax) / yMax * GRAPH_INNER_H);
    int y1_h = GRAPH_INNER_Y + GRAPH_INNER_H - (int)(constrain(graphHum[idx1], yMin, yMax) / yMax * GRAPH_INNER_H);
    lcd.drawLine(x0, y0_h, x1, y1_h, COL_HUMIDITY);
  }
}

// ============================================================
// FUNGSI: Gambar Tombol Relay (Bawah)
// ============================================================
void drawRelayButtons() {
  const char* relayLabels[4] = {"KIPAS EXHS 1", "KIPAS EXHS 2", "KIPAS EXHS 3", "KIPAS EXHS 4"};

  int startX = (800 - (RELAY_BTN_W * 4 + RELAY_GAP * 3)) / 2;

  for (int i = 0; i < 4; i++) {
    int bx = startX + i * (RELAY_BTN_W + RELAY_GAP);
    int by = RELAY_Y;

    bool isOn = sensorData.relay[i];
    uint16_t btnCol = isOn ? COL_RELAY_BTN_ON : COL_RELAY_BTN_OFF;
    uint16_t borderCol = isOn ? COL_RELAY_ON : COL_RELAY_OFF;

    lcd.fillRoundRect(bx, by, RELAY_BTN_W, RELAY_BTN_H, 8, btnCol);
    lcd.drawRoundRect(bx, by, RELAY_BTN_W, RELAY_BTN_H, 8, borderCol);

    // Status dot
    int dotX = bx + 12;
    int dotY = by + RELAY_BTN_H / 2;
    lcd.fillCircle(dotX, dotY, 6, isOn ? COL_RELAY_ON : COL_RELAY_OFF);

    // Label relay
    lcd.setFont(&fonts::Font2);
    lcd.setTextColor(COL_TEXT_DIM);
    lcd.setCursor(bx + 26, by + 8);
    lcd.print(relayLabels[i]);

    // Status ON/OFF
    lcd.setFont(&fonts::FreeSansBold12pt7b);
    lcd.setTextColor(isOn ? COL_RELAY_ON : COL_RELAY_OFF);
    lcd.setCursor(bx + 26, by + 26);
    lcd.print(isOn ? "ON" : "OFF");
  }

  // Status bar di bawah relay
  int barY = RELAY_Y + RELAY_BTN_H + 5;
  lcd.fillRect(0, barY, 800, 20, COL_HEADER_BG);

  lcd.setFont(&fonts::Font2);

  // Mode
  lcd.setTextColor(sensorData.mode == 0 ? COL_MODE_AUTO : COL_MODE_MANUAL);
  lcd.setCursor(10, barY + 3);
  lcd.print("Mode: ");
  lcd.print(sensorData.mode == 0 ? "OTOMATIS" : "MANUAL");

  // Suhu max
  lcd.setTextColor(COL_TEXT_DIM);
  lcd.setCursor(200, barY + 3);
  lcd.print("Suhu Maks: 50.0 *C");

  // Suhu tertinggi
  lcd.setCursor(400, barY + 3);
  lcd.print("Suhu Tertinggi: ");
  lcd.setTextColor(sensorData.suhuMax > 50.0 ? COL_STATUS_OVERTEMP : COL_TEXT_WHITE);
  lcd.printf("%.1f *C", sensorData.suhuMax);

  // Connection status
  lcd.setTextColor(sensorData.dataReceived ? COL_RELAY_ON : COL_RELAY_OFF);
  lcd.setCursor(630, barY + 3);
  lcd.print(sensorData.dataReceived ? "ONLINE" : "OFFLINE");

  // WiFi indicator
  lcd.setTextColor(wifiConnected ? COL_MODE_CAM : COL_TEXT_DIM);
  lcd.setCursor(720, barY + 3);
  lcd.print(wifiConnected ? "WiFi OK" : "No WiFi");
}

// ============================================================
// FUNGSI: Gambar "Menunggu Data..."
// ============================================================
void drawWaiting() {
  lcd.fillScreen(COL_BG);
  lcd.setFont(&fonts::FreeSansBold12pt7b);
  lcd.setTextColor(COL_TEXT_DIM);
  lcd.setCursor(250, 200);
  lcd.print("Menunggu data dari");
  lcd.setCursor(280, 230);
  lcd.print("Sensor Board...");
  lcd.setFont(&fonts::Font2);
  lcd.setTextColor(COL_TEXT_LABEL);
  lcd.setCursor(230, 270);
  lcd.print("Pastikan koneksi UART (TX/RX/GND) benar");
  
  // Tampilkan MAC Address langsung di layar
  lcd.setCursor(240, 310);
  lcd.setTextColor(COL_MODE_CAM);
  lcd.print("MAC Address (Penerima): ");
  lcd.print(WiFi.macAddress());
}

// ============================================================
// FUNGSI: Gambar seluruh UI (Dashboard)
// ============================================================
void drawFullUI() {
  lcd.fillScreen(COL_BG);
  drawHeader();
  drawSensorPanel();
  drawGraph();
  drawRelayButtons();
  needFullRedraw = false;
}

// ============================================================
// FUNGSI: Update UI (hanya bagian yang berubah)
// ============================================================
void updateUI() {
  // Update sensor panel (selalu update karena nilai berubah)
  drawSensorPanel();

  // Update grafik
  drawGraph();

  // Update relay buttons jika berubah
  bool relayChanged = false;
  for (int i = 0; i < 4; i++) {
    if (prevRelay[i] != sensorData.relay[i]) {
      relayChanged = true;
      prevRelay[i] = sensorData.relay[i];
    }
  }

  if (relayChanged || prevStatus != sensorData.status) {
    drawRelayButtons();
    prevStatus = sensorData.status;
  }

  // Update mode buttons jika berubah
  if (prevMode != sensorData.mode) {
    drawModeButtons();
    prevMode = sensorData.mode;
  }

  // Update warna jika berubah
  if (prevWarna != sensorData.warna) {
    prevWarna = sensorData.warna;
  }
}

// ============================================================
// FUNGSI: Gambar Camera View (Full-screen)
// ============================================================
// ============================================================
// FUNGSI: Menggambar Foto yang Diterima (ESP-NOW)
// ============================================================
// Helper untuk mendapatkan dimensi JPEG dari buffer agar penskalaan otomatis bekerja dengan baik
bool getJpgSize(const uint8_t* data, size_t len, int* width, int* height) {
  if (len < 4) return false;
  if (data[0] != 0xFF || data[1] != 0xD8) return false;
  
  size_t i = 2;
  while (i < len - 4) {
    if (data[i] != 0xFF) {
      i++;
      continue;
    }
    uint8_t marker = data[i+1];
    if (marker == 0xFF) {
      i++;
      continue;
    }
    
    i += 2; // Lewati 0xFF dan marker
    
    if (marker == 0xD9 || marker == 0xDA) {
      // EOI atau SOS, hentikan parsing
      break;
    }
    
    // RST markers (0xD0 - 0xD7) dan TEM (0x01) tidak memiliki data panjang block
    if (marker == 0x01 || (marker >= 0xD0 && marker <= 0xD7)) {
      continue;
    }
    
    // Baca panjang block (big endian)
    uint16_t blockLength = (data[i] << 8) | data[i+1];
    
    // Periksa jika marker ini adalah Start of Frame (SOF)
    if ((marker >= 0xC0 && marker <= 0xCF) && marker != 0xC4 && marker != 0xC8 && marker != 0xCC) {
      if (i + 6 < len) {
        *height = (data[i+3] << 8) | data[i+4];
        *width = (data[i+5] << 8) | data[i+6];
        return true;
      }
      return false;
    }
    
    i += blockLength;
  }
  return false;
}

void drawReceivedPhoto() {
  if (receivedPhotoSize == 0 || !camFrameBuf) return;

  int imgW = 320;
  int imgH = 240;

  // Dapatkan dimensi gambar JPEG dari buffer secara dinamis
  if (!getJpgSize(camFrameBuf, receivedPhotoSize, &imgW, &imgH)) {
    // Fallback jika gagal membaca header JPEG
    imgW = 320;
    imgH = 240;
  }

  // Hitung rasio penskalaan agar gambar pas (fit) di dalam area video (800 x 410)
  float scaleX = (float)CAM_VIDEO_W / imgW;
  float scaleY = (float)CAM_VIDEO_H / imgH;
  float scale = (scaleX < scaleY) ? scaleX : scaleY; // Gunakan rasio terkecil agar seluruh gambar terlihat

  // Hitung dimensi gambar ter-skala
  int scaledW = imgW * scale;
  int scaledH = imgH * scale;

  // Pusatkan gambar di dalam area video
  int drawX = CAM_VIDEO_X + (CAM_VIDEO_W - scaledW) / 2;
  int drawY = CAM_VIDEO_Y + (CAM_VIDEO_H - scaledH) / 2;

  // Bersihkan area video terlebih dahulu
  lcd.fillRect(CAM_VIDEO_X, CAM_VIDEO_Y, CAM_VIDEO_W, CAM_VIDEO_H, COL_GRAPH_BG);
  lcd.drawRect(CAM_VIDEO_X, CAM_VIDEO_Y, CAM_VIDEO_W, CAM_VIDEO_H, COL_PANEL_BORDER);

  // Gambar JPG dari buffer dengan faktor penskalaan (scale) yang benar sebagai argumen ke-9 dan ke-10
  lcd.drawJpg(camFrameBuf, receivedPhotoSize, drawX, drawY, scaledW, scaledH, 0, 0, scale, scale);
  
  lastPhotoTime = millis();
}

// ============================================================
// FUNGSI: Gambar Camera View (Full-screen)
// ============================================================
void drawCameraUI() {
  lcd.fillScreen(COL_BG);

  // ---- Header ----
  lcd.fillRect(0, 0, 800, CAM_HEADER_H, COL_HEADER_BG);

  // Tombol BACK
  lcd.fillRoundRect(CAM_BACK_BTN_X, CAM_BACK_BTN_Y, CAM_BACK_BTN_W, CAM_BACK_BTN_H, 6, COL_PANEL);
  lcd.drawRoundRect(CAM_BACK_BTN_X, CAM_BACK_BTN_Y, CAM_BACK_BTN_W, CAM_BACK_BTN_H, 6, COL_MODE_AUTO);
  lcd.setFont(&fonts::FreeSansBold9pt7b);
  lcd.setTextColor(COL_MODE_AUTO);
  lcd.setCursor(CAM_BACK_BTN_X + 8, CAM_BACK_BTN_Y + 7);
  lcd.print("BACK");

  // Judul
  lcd.setFont(&fonts::FreeSansBold12pt7b);
  lcd.setTextColor(COL_HEADER_TEXT);
  lcd.setCursor(110, 8);
  lcd.print("FOTO TERAKHIR - RUMAH PENGERING");

  // Garis accent
  lcd.fillRect(0, CAM_HEADER_H - 2, 800, 2, COL_MODE_CAM);

  // ---- Video Area (Photo box) ----
  lcd.fillRect(CAM_VIDEO_X, CAM_VIDEO_Y, CAM_VIDEO_W, CAM_VIDEO_H, COL_GRAPH_BG);
  lcd.drawRect(CAM_VIDEO_X, CAM_VIDEO_Y, CAM_VIDEO_W, CAM_VIDEO_H, COL_PANEL_BORDER);

  if (receivedPhotoSize == 0) {
    // Loading / Waiting text jika belum ada foto
    lcd.setFont(&fonts::FreeSansBold9pt7b);
    lcd.setTextColor(COL_TEXT_DIM);
    lcd.setCursor(260, CAM_VIDEO_Y + CAM_VIDEO_H / 2 - 20);
    lcd.print("Menunggu foto dari kamera...");
    
    lcd.setFont(&fonts::Font2);
    lcd.setCursor(290, CAM_VIDEO_Y + CAM_VIDEO_H / 2 + 5);
    lcd.print("Kamera mengirim foto setiap 5 detik");

    // Tampilkan MAC Address
    lcd.setCursor(250, CAM_VIDEO_Y + CAM_VIDEO_H / 2 + 30);
    lcd.setTextColor(COL_MODE_CAM);
    lcd.print("MAC Address (Penerima): ");
    lcd.print(WiFi.macAddress());
  } else {
    // Tampilkan foto terakhir yang berhasil didecode
    drawReceivedPhoto();
  }

  // ---- Status Bar ----
  drawCameraStatusBar();
}

// ============================================================
// FUNGSI: Gambar Status Bar Kamera
// ============================================================
void drawCameraStatusBar() {
  lcd.fillRect(0, CAM_STATUS_Y, 800, CAM_STATUS_H, COL_HEADER_BG);
  lcd.setFont(&fonts::Font2);

  // Status koneksi berdasarkan waktu terima paket terakhir (< 15 detik = ONLINE)
  bool isCamOnline = (lastPacketTime > 0 && (millis() - lastPacketTime < 15000));
  
  lcd.setTextColor(isCamOnline ? COL_RELAY_ON : COL_RELAY_OFF);
  lcd.setCursor(10, CAM_STATUS_Y + 8);
  lcd.print(isCamOnline ? "CAMERA ONLINE" : "CAMERA OFFLINE");

  // Waktu penerimaan
  lcd.setTextColor(COL_TEXT_DIM);
  lcd.setCursor(170, CAM_STATUS_Y + 8);
  if (lastPhotoTime > 0) {
    lcd.printf("Diterima: %ds yang lalu", (millis() - lastPhotoTime) / 1000);
  } else {
    lcd.print("Belum ada foto");
  }

  // Ukuran foto
  lcd.setCursor(350, CAM_STATUS_Y + 8);
  lcd.printf("Ukuran: %.1f KB", receivedPhotoSize / 1024.0);

  // WiFi Channel saat ini
  uint8_t primaryChan = 1;
  wifi_second_chan_t secondChan;
  esp_wifi_get_channel(&primaryChan, &secondChan);
  
  lcd.setTextColor(COL_MODE_CAM);
  lcd.setCursor(500, CAM_STATUS_Y + 8);
  lcd.printf("ESP-NOW Channel: %d", primaryChan);

  // Info data sensor mini
  lcd.setTextColor(COL_SUHU_DS);
  lcd.setCursor(720, CAM_STATUS_Y + 8);
  lcd.printf("%.1f*C", sensorData.suhuDS);
}

// ============================================================
// FUNGSI: Handle Touch Input
// ============================================================
void handleTouch() {
  int32_t tx, ty;
  if (!lcd.getTouch(&tx, &ty)) return;

  // Debounce
  if (millis() - lastTouchTime < TOUCH_DEBOUNCE) return;
  lastTouchTime = millis();

  // ============================================================
  // MODE KAMERA: Handle touch di camera view
  // ============================================================
  if (showCamera) {
    // --- Cek Tombol BACK ---
    if (tx >= CAM_BACK_BTN_X && tx <= CAM_BACK_BTN_X + CAM_BACK_BTN_W &&
        ty >= CAM_BACK_BTN_Y && ty <= CAM_BACK_BTN_Y + CAM_BACK_BTN_H) {
      // Kembali ke dashboard
      showCamera = false;
      needFullRedraw = true;
      return;
    }
    return; // Tidak ada aksi lain di camera view
  }

  // ============================================================
  // MODE DASHBOARD: Handle touch di dashboard
  // ============================================================

  int btnY = HEADER_Y + 7;

  // --- Cek Tombol FOTO (sebelumnya CAM) ---
  int camX = 800 - MODE_BTN_W * 3 - 30;
  if (tx >= camX && tx <= camX + MODE_BTN_W &&
      ty >= btnY && ty <= btnY + MODE_BTN_H) {
    showCamera = true;
    drawCameraUI();
    return;
  }

  // --- Cek Tombol Mode AUTO ---
  int autoX = 800 - MODE_BTN_W * 2 - 20;
  if (tx >= autoX && tx <= autoX + MODE_BTN_W &&
      ty >= btnY && ty <= btnY + MODE_BTN_H) {
    sendCommand("AUTO");
    sensorData.mode = 0;
    drawModeButtons();
    return;
  }

  // --- Cek Tombol Mode MANUAL ---
  int manX = 800 - MODE_BTN_W - 10;
  if (tx >= manX && tx <= manX + MODE_BTN_W &&
      ty >= btnY && ty <= btnY + MODE_BTN_H) {
    sendCommand("MANUAL");
    sensorData.mode = 1;
    drawModeButtons();
    return;
  }

  // --- Cek Tombol Relay (hanya di mode manual) ---
  if (sensorData.mode == 1) {
    int startX = (800 - (RELAY_BTN_W * 4 + RELAY_GAP * 3)) / 2;

    for (int i = 0; i < 4; i++) {
      int bx = startX + i * (RELAY_BTN_W + RELAY_GAP);
      int by = RELAY_Y;

      if (tx >= bx && tx <= bx + RELAY_BTN_W &&
          ty >= by && ty <= by + RELAY_BTN_H) {
        // Toggle relay
        bool newState = !sensorData.relay[i];
        sensorData.relay[i] = newState;

        // Kirim perintah
        String cmd = "R" + String(i + 1) + ":" + (newState ? "ON" : "OFF");
        sendCommand(cmd);

        // Update visual
        drawRelayButtons();
        return;
      }
    }
  }
}

// ============================================================
// FUNGSI: Inisialisasi WiFi
// ============================================================
void initWiFi() {
  // Muat WiFi dari Preferences
  preferences.begin("wifi-config", true);
  activeSSID = preferences.getString("ssid", wifi_ssid);
  activePASS = preferences.getString("pass", wifi_password);
  preferences.end();

  Serial.println("[WiFi] Connecting...");
  Serial.printf("[WiFi] SSID: %s\n", activeSSID.c_str());

  WiFi.mode(WIFI_STA);
  WiFi.begin(activeSSID.c_str(), activePASS.c_str());

  // Non-blocking: coba konek selama 10 detik
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    delay(500);
    Serial.print(".");
    attempts++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    wifiConnected = true;
    Serial.println();
    Serial.print("[WiFi] Connected! IP: ");
    Serial.println(WiFi.localIP());
    Serial.printf("[WiFi] RSSI: %d dBm\n", WiFi.RSSI());
  } else {
    wifiConnected = false;
    Serial.println();
    Serial.println("[WiFi] Connection failed! Camera feature disabled.");
    Serial.println("[WiFi] Dashboard will work normally via UART.");
  }
}

// ============================================================
// SETUP
// ============================================================
void setup() {
  // Debug Serial (USB)
  Serial.begin(115200);
  delay(1500); // Beri waktu port USB CDC terdeteksi dan terhubung oleh PC
  Serial.println("ESP32-8048S043R Display starting...");

  // Inisialisasi display
  lcd.init();
  lcd.setRotation(0);
  lcd.setBrightness(200);

  // Kalibrasi touch (sesuaikan jika diperlukan)
  // lcd.setTouchCalibrate(...);

  // Inisialisasi Serial ke sensor board
  SensorSerial.begin(115200, SERIAL_8N1, SENSOR_RX_PIN, SENSOR_TX_PIN);
  Serial.println("Sensor UART initialized (RX=" + String(SENSOR_RX_PIN) +
                 ", TX=" + String(SENSOR_TX_PIN) + ")");

  // Inisialisasi graph buffer
  for (int i = 0; i < GRAPH_POINTS; i++) {
    graphSuhuDS[i]  = 0;
    graphSuhuSHT[i] = 0;
    graphHum[i]     = 0;
  }

  // Alokasi buffer JPEG untuk streaming kamera
  camFrameBuf = (uint8_t*)malloc(CAM_BUF_SIZE);
  if (camFrameBuf) {
    Serial.printf("[CAM] JPEG buffer allocated: %d bytes\n", CAM_BUF_SIZE);
  } else {
    Serial.println("[CAM] WARNING: Failed to allocate JPEG buffer!");
  }

  // Tampilkan layar awal
  drawWaiting();

  // Inisialisasi WiFi (non-blocking, max 10 detik)
  initWiFi();

  // Tampilkan MAC Address untuk keperluan konfigurasi ESP32-CAM
  Serial.print("[WiFi] MAC Address Display: ");
  Serial.println(WiFi.macAddress());

  // Inisialisasi ESP-NOW untuk menerima foto
  initESPNow();

  Serial.println("Display ready!");
}

// ============================================================
// LOOP
// ============================================================
void loop() {
  // ---- 1. BACA DATA DARI SENSOR BOARD (selalu, bahkan di camera mode) ----
  while (SensorSerial.available()) {
    char ch = SensorSerial.read();
    if (ch == '\n') {
      parseData(serialBuffer);
      serialBuffer = "";
      lastDataTime = millis();
    } else if (ch != '\r') {
      serialBuffer += ch;
    }
  }

  // ---- 2. HANDLE TOUCH INPUT ----
  handleTouch();

  // ---- 3. MODE KAMERA ----
  if (showCamera) {
    // Tampilkan foto baru secara otomatis (Auto-Refresh)
    if (newPhotoAvailable) {
      newPhotoAvailable = false;
      drawReceivedPhoto();
      drawCameraStatusBar();
    }

    // Update status bar secara berkala (tiap 1 detik) untuk memperbarui durasi terima foto
    static unsigned long lastStatusUpdate = 0;
    if (millis() - lastStatusUpdate > 1000) {
      lastStatusUpdate = millis();
      drawCameraStatusBar();
    }

    // Delay sedikit untuk responsivitas touch tombol BACK
    delay(50);
    return; // Skip dashboard update
  }

  // ---- 4. MODE DASHBOARD: UPDATE DISPLAY ----
  if (sensorData.dataReceived) {
    if (needFullRedraw) {
      drawFullUI();
    } else {
      updateUI();
    }
  }

  // Cek timeout koneksi (5 detik tidak ada data)
  if (lastDataTime > 0 && millis() - lastDataTime > 5000) {
    sensorData.dataReceived = false;
    if (!needFullRedraw) {
      drawWaiting();
      needFullRedraw = true;
    }
  }

  // Cek WiFi status secara berkala
  static unsigned long lastWiFiCheck = 0;
  if (millis() - lastWiFiCheck > 10000) {
    lastWiFiCheck = millis();
    wifiConnected = (WiFi.status() == WL_CONNECTED);
    if (!wifiConnected && WiFi.status() != WL_CONNECTED) {
      WiFi.reconnect();
    }
  }

  delay(500); // Refresh rate ~20fps untuk touch responsiveness
}
