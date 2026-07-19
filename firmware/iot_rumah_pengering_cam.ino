#include "esp_camera.h"
#include "img_converters.h"
#include <WiFi.h>
#include <WiFiClient.h>
#include <HTTPClient.h>
#include <esp_now.h>
#include <esp_wifi.h>
#include <Preferences.h>
#include <ArduinoJson.h>

#define FLASH_GPIO_NUM 4

// ==============================
// Konfigurasi WiFi & Server Web
// ==============================
#define WIFI_SSID "beneng"
#define WIFI_PASS "12345678"
#define SERVER_URL "https://beneng.jeptira.id/api/upload_photo.php" // Ganti dengan IP server Anda

Preferences preferences;
String activeSSID = WIFI_SSID;
String activePASS = WIFI_PASS;

// ==============================
// Konfigurasi ESP-NOW
// ==============================
// Ganti dengan MAC Address ESP32 Penerima
uint8_t receiverMacAddress[] = {0x30, 0xED, 0xA0, 0xAA, 0xDD, 0x04};

// ==============================
// AI Thinker ESP32-CAM Pin Configuration
// ==============================

#define PWDN_GPIO_NUM     32
#define RESET_GPIO_NUM    -1
#define XCLK_GPIO_NUM      0
#define SIOD_GPIO_NUM     26
#define SIOC_GPIO_NUM     27

#define Y9_GPIO_NUM       35
#define Y8_GPIO_NUM       34
#define Y7_GPIO_NUM       39
#define Y6_GPIO_NUM       36
#define Y5_GPIO_NUM       21
#define Y4_GPIO_NUM       19
#define Y3_GPIO_NUM       18
#define Y2_GPIO_NUM        5
#define VSYNC_GPIO_NUM    25
#define HREF_GPIO_NUM     23
#define PCLK_GPIO_NUM     22

// ==============================
// === BARU: Struktur Paket Gambar ESP-NOW ===
// ==============================
// Tipe paket untuk identifikasi jenis data
#define IMG_PKT_HEADER  0x01  // Paket header (informasi gambar: ukuran, CRC)
#define IMG_PKT_DATA    0x02  // Paket data (chunk payload)
#define IMG_PKT_END     0x03  // Paket akhir (konfirmasi transfer selesai)

// Ukuran payload per chunk (ESP-NOW max 250 bytes, sisanya untuk header struct)
#define IMG_CHUNK_SIZE  200

// Struktur paket — harus identik di pengirim (CAM) dan penerima (Controller)
typedef struct __attribute__((packed)) {
    uint8_t  pktType;         // Tipe paket (HEADER/DATA/END)
    uint32_t totalSize;       // Ukuran total gambar dalam bytes
    uint16_t totalChunks;     // Jumlah total chunk yang akan dikirim
    uint16_t chunkIndex;      // Nomor urut chunk saat ini (0-based)
    uint16_t chunkSize;       // Ukuran data aktual di chunk ini
    uint32_t crc32;           // CRC32: di HEADER = CRC seluruh gambar, di DATA = CRC chunk
    uint8_t  data[IMG_CHUNK_SIZE]; // Payload data gambar
} ImagePacket;
// Total struct size: 1+4+2+2+2+4+200 = 215 bytes (< 250 ESP-NOW limit) ✓

// ==============================
// Variabel Global
// ==============================
esp_now_peer_info_t peerInfo;
bool isConnected = false;
int frameCounter = 0;
bool useSoftwareJpeg = false;

// === Status kamera dari server (ON/OFF) ===
bool cameraActive = true;  // Default aktif, bisa di-stop dari website

// === BARU: Status pengiriman untuk retry mechanism ===
volatile bool lastSendOk = false;   // Hasil callback terakhir
volatile bool sendDone = false;     // Flag callback sudah dipanggil

// ==============================
// === BARU: Fungsi CRC32 ===
// ==============================
// Menghitung CRC32 (IEEE 802.3) untuk verifikasi integritas data.
// Digunakan untuk memastikan gambar tidak corrupt saat transfer ESP-NOW.
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

