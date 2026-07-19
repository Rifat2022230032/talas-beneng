#include <Wire.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <Adafruit_SHT31.h>
#include <Adafruit_TCS34725.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Preferences.h>

// ============================================
// KONFIGURASI PIN
// ============================================

// DS18B20
#define ONE_WIRE_BUS 5

// Relay 4-Channel (Active LOW: LOW = ON, HIGH = OFF)
#define RELAY_1_PIN 25  // Kipas Exhaust 1
#define RELAY_2_PIN 26  // Kipas Exhaust 2
#define RELAY_3_PIN 27  // Kipas Exhaust 3
#define RELAY_4_PIN 14  // Kipas Exhaust 4

// UART ke Display Board
#define DISPLAY_RX_PIN 16
#define DISPLAY_TX_PIN 17

// TCS34725 LED
#define TCS_LED_PIN 13  // Kontrol LED Sensor TCS34725 (LOW = Mati, HIGH = Nyala)

// ============================================
// KONFIGURASI WIFI & SERVER IOT
// ============================================
#define WIFI_SSID "beneng"
#define WIFI_PASS "12345678"
#define SERVER_URL "https://beneng.jeptira.id/api/receive_sensor.php" // Ganti dengan IP server Anda

// Variabel global WiFi dinamis
Preferences preferences;
String activeSSID = WIFI_SSID;
String activePASS = WIFI_PASS;

// ============================================
// KONSTANTA & VARIABEL PENGERINGAN DINAMIS
// ============================================
// Nilai default awal (akan diperbarui otomatis dari server)
float suhuMaks = 50.0;  // °C - batas suhu maksimal keamanan (kipas OFF)
float humMaks = 60.0;   // %RH - batas kelembapan maksimal (kipas ON)
float humMin = 50.0;    // %RH - batas kelembapan minimal/target (kipas OFF)

// ============================================
// THRESHOLD DETEKSI WARNA DAUN
// ============================================
// Menggunakan rasio warna (lebih robust terhadap perubahan cahaya)
// Rasio = komponen / (R + G + B)
#define RASIO_G_HIJAU       0.40  // G/(R+G+B) > 0.40 = Hijau (Segar)
#define RASIO_R_HIJAU       0.30  // R/(R+G+B) < 0.30 = Hijau (Segar)
#define RASIO_G_KUNING_HIJAU 0.33 // G/(R+G+B) 0.33-0.40 = Kuning-Hijau
#define RASIO_R_KUNING_COKLAT 0.38 // R/(R+G+B) 0.38-0.42 = Kuning-Coklat
#define RASIO_G_COKLAT      0.28  // G/(R+G+B) < 0.28 = Coklat (Kering)
#define RASIO_R_COKLAT      0.42  // R/(R+G+B) > 0.42 = Coklat (Kering)

// ============================================
// ENUM STATUS WARNA DAUN
// ============================================
enum StatusWarna {
  WARNA_HIJAU,         // Daun segar, baru dimasukkan
  WARNA_KUNING_HIJAU,  // Proses pengeringan awal
  WARNA_KUNING_COKLAT, // Hampir kering
  WARNA_COKLAT,        // Kering / selesai
  WARNA_TIDAK_DIKETAHUI
};

// ============================================
// ENUM STATUS SISTEM
// ============================================
enum StatusSistem {
  SISTEM_PROSES,       // Pengeringan berjalan
  SISTEM_OVERTEMP,     // Suhu melebihi batas
  SISTEM_SELESAI,      // Daun sudah kering
  SISTEM_ERROR         // Sensor error
};

// ============================================
// OBJEK SENSOR
// ============================================
OneWire oneWire(ONE_WIRE_BUS);
DallasTemperature ds18b20(&oneWire);
Adafruit_SHT31 sht31 = Adafruit_SHT31();

// TCS34725: Integration time 154ms, Gain 4x
// Cocok untuk kondisi dalam ruang pengering tertutup
Adafruit_TCS34725 tcs = Adafruit_TCS34725(
  TCS34725_INTEGRATIONTIME_154MS,
  TCS34725_GAIN_4X
);

// ============================================
// VARIABEL GLOBAL & OBJEK KOMUNIKASI
// ============================================
HardwareSerial DisplaySerial(2);

int mode = 0; // Mode kontrol (0 = AUTO, 1 = MANUAL)
String displayBuffer = ""; // Buffer untuk perintah dari display

