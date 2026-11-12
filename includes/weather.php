<?php
// Mengambil data cuaca dari API publik Open-Meteo (tanpa API key).
// Dokumentasi: https://open-meteo.com/en/docs

// Koordinat lokasi toko (ubah sesuai lokasi Kopipiro).
const SHOP_CITY      = "Surabaya";
const SHOP_LAT       = -7.2575;
const SHOP_LON       = 112.7521;

// Batas suhu (°C) untuk menampilkan saran minuman dingin.
const HOT_THRESHOLD  = 30;

// Waktu simpan cache di session (detik).
const WEATHER_CACHE_TTL = 600;

// Memanggil API dengan cURL, lengkap dengan penanganan error.
function ambilApi(string $url): array
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);

    $respon = curl_exec($ch);
    $kode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error  = curl_error($ch);
    curl_close($ch);

    // 1. Gagal terhubung (timeout, DNS, SSL, dll).
    if ($respon === false) {
        return ['error' => $error];
    }

    // 2. Server API membalas, tetapi bukan 200 OK.
    if ($kode !== 200) {
        return ['error' => "HTTP $kode"];
    }

    // 3. Isi balasan harus JSON yang valid.
    $data = json_decode($respon, true);

    if (!is_array($data)) {
        return ['error' => 'Format respons tidak valid'];
    }

    return $data;
}

// Mengambil cuaca saat ini. Hasil disimpan di session supaya
// API tidak dipanggil di setiap kali halaman dibuka
// (menghindari rate limit dan membuat halaman lebih cepat).
function getCuaca(): array
{
    $cache = $_SESSION['weather_cache'] ?? null;

    if ($cache && (time() - $cache['time']) < WEATHER_CACHE_TTL) {
        return $cache['data'];
    }

    $url = "https://api.open-meteo.com/v1/forecast"
         . "?latitude=" . SHOP_LAT
         . "&longitude=" . SHOP_LON
         . "&current=temperature_2m,wind_speed_10m";

    $data = ambilApi($url);

    // Pastikan data yang kita butuhkan benar-benar ada.
    if (!isset($data['error'])
        && !isset($data['current']['temperature_2m'])) {
        $data = ['error' => 'Data suhu tidak ditemukan'];
    }

    // Simpan cache hanya jika berhasil.
    if (!isset($data['error'])) {
        $_SESSION['weather_cache'] = [
            'time' => time(),
            'data' => $data,
        ];
    }

    return $data;
}