// ==============================
// Callback saat data terkirim (DIPERBARUI)
// ==============================
// === BARU: Menambahkan tracking status untuk mekanisme retry ===
void onDataSent(const uint8_t *mac_addr, esp_now_send_status_t status) {
    lastSendOk = (status == ESP_NOW_SEND_SUCCESS);
    sendDone = true;

    if (status != ESP_NOW_SEND_SUCCESS) {
        Serial.println("Gagal mengirim data");
    }
}

// ==============================
// === BARU: Kirim Paket dengan Retry ===
// ==============================
// Mencoba mengirim paket ESP-NOW hingga maxRetries kali.
// Menunggu callback konfirmasi sebelum menentukan berhasil/gagal.
// Return: true jika berhasil, false jika gagal setelah semua retry.
bool sendPacketWithRetry(ImagePacket* pkt, int maxRetries) {
    for (int retry = 0; retry < maxRetries; retry++) {
        sendDone = false;
        lastSendOk = false;

        // Kirim paket via ESP-NOW
        esp_err_t result = esp_now_send(receiverMacAddress, (uint8_t*)pkt, sizeof(ImagePacket));
        if (result != ESP_OK) {
            Serial.printf("  ESP-NOW send error: %d, retry %d/%d\n", result, retry + 1, maxRetries);
            delay(10);
            continue;
        }

        // Tunggu callback konfirmasi (timeout 1000ms agar hardware retry selesai)
        unsigned long timeout = millis() + 1000;
        while (!sendDone && millis() < timeout) {
            delay(1);
        }

        // Cek hasil callback
        if (sendDone && lastSendOk) {
            return true; // Berhasil terkirim
        }

        // Gagal, coba lagi dengan delay progresif
        delay(5 * (retry + 1));
    }
    return false; // Gagal setelah semua retry
}