// Non-blocking timing
unsigned long lastSensorReadTime = 0;
const unsigned long sensorReadInterval = 5000; // Baca & Kirim setiap 5 detik

// Non-blocking WiFi reconnect timing
unsigned long lastWiFiReconnectAttempt = 0;
const unsigned long wifiReconnectInterval = 30000; // Coba reconnect setiap 30 detik jika terputus

bool relayState[4] = {false, false, false, false}; // Status relay (true=ON)
StatusWarna statusWarnaDaun = WARNA_TIDAK_DIKETAHUI;
StatusSistem statusSistem = SISTEM_PROSES;
bool tcsReady = false; // Flag sensor TCS34725 terdeteksi
bool exhaustHumActive = false; // Status aktif exhaust berdasarkan kelembapan (histeresis)
bool localSettingsDirty = false; // Flag untuk menandai perubahan lokal dari display touchscreen

// Pin relay dalam array untuk kemudahan kontrol
const int relayPins[4] = {RELAY_1_PIN, RELAY_2_PIN, RELAY_3_PIN, RELAY_4_PIN};
const char* relayNama[4] = {
  "Kipas Exhaust 1",
  "Kipas Exhaust 2",
  "Kipas Exhaust 3",
  "Kipas Exhaust 4"
};

// ============================================
// FUNGSI: Nyalakan semua relay
// ============================================
void nyalakanSemuaRelay() {
  for (int i = 0; i < 4; i++) {
    digitalWrite(relayPins[i], LOW); // Active LOW
    relayState[i] = true;
  }
}

// ============================================
// FUNGSI: Matikan semua relay
// ============================================
void matikanSemuaRelay() {
  for (int i = 0; i < 4; i++) {
    digitalWrite(relayPins[i], HIGH); // Active LOW
    relayState[i] = false;
  }
}

// ============================================
// FUNGSI: Deteksi warna daun dari sensor TCS34725
// ============================================
StatusWarna deteksiWarnaDaun(uint16_t r, uint16_t g, uint16_t b, uint16_t c) {
  // Cek apakah ada objek di depan sensor (clear value terlalu rendah = tidak ada objek)
  if (c < 100) {
    return WARNA_TIDAK_DIKETAHUI;
  }

  // Hitung total RGB
  float total = (float)(r + g + b);
  if (total == 0) {
    return WARNA_TIDAK_DIKETAHUI;
  }

  // Hitung rasio masing-masing komponen
  float rasioR = (float)r / total;
  float rasioG = (float)g / total;

  // Klasifikasi berdasarkan rasio warna
  // 1. HIJAU (Segar): G dominan, R rendah
  if (rasioG > RASIO_G_HIJAU && rasioR < RASIO_R_HIJAU) {
    return WARNA_HIJAU;
  }
  // 2. COKLAT (Kering): R dominan, G rendah
  else if (rasioG < RASIO_G_COKLAT && rasioR > RASIO_R_COKLAT) {
    return WARNA_COKLAT;
  }
  // 3. KUNING-COKLAT (Hampir Kering): R mulai dominan
  else if (rasioR >= RASIO_R_KUNING_COKLAT && rasioG >= RASIO_G_COKLAT) {
    return WARNA_KUNING_COKLAT;
  }
  // 4. KUNING-HIJAU (Proses Awal): G masih sedikit dominan
  else if (rasioG >= RASIO_G_KUNING_HIJAU && rasioR < RASIO_R_KUNING_COKLAT) {
    return WARNA_KUNING_HIJAU;
  }
  // 5. Tidak dapat diklasifikasikan
  else {
    return WARNA_TIDAK_DIKETAHUI;
  }
}

// ============================================
// FUNGSI: Dapatkan nama status warna
// ============================================
const char* getNamaWarna(StatusWarna warna) {
  switch (warna) {
    case WARNA_HIJAU:         return "HIJAU (Segar)";
    case WARNA_KUNING_HIJAU:  return "KUNING-HIJAU (Proses Awal)";
    case WARNA_KUNING_COKLAT: return "KUNING-COKLAT (Hampir Kering)";
    case WARNA_COKLAT:        return "COKLAT (Kering/Selesai)";
    default:                  return "TIDAK DIKETAHUI";
  }
}

// ============================================
// FUNGSI: Dapatkan nama status sistem
// ============================================
const char* getStatusSistem(StatusSistem status) {
  switch (status) {
    case SISTEM_PROSES:   return "PROSES PENGERINGAN";
    case SISTEM_OVERTEMP: return "OVERTEMP - KIPAS OFF";
    case SISTEM_SELESAI:  return "SELESAI - DAUN KERING";
    case SISTEM_ERROR:    return "ERROR SENSOR";
    default:              return "UNKNOWN";
  }
}

// ============================================
// FUNGSI: Kirim data sensor ke Display Board via UART
// ============================================
void sendDisplayData(float suhuDS, float suhuSHT, float hum, float suhuMax, int warna, int r, int g, int b) {
  DisplaySerial.print("DATA:");
  DisplaySerial.print("suhuDS:"); DisplaySerial.print(suhuDS, 1); DisplaySerial.print(",");
  DisplaySerial.print("suhuSHT:"); DisplaySerial.print(suhuSHT, 1); DisplaySerial.print(",");
  DisplaySerial.print("hum:"); DisplaySerial.print(hum, 1); DisplaySerial.print(",");
  DisplaySerial.print("suhuMax:"); DisplaySerial.print(suhuMax, 1); DisplaySerial.print(",");
  DisplaySerial.print("warna:"); DisplaySerial.print(warna); DisplaySerial.print(",");
  DisplaySerial.print("r:"); DisplaySerial.print(r); DisplaySerial.print(",");
  DisplaySerial.print("g:"); DisplaySerial.print(g); DisplaySerial.print(",");
  DisplaySerial.print("b:"); DisplaySerial.print(b); DisplaySerial.print(",");
  DisplaySerial.print("relay1:"); DisplaySerial.print(relayState[0] ? 1 : 0); DisplaySerial.print(",");
  DisplaySerial.print("relay2:"); DisplaySerial.print(relayState[1] ? 1 : 0); DisplaySerial.print(",");
  DisplaySerial.print("relay3:"); DisplaySerial.print(relayState[2] ? 1 : 0); DisplaySerial.print(",");
  DisplaySerial.print("relay4:"); DisplaySerial.print(relayState[3] ? 1 : 0); DisplaySerial.print(",");
  DisplaySerial.print("status:"); DisplaySerial.print((int)statusSistem); DisplaySerial.print(",");
  DisplaySerial.print("mode:"); DisplaySerial.println(mode);
  
  // Debug print lokal ke PC
  Serial.println("[UART] Data sensor terkirim ke Display");
}

// ============================================
// FUNGSI: Parsing perintah manual dari Display Board
// ============================================
void parseCommand(String cmd) {
  cmd.trim();
  if (!cmd.startsWith("CMD:")) return;
  cmd = cmd.substring(4); // Hapus "CMD:"

  if (cmd == "AUTO") {
    if (mode != 0) {
      mode = 0;
      localSettingsDirty = true;
      Serial.println("[Display CMD] Mode AUTO diaktifkan");
    }
  } 
  else if (cmd == "MANUAL") {
    if (mode != 1) {
      mode = 1;
      localSettingsDirty = true;
      Serial.println("[Display CMD] Mode MANUAL diaktifkan");
    }
  } 
  else if (cmd.startsWith("R")) {
    // Format: R1:ON atau R1:OFF, R2:ON, dst.
    int colonIdx = cmd.indexOf(':');
    if (colonIdx == -1) return;

    String relayNumStr = cmd.substring(1, colonIdx);
    String stateStr = cmd.substring(colonIdx + 1);

    int relayIdx = relayNumStr.toInt() - 1;
    if (relayIdx >= 0 && relayIdx < 4) {
      bool newState = (stateStr == "ON");
      if (mode == 1) { // Hanya izinkan kontrol manual jika dalam mode MANUAL
        if (relayState[relayIdx] != newState) {
          relayState[relayIdx] = newState;
          localSettingsDirty = true;
          // Output ke pin relay langsung diperbarui
          digitalWrite(relayPins[relayIdx], newState ? LOW : HIGH); // Active LOW
          Serial.printf("[Display CMD] Relay %d diatur ke %s\n", relayIdx + 1, newState ? "ON" : "OFF");
        }
      } else {
        Serial.println("[Display CMD] Command relay diabaikan (bukan mode MANUAL)");
      }
    }
  }
}