// ==============================
// Fungsi Kirim Gambar via ESP-NOW (DIPERBARUI)
// ==============================
// === BARU: Menggunakan protokol HEADER→DATA→END dengan CRC32 dan retry ===
void sendImageViaESPNow(camera_fb_t *fb) {
    if (!isConnected) {
        Serial.println("ESP-NOW belum siap");
        return;
    }

    uint32_t imageSize = fb->len;
    uint8_t *imageBuffer = fb->buf;
    uint8_t *jpg_buf = NULL;
    size_t jpg_buf_len = 0;
    bool is_jpg_allocated = false;

    if (useSoftwareJpeg) {
        // Lakukan software JPEG compression
        // Kualitas kompresi 20 untuk ukuran yang cukup kecil di ESP-NOW
        bool jpeg_converted = frame2jpg(fb, 20, &jpg_buf, &jpg_buf_len);
        if (!jpeg_converted) {
            Serial.println("Gagal kompresi JPEG software");
            return;
        }
        imageSize = jpg_buf_len;
        imageBuffer = jpg_buf;
        is_jpg_allocated = true;
        Serial.printf("Software JPEG selesai: %d bytes\n", imageSize);
    }

    // Hitung jumlah chunk yang diperlukan
    uint16_t totalChunks = (imageSize + IMG_CHUNK_SIZE - 1) / IMG_CHUNK_SIZE;

    // === BARU: Hitung CRC32 seluruh gambar sebelum kirim ===
    uint32_t imageCRC = calculateCRC32(imageBuffer, imageSize);

    Serial.printf("Mengirim gambar: %d bytes, %d chunks, CRC32: %08X\n",
                  imageSize, totalChunks, imageCRC);

    // === BARU: Kirim paket HEADER (info gambar) ===
    ImagePacket headerPkt;
    memset(&headerPkt, 0, sizeof(ImagePacket));
    headerPkt.pktType = IMG_PKT_HEADER;
    headerPkt.totalSize = imageSize;
    headerPkt.totalChunks = totalChunks;
    headerPkt.chunkIndex = 0;
    headerPkt.chunkSize = 0;
    headerPkt.crc32 = imageCRC; // CRC seluruh gambar

    if (!sendPacketWithRetry(&headerPkt, 3)) {
        Serial.println("Gagal kirim HEADER, abort transfer!");
        if (is_jpg_allocated && jpg_buf != NULL) {
            free(jpg_buf);
        }
        return;
    }
    delay(10); // Beri waktu penerima memproses header

    // Kirim setiap chunk data
    int failCount = 0;
    for (uint16_t i = 0; i < totalChunks; i++) {
        ImagePacket dataPkt;
        memset(&dataPkt, 0, sizeof(ImagePacket));
        dataPkt.pktType = IMG_PKT_DATA;
        dataPkt.totalSize = imageSize;
        dataPkt.totalChunks = totalChunks;
        dataPkt.chunkIndex = i;

        // Hitung ukuran chunk (chunk terakhir mungkin lebih kecil)
        uint32_t remainingBytes = imageSize - (i * IMG_CHUNK_SIZE);
        dataPkt.chunkSize = (remainingBytes < IMG_CHUNK_SIZE) ? remainingBytes : IMG_CHUNK_SIZE;

        // Salin data gambar ke paket
        memcpy(dataPkt.data, imageBuffer + (i * IMG_CHUNK_SIZE), dataPkt.chunkSize);

        // === BARU: CRC32 per chunk untuk deteksi error pada level chunk ===
        dataPkt.crc32 = calculateCRC32(dataPkt.data, dataPkt.chunkSize);

        // Kirim dengan retry
        if (!sendPacketWithRetry(&dataPkt, 3)) {
            Serial.printf("Gagal kirim chunk %d setelah retry!\n", i);
            failCount++;
            if (failCount > 5) {
                Serial.println("Terlalu banyak kegagalan, abort transfer!");
                if (is_jpg_allocated && jpg_buf != NULL) {
                    free(jpg_buf);
                }
                return;
            }
            continue; // Skip chunk yang gagal, penerima akan deteksi CRC error
        }

        // === BARU: Delay 10ms (naik dari 5ms) untuk stabilitas transfer ===
        delay(10);

        // Tampilkan progress setiap 20 chunk
        if (i % 20 == 0) {
            Serial.printf("Progress: %d/%d (%.0f%%)\n", i, totalChunks,
                          (float)i / totalChunks * 100.0);
        }

        // Feed watchdog agar tidak reset
        yield();
    }

    // === BARU: Kirim paket END (konfirmasi selesai) ===
    ImagePacket endPkt;
    memset(&endPkt, 0, sizeof(ImagePacket));
    endPkt.pktType = IMG_PKT_END;
    endPkt.totalSize = imageSize;
    endPkt.totalChunks = totalChunks;
    endPkt.chunkIndex = totalChunks; // Penanda: index = totalChunks berarti selesai
    endPkt.chunkSize = 0;
    endPkt.crc32 = imageCRC; // CRC seluruh gambar untuk verifikasi akhir

    if (!sendPacketWithRetry(&endPkt, 3)) {
        Serial.println("Warning: END packet gagal terkirim");
    }

    Serial.printf("Gambar terkirim! Frame #%d, %d chunks, %d gagal\n",
                  frameCounter + 1, totalChunks, failCount);
    frameCounter++;

    // Kirim gambar via HTTP POST (Website)
    uploadImageToWeb(imageBuffer, imageSize);

    if (is_jpg_allocated && jpg_buf != NULL) {
        free(jpg_buf);
    }
}