// ============================================
// FUNGSI: Membaca buffer serial perintah dari Display Board
// ============================================
void readDisplayCommands() {
  while (DisplaySerial.available()) {
    char ch = DisplaySerial.read();
    if (ch == '\n') {
      parseCommand(displayBuffer);
      displayBuffer = "";
    } else if (ch != '\r') {
      displayBuffer += ch;
    }
  }
}

// ============================================
// FUNGSI: Menghubungkan ke Jaringan WiFi
// ============================================
void connectWiFi() {
  Serial.println();
  Serial.print("[WiFi] Menyambungkan ke ");
  Serial.println(activeSSID);
  
  WiFi.begin(activeSSID.c_str(), activePASS.c_str());
  
  int attempts = 0;
  // Coba menyambungkan maksimal 10 detik (20 * 500ms)
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    delay(500);
    Serial.print(".");
    attempts++;
  }
  
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println();
    Serial.println("[WiFi] Terhubung!");
    Serial.print("[WiFi] IP Address: ");
    Serial.println(WiFi.localIP());
  } else {
    Serial.println();
    Serial.println("[WiFi] Gagal terhubung. Akan dicoba kembali nanti.");
  }
}

// ============================================
// FUNGSI: Cek dan Hubungkan WiFi secara Non-Blocking
// ============================================
void checkWiFiConnection() {
  if (WiFi.status() != WL_CONNECTED) {
    unsigned long currentMillis = millis();
    if (currentMillis - lastWiFiReconnectAttempt >= wifiReconnectInterval) {
      lastWiFiReconnectAttempt = currentMillis;
      Serial.println();
      Serial.println("[WiFi] Koneksi terputus! Mencoba menghubungkan kembali secara non-blocking...");
      WiFi.begin(activeSSID.c_str(), activePASS.c_str());
    }
  }
}

// ============================================
// FUNGSI: Mengirim data sensor ke web API & Update Settings
// ============================================
void sendDataToServer(float tempDS, float humidityVal, float tempSHT, bool e1, bool e2, bool e3, bool e4, int wifiRSSI, bool updateSettings) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[IoT] Gagal mengirim data, WiFi tidak terhubung.");
    return;
  }

  HTTPClient http;
  http.begin(SERVER_URL);
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");
  http.setTimeout(4000); // Timeout 4 detik

  // Hitung exhaust global (logical OR dari keempat channel)
  int exhaustVal = (e1 || e2 || e3 || e4) ? 1 : 0;

  // Susun data POST dengan status per-channel dan mode kontrol
  String postData = "temperature=" + String(tempDS, 1) +
                    "&humidity=" + String(humidityVal, 1) +
                    "&sht_temperature=" + String(tempSHT, 1) +
                    "&exhaust=" + String(exhaustVal) +
                    "&exhaust1=" + String(e1 ? 1 : 0) +
                    "&exhaust2=" + String(e2 ? 1 : 0) +
                    "&exhaust3=" + String(e3 ? 1 : 0) +
                    "&exhaust4=" + String(e4 ? 1 : 0) +
                    "&wifi=" + String(wifiRSSI) +
                    "&control_mode=" + String(mode) +
                    "&update_settings=" + String(updateSettings ? 1 : 0);

  Serial.println("[IoT] Mengirim data ke server: " + postData);
  int httpResponseCode = http.POST(postData);

  if (httpResponseCode > 0) {
    Serial.printf("[IoT] Respon Server: %d\n", httpResponseCode);
    
    if (httpResponseCode == HTTP_CODE_OK) {
      if (updateSettings) {
        localSettingsDirty = false;
        Serial.println("[IoT] Pengaturan lokal disinkronkan ke server successfully.");
      }
      String response = http.getString();
      Serial.println("[IoT] Payload: " + response);

      StaticJsonDocument<768> doc;
      DeserializationError error = deserializeJson(doc, response);

      if (!error) {
        // Cek pembaruan WiFi Config
        if (doc.containsKey("wifi_ssid") && doc.containsKey("wifi_password")) {
          String new_ssid = doc["wifi_ssid"].as<String>();
          String new_pass = doc["wifi_password"].as<String>();
          if (new_ssid.length() > 0 && (new_ssid != activeSSID || new_pass != activePASS)) {
            Serial.println("[WiFi] Terdeteksi perubahan konfigurasi WiFi dari server!");
            Serial.printf("[WiFi] SSID baru: '%s', Pass baru: '%s'\n", new_ssid.c_str(), new_pass.c_str());
            
            // Broadcast ke Display Board via UART
            DisplaySerial.println("WIFI_SET:" + new_ssid + ":" + new_pass);
            delay(1000); // beri jeda untuk pengiriman UART
            
            // Simpan ke Preferences NVS
            preferences.begin("wifi-config", false); // read-write
            preferences.putString("ssid", new_ssid);
            preferences.putString("pass", new_pass);
            preferences.end();
            
            Serial.println("[WiFi] Restarting ESP32 to apply new WiFi settings...");
            delay(1500);
            ESP.restart();
          }
        }

        if (doc.containsKey("suhu_maks") && doc.containsKey("hum_maks") && doc.containsKey("hum_min")) {
          suhuMaks = doc["suhu_maks"];
          humMaks = doc["hum_maks"];
          humMin = doc["hum_min"];
          Serial.println("[IoT] Pengaturan sensor diperbarui secara dinamis dari database!");
          Serial.printf("[IoT]   Suhu Maks: %.1f °C, Hum Maks: %.1f %%, Hum Min: %.1f %%\n", suhuMaks, humMaks, humMin);
        }

        // Baca Mode Kontrol (0: AUTO, 1: MANUAL)
        if (doc.containsKey("control_mode")) {
          int newMode = doc["control_mode"];
          if (newMode != mode) {
            mode = newMode;
            Serial.printf("[IoT] Mode kontrol diperbarui dari server ke: %s\n", mode == 0 ? "AUTO" : "MANUAL");
          }
        }

        // Baca Status Manual Exhaust per-channel (jika dalam mode MANUAL)
        if (mode == 1) {
          const char* exhaustKeys[4] = {"exhaust_1_control", "exhaust_2_control", "exhaust_3_control", "exhaust_4_control"};
          for (int i = 0; i < 4; i++) {
            if (doc.containsKey(exhaustKeys[i])) {
              bool newState = (doc[exhaustKeys[i]].as<int>() == 1);
              if (relayState[i] != newState) {
                relayState[i] = newState;
                Serial.printf("[IoT] Exhaust %d diperbarui dari server ke: %s\n", i + 1, newState ? "ON" : "OFF");
              }
            }
          }
        }
      } else {
        Serial.print("[IoT] Gagal melakukan parsing JSON: ");
        Serial.println(error.f_str());
      }
    }
  } else {
    Serial.printf("[IoT] Gagal mengirim POST, error: %s\n", http.errorToString(httpResponseCode).c_str());
  }
  http.end();
}

// ============================================
// SETUP
// ============================================
void setup() {
  Serial.begin(115200);
  DisplaySerial.begin(115200, SERIAL_8N1, DISPLAY_RX_PIN, DISPLAY_TX_PIN);
  Serial.println("[INIT] Display UART siap (RX=" + String(DISPLAY_RX_PIN) + ", TX=" + String(DISPLAY_TX_PIN) + ")");
  Serial.println();
  Serial.println("========================================");
  Serial.println("  IOT RUMAH PENGERING DAUN TALAS BENENG");
  Serial.println("========================================");
  Serial.println();

  // --- Muat Konfigurasi WiFi dari Preferences ---
  preferences.begin("wifi-config", true); // Buka namespace "wifi-config" mode read-only
  activeSSID = preferences.getString("ssid", WIFI_SSID);
  activePASS = preferences.getString("pass", WIFI_PASS);
  preferences.end();
  Serial.printf("[INIT] Memuat WiFi Config: SSID='%s'\n", activeSSID.c_str());

  // --- Inisialisasi WiFi ---
  WiFi.mode(WIFI_STA);
  WiFi.setAutoReconnect(true); // Aktifkan auto-reconnect bawaan ESP32
  connectWiFi();

  // --- Inisialisasi Pin Relay ---
  Serial.println("[INIT] Relay 4-Channel...");
  for (int i = 0; i < 4; i++) {
    pinMode(relayPins[i], OUTPUT);
    digitalWrite(relayPins[i], HIGH); // Mulai dalam keadaan OFF (Active LOW)
    relayState[i] = false;
  }
  Serial.println("[OK]   Relay 4-Channel siap (semua OFF)");

  // --- Inisialisasi LED TCS34725 ---
  pinMode(TCS_LED_PIN, OUTPUT);
  digitalWrite(TCS_LED_PIN, LOW); // Matikan LED sensor
  Serial.println("[OK]   TCS34725 LED pin dikonfigurasi (Mati)");

  // --- Inisialisasi DS18B20 ---
  Serial.println("[INIT] DS18B20...");
  ds18b20.begin();
  Serial.println("[OK]   DS18B20 siap");

  // --- Inisialisasi I2C ---
  Wire.begin(21, 22);

  // --- Inisialisasi SHT31 ---
  Serial.println("[INIT] SHT31...");
  if (!sht31.begin(0x44)) {
    Serial.println("[FAIL] SHT31 tidak terdeteksi!");
    while (1) delay(1000);
  }
  Serial.println("[OK]   SHT31 siap (0x44)");

  // --- Inisialisasi TCS34725 ---
  Serial.println("[INIT] TCS34725...");
  if (tcs.begin()) {
    tcsReady = true;
    tcs.setInterrupt(true); // Matikan LED onboard sensor TCS34725 (active low)
    Serial.println("[OK]   TCS34725 siap (0x29), LED dimatikan");
  } else {
    tcsReady = false;
    Serial.println("[WARN] TCS34725 tidak terdeteksi!");
    Serial.println("       Sistem tetap berjalan tanpa deteksi warna.");
    Serial.println("       Periksa koneksi I2C (SDA=21, SCL=22).");
  }

  Serial.println();
  Serial.println("Sistem siap! Memulai monitoring...");
  Serial.print("Suhu maks keamanan (Kipas OFF): ");
  Serial.print(suhuMaks);
  Serial.println(" °C");
  Serial.print("Batas Kelembapan Maks (Kipas ON) : ");
  Serial.print(humMaks);
  Serial.println(" %RH");
  Serial.print("Batas Kelembapan Min (Kipas OFF): ");
  Serial.print(humMin);
  Serial.println(" %RH");
  Serial.println("========================================");
  Serial.println();

  delay(1000);
}