// ==============================
// === BARU: Kirim Gambar ke Web Server via HTTP POST ===
// ==============================
bool uploadImageToWeb(const uint8_t *imageBuffer, size_t imageSize) {
    if (WiFi.status() != WL_CONNECTED) {
        Serial.println("[HTTP] WiFi tidak terhubung, skip upload web.");
        return false;
    }

    Serial.printf("[HTTP] Mengirim foto ke %s...\n", SERVER_URL);

    HTTPClient http;
    http.begin(SERVER_URL);
    http.setTimeout(10000); // Timeout 10 detik

    // Boundary untuk multipart/form-data
    String boundary = "esp32cam-boundary-123456789";
    http.addHeader("Content-Type", "multipart/form-data; boundary=" + boundary);

    // Construct multipart body
    String head = "--" + boundary + "\r\n" +
                  "Content-Disposition: form-data; name=\"image\"; filename=\"photo.jpg\"\r\n" +
                  "Content-Type: image/jpeg\r\n\r\n";
    String tail = "\r\n--" + boundary + "--\r\n";

    // Buat buffer untuk seluruh body
    uint32_t totalLen = head.length() + imageSize + tail.length();
    uint8_t *body = (uint8_t *)malloc(totalLen);
    if (!body) {
        Serial.println("[HTTP] Gagal alokasi memori untuk body");
        http.end();
        return false;
    }

    // Susun body: head + image + tail
    memcpy(body, head.c_str(), head.length());
    memcpy(body + head.length(), imageBuffer, imageSize);
    memcpy(body + head.length() + imageSize, tail.c_str(), tail.length());

    // Kirim POST request
    int httpResponseCode = http.POST(body, totalLen);
    free(body);

    if (httpResponseCode <= 0) {
        Serial.printf("[HTTP] Gagal mengirim, error: %s\n", http.errorToString(httpResponseCode).c_str());
        http.end();
        return false;
    }

    Serial.printf("[HTTP] Respon server: %d\n", httpResponseCode);
    String response = http.getString();
    http.end();

    // Parse JSON response
    int jsonStart = response.indexOf('{');
    if (jsonStart != -1) {
        String jsonBody = response.substring(jsonStart);
        StaticJsonDocument<384> doc;
        DeserializationError error = deserializeJson(doc, jsonBody);
        
        if (!error) {
            // Cek camera_status dari server (ON/OFF)
            if (doc.containsKey("camera_status")) {
                String camStatus = doc["camera_status"].as<String>();
                if (camStatus == "OFF") {
                    cameraActive = false;
                    Serial.println("[CAM] Server memerintahkan STOP kamera.");
                } else {
                    cameraActive = true;
                    Serial.println("[CAM] Kamera AKTIF.");
                }
            }

            if (doc.containsKey("wifi_ssid") && doc.containsKey("wifi_password")) {
                String new_ssid = doc["wifi_ssid"].as<String>();
                String new_pass = doc["wifi_password"].as<String>();
                if (new_ssid.length() > 0 && (new_ssid != activeSSID || new_pass != activePASS)) {
                    Serial.println("[WiFi] Terdeteksi perubahan konfigurasi WiFi dari server!");
                    Serial.printf("[WiFi] SSID baru: '%s', Pass baru: '%s'\n", new_ssid.c_str(), new_pass.c_str());
                    
                    // Simpan ke Preferences NVS
                    preferences.begin("wifi-config", false); // read-write
                    preferences.putString("ssid", new_ssid);
                    preferences.putString("pass", new_pass);
                    preferences.end();
                    
                    Serial.println("[WiFi] Restarting ESP32-CAM to apply new WiFi settings...");
                    delay(1500);
                    ESP.restart();
                }
            }
        } else {
            Serial.printf("[HTTP] Gagal parsing JSON respon: %s\n", error.c_str());
        }
    }
    return true;
}

// ==============================
// Setup ESP-NOW
// ==============================
void setupESPNow() {
    // Inisialisasi ESP-NOW
    if (esp_now_init() != ESP_OK) {
        Serial.println("Gagal inisialisasi ESP-NOW");
        return;
    }

    // Set callback
    esp_now_register_send_cb(onDataSent);

    // Register peer
    memcpy(peerInfo.peer_addr, receiverMacAddress, 6);
    peerInfo.channel = 0;
    peerInfo.encrypt = false;

    if (esp_now_add_peer(&peerInfo) != ESP_OK) {
        Serial.println("Gagal menambahkan peer");
        return;
    }

    isConnected = true;
    Serial.println("ESP-NOW siap!");
    Serial.print("MAC Address pengirim: ");
    Serial.println(WiFi.macAddress());
}