// ============================================
// LOOP
// ============================================
void loop() {
  // ---- 0. BACA PERINTAH DARI DISPLAY BOARD via UART (Selalu dijalankan) ----
  readDisplayCommands();

  // ---- 1. CEK KONEKSI WIFI (Non-blocking reconnect jika terputus) ----
  checkWiFiConnection();

  // ---- TIMING MONITORING SENSOR (Setiap 5 Detik atau ketika ada perubahan lokal dari display) ----
  if (millis() - lastSensorReadTime >= sensorReadInterval || localSettingsDirty) {
    bool isDirtySync = localSettingsDirty;
    lastSensorReadTime = millis();

    // ---- 1. BACA SENSOR SUHU & KELEMBAPAN ----
    // Baca DS18B20
    ds18b20.requestTemperatures();
    float suhuDS18B20 = ds18b20.getTempCByIndex(0);

    // Baca SHT31
    float suhuSHT31 = sht31.readTemperature();
    float kelembapan = sht31.readHumidity();

    // Ambil suhu tertinggi dari kedua sensor untuk keamanan
    float suhuTertinggi = max(suhuDS18B20, suhuSHT31);

    // ---- 2. BACA SENSOR WARNA ----
    uint16_t r = 0, g = 0, b = 0, c = 0;
    float rasioR = 0, rasioG = 0, rasioB = 0;

    if (tcsReady) {
      tcs.getRawData(&r, &g, &b, &c);

      float total = (float)(r + g + b);
      if (total > 0) {
        rasioR = (float)r / total;
        rasioG = (float)g / total;
        rasioB = (float)b / total;
      }

      statusWarnaDaun = deteksiWarnaDaun(r, g, b, c);
    }

    // ---- 3. LOGIKA KONTROL RELAY ----
    if (mode == 0) { // Mode OTOMATIS
      // Tentukan status kelembapan berdasarkan batas maks/min (histeresis)
      if (kelembapan > humMaks) {
        exhaustHumActive = true;
      } else if (kelembapan < humMin) {
        exhaustHumActive = false;
      }

      // PRIORITAS 1: Cek suhu berlebih (perlindungan utama demi keamanan)
      if (suhuTertinggi > suhuMaks) {
        matikanSemuaRelay(); // Terlalu panas, matikan exhaust fan untuk pendinginan demi keamanan daun
        statusSistem = SISTEM_OVERTEMP;
      }
      // PRIORITAS 2: Kontrol berdasarkan kelembapan
      else if (exhaustHumActive) {
        nyalakanSemuaRelay(); // Kelembapan tinggi, nyalakan exhaust fan
        statusSistem = SISTEM_PROSES;
      }
      else {
        matikanSemuaRelay(); // Kelembapan sudah aman (mencapai humMin) dan suhu aman, matikan exhaust fan
        statusSistem = SISTEM_PROSES;
      }

      // Update statusSistem jika daun sudah kering untuk display (tanpa mematikan kipas)
      if (tcsReady && statusWarnaDaun == WARNA_COKLAT && statusSistem != SISTEM_OVERTEMP) {
        statusSistem = SISTEM_SELESAI;
      }
    } 
    else { // Mode MANUAL
      exhaustHumActive = false; // Reset status histeresis ketika berpindah ke manual
      // PRIORITAS 1: Cek suhu berlebih, jika > suhuMaks matikan exhaust fan demi keamanan
      if (suhuTertinggi > suhuMaks) {
        matikanSemuaRelay();
        statusSistem = SISTEM_OVERTEMP;
      } else {
        statusSistem = SISTEM_PROSES;
        // Sinkronkan relayState dengan output pin fisik
        for (int i = 0; i < 4; i++) {
          digitalWrite(relayPins[i], relayState[i] ? LOW : HIGH); // Active LOW
        }
      }
    }

    // ---- 4. OUTPUT SERIAL MONITOR (PC) ----
    Serial.println("===== DATA SENSOR =====");
    Serial.print("DS18B20 Suhu : ");
    Serial.print(suhuDS18B20);
    Serial.println(" °C");
    Serial.print("SHT31 Suhu   : ");
    Serial.print(suhuSHT31);
    Serial.println(" °C");
    Serial.print("Kelembapan   : ");
    Serial.print(kelembapan);
    Serial.println(" %RH");
    Serial.print("Suhu Tertinggi: ");
    Serial.print(suhuTertinggi);
    Serial.println(" °C");
    Serial.println();
    Serial.println("===== SENSOR WARNA =====");
    if (tcsReady) {
      Serial.print("R: ");
      Serial.print(r);
      Serial.print("  G: ");
      Serial.print(g);
      Serial.print("  B: ");
      Serial.print(b);
      Serial.print("  Clear: ");
      Serial.println(c);
      Serial.print("Rasio R: ");
      Serial.print(rasioR, 2);
      Serial.print("  G: ");
      Serial.print(rasioG, 2);
      Serial.print("  B: ");
      Serial.println(rasioB, 2);
      Serial.print("Status Warna : ");
      Serial.println(getNamaWarna(statusWarnaDaun));
    } else {
      Serial.println("TCS34725 tidak tersedia");
    }
    Serial.println();
    Serial.println("===== STATUS SISTEM =====");
    for (int i = 0; i < 4; i++) {
      Serial.print("Relay ");
      Serial.print(i + 1);
      Serial.print(" (");
      Serial.print(relayNama[i]);
      Serial.print(") : ");
      Serial.println(relayState[i] ? "ON" : "OFF");
    }
    Serial.println();
    Serial.print("Batas Suhu Keamanan (ON) : ");
    Serial.print(suhuMaks);
    Serial.println(" °C");
    Serial.print("Batas Kelembapan Maks (ON) : ");
    Serial.print(humMaks);
    Serial.println(" %RH");
    Serial.print("Batas Kelembapan Min (OFF): ");
    Serial.print(humMin);
    Serial.println(" %RH");
    Serial.println();
    Serial.print(">>> Status : ");
    Serial.println(getStatusSistem(statusSistem));
    Serial.print(">>> Mode   : ");
    Serial.println(mode == 0 ? "OTOMATIS" : "MANUAL");
    Serial.println("======================");
    Serial.println();

    // ---- 5. KIRIM DATA KE DISPLAY BOARD via UART ----
    sendDisplayData(suhuDS18B20, suhuSHT31, kelembapan, suhuTertinggi, (int)statusWarnaDaun, r, g, b);

    // ---- 6. KIRIM DATA KE WEB SERVER IOT & UPDATE SETTINGS ----
    int wifiRSSI = (WiFi.status() == WL_CONNECTED) ? WiFi.RSSI() : -999;
    sendDataToServer(suhuDS18B20, kelembapan, suhuSHT31, relayState[0], relayState[1], relayState[2], relayState[3], wifiRSSI, isDirtySync);
  }
}