// ==============================
// Setup Kamera
// ==============================
void setupCamera() {
    camera_config_t config;
    config.ledc_channel = LEDC_CHANNEL_0;
    config.ledc_timer = LEDC_TIMER_0;
    config.pin_d0 = Y2_GPIO_NUM;
    config.pin_d1 = Y3_GPIO_NUM;
    config.pin_d2 = Y4_GPIO_NUM;
    config.pin_d3 = Y5_GPIO_NUM;
    config.pin_d4 = Y6_GPIO_NUM;
    config.pin_d5 = Y7_GPIO_NUM;
    config.pin_d6 = Y8_GPIO_NUM;
    config.pin_d7 = Y9_GPIO_NUM;
    config.pin_xclk = XCLK_GPIO_NUM;
    config.pin_pclk = PCLK_GPIO_NUM;
    config.pin_vsync = VSYNC_GPIO_NUM;
    config.pin_href = HREF_GPIO_NUM;
    config.pin_sscb_sda = SIOD_GPIO_NUM;
    config.pin_sscb_scl = SIOC_GPIO_NUM;
    config.pin_pwdn = PWDN_GPIO_NUM;
    config.pin_reset = RESET_GPIO_NUM;
    config.xclk_freq_hz = 20000000;
    config.pixel_format = PIXFORMAT_JPEG;

    // Gunakan resolusi rendah untuk ESP-NOW (ukuran lebih kecil)
    if (psramFound()) {
        config.frame_size = FRAMESIZE_QVGA;  // 320x240 (lebih kecil untuk ESP-NOW)
        config.jpeg_quality = 15;              // Kualitas lebih rendah = ukuran lebih kecil
        config.fb_count = 1;
        Serial.println("PSRAM ditemukan, menggunakan QVGA");
    } else {
        config.frame_size = FRAMESIZE_QVGA;
        config.jpeg_quality = 15;
        config.fb_count = 1;
        Serial.println("PSRAM tidak ditemukan, menggunakan QVGA");
    }

    esp_err_t err = esp_camera_init(&config);
    if (err != ESP_OK) {
        Serial.printf("Gagal init kamera dengan JPEG (0x%x). Mencoba fallback ke RGB565...\n", err);
        config.pixel_format = PIXFORMAT_RGB565;
        err = esp_camera_init(&config);
        if (err != ESP_OK) {
            Serial.printf("Gagal init kamera dengan RGB565: 0x%x\n", err);
            return;
        }
        useSoftwareJpeg = true;
        Serial.println("Kamera siap (Menggunakan software JPEG compression)!");
    } else {
        useSoftwareJpeg = false;
        Serial.println("Kamera siap (Menggunakan hardware JPEG compression)!");
    }

    // Configure sensor settings for low light & brightness optimization
    sensor_t * s = esp_camera_sensor_get();
    if (s) {
        s->set_brightness(s, 1);                  // Increase brightness (+1 range -2 to 2)
        s->set_contrast(s, 0);                    // Contrast (0)
        s->set_saturation(s, 0);                  // Saturation (0)
        s->set_gain_ctrl(s, 1);                   // Auto gain control (AGC) enabled
        s->set_exposure_ctrl(s, 1);               // Auto exposure control (AEC) enabled
        s->set_whitebal(s, 1);                    // Auto white balance (AWB) enabled
        s->set_gainceiling(s, GAINCEILING_8X);    // Max gain ceiling for dim lighting
    }

    // Sensor Warm-up: capture & discard 10 initial frames
    Serial.println("Melakukan warm-up sensor...");
    for (int i = 0; i < 10; i++) {
        camera_fb_t *fb = esp_camera_fb_get();
        if (fb) {
            esp_camera_fb_return(fb);
            delay(80);
        }
    }
    Serial.println("Sensor warm-up selesai!");
}

// ==============================
// === BARU: Fungsi mendapatkan Channel WiFi Router ===
// ==============================
int32_t getWiFiChannel(const char *ssid) {
    if (int32_t n = WiFi.scanNetworks()) {
        for (int i = 0; i < n; i++) {
            if (WiFi.SSID(i) == ssid) {
                return WiFi.channel(i);
            }
        }
    }
    return -1;
}

// ==============================
// Setup
// ==============================
void setup() {
    Serial.begin(115200);
    Serial.println("\nESP32-CAM ESP-NOW Sender");

    // Setup WiFi
    preferences.begin("wifi-config", true);
    activeSSID = preferences.getString("ssid", WIFI_SSID);
    activePASS = preferences.getString("pass", WIFI_PASS);
    preferences.end();
    Serial.printf("Memuat WiFi Config: SSID='%s'\n", activeSSID.c_str());

    WiFi.mode(WIFI_STA);
    WiFi.setAutoReconnect(true);

    Serial.printf("Menghubungkan ke WiFi: %s...\n", activeSSID.c_str());
    WiFi.begin(activeSSID.c_str(), activePASS.c_str());

    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 20) {
        delay(500);
        Serial.print(".");
        attempts++;
    }

    if (WiFi.status() == WL_CONNECTED) {
        Serial.println("\n[WiFi] Terhubung!");
        Serial.print("[WiFi] IP Address: ");
        Serial.println(WiFi.localIP());
    } else {
        Serial.println("\n[WiFi] Gagal terhubung (Timeout). Menyetel channel secara manual untuk ESP-NOW.");
        // Cari channel WiFi dari SSID router
        int32_t channel = getWiFiChannel(activeSSID.c_str());
        if (channel > 0) {
            Serial.printf("WiFi '%s' ditemukan di Channel %d. Mengatur ESP-NOW ke channel ini.\n", WIFI_SSID, channel);
            esp_wifi_set_promiscuous(true);
            esp_wifi_set_channel(channel, WIFI_SECOND_CHAN_NONE);
            esp_wifi_set_promiscuous(false);
        } else {
            Serial.println("WiFi tidak ditemukan. Menggunakan default Channel 1.");
            esp_wifi_set_promiscuous(true);
            esp_wifi_set_channel(1, WIFI_SECOND_CHAN_NONE);
            esp_wifi_set_promiscuous(false);
        }
    }

    Serial.print("MAC Address ESP32-CAM: ");
    Serial.println(WiFi.macAddress());
    Serial.println("MASUKKAN MAC ADDRESS INI KE KODE PENERIMA!");
    Serial.println();

    // Setup flash LED - nyala terus
    pinMode(FLASH_GPIO_NUM, OUTPUT);
    digitalWrite(FLASH_GPIO_NUM, HIGH);

    // Setup Kamera
    setupCamera();

    // Setup ESP-NOW
    setupESPNow();

    // Ambil gambar pertama untuk test
    delay(1000);
}

// ==============================
// === Fungsi cek status kamera dari server ===
// ==============================
void checkCameraStatusFromServer() {
    if (WiFi.status() != WL_CONNECTED) return;

    HTTPClient http;
    String checkUrl = String(SERVER_URL);
    // Ganti upload_photo.php menjadi camera_control.php
    checkUrl.replace("upload_photo.php", "camera_control.php");
    http.begin(checkUrl);
    http.setTimeout(5000);

    int httpCode = http.GET();
    if (httpCode == 200) {
        String payload = http.getString();
        StaticJsonDocument<256> doc;
        DeserializationError error = deserializeJson(doc, payload);
        if (!error && doc.containsKey("camera_status")) {
            String camStatus = doc["camera_status"].as<String>();
            bool newState = (camStatus == "ON");
            if (newState != cameraActive) {
                cameraActive = newState;
                Serial.printf("[CAM] Status diperbarui dari server: %s\n", cameraActive ? "AKTIF" : "STOP");
            }
        }
    }
    http.end();
}

// ==============================
// Loop - Kirim Gambar Setiap 5 Detik
// ==============================
void loop() {
    if (!isConnected) {
        Serial.println("Tunggu koneksi ESP-NOW...");
        delay(1000);
        return;
    }

    // Jika kamera di-stop dari website, matikan flash & cek status dari server secara berkala
    if (!cameraActive) {
        digitalWrite(FLASH_GPIO_NUM, LOW); // Matikan flash saat tidak foto
        Serial.println("[CAM] Kamera BERHENTI (di-stop dari website). Menunggu perintah aktif...");
        checkCameraStatusFromServer();
        delay(5000); // Cek ulang setiap 5 detik
        return;
    }

    // Pastikan flash LED menyala saat kamera aktif
    digitalWrite(FLASH_GPIO_NUM, HIGH);

    // Capture gambar
    camera_fb_t *fb = esp_camera_fb_get();

    if (!fb) {
        Serial.println("Gagal capture kamera");
        delay(1000);
        return;
    }

    Serial.printf("Mengirim frame #%d...\n", frameCounter + 1);

    // Kirim gambar via ESP-NOW
    sendImageViaESPNow(fb);

    // Kembalikan frame buffer
    esp_camera_fb_return(fb);

    // Tunggu sebelum kirim gambar berikutnya
    delay(5000);  // Kirim setiap 5 detik
}
