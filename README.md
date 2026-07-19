# Data Acquisition Engine

Technical Challenge untuk Program PKL Berani Digital ID. Aplikasi ini adalah engine agregasi data yang terdiri dari tiga connector independen (Website Metadata Extractor, Domain Intelligence via RDAP, dan Company Location Finder via Nominatim/OpenStreetMap), yang digabungkan menjadi satu endpoint integrasi.

## Tech Stack

- Framework: Laravel 13 (PHP 8.3)
- HTTP Client: Laravel HTTP Client (Guzzle)
- Parsing HTML: Native PHP DOMDocument/DOMXPath, tanpa dependency eksternal
- Cache: File-based cache
- Database: Tidak digunakan (lihat bagian Asumsi dan Kendala)

## Instalasi

Prasyarat: PHP >= 8.2 dan Composer sudah terpasang.

```bash
git clone https://github.com/USERNAME/data-acquisition-engine.git
cd data-acquisition-engine
composer install
cp .env.example .env
php artisan key:generate
```

## Konfigurasi

Buka file `.env`, pastikan baris berikut sudah sesuai:

```env
CACHE_STORE=file
```

Aplikasi ini tidak memerlukan konfigurasi database apapun, karena seluruh data diambil secara langsung dari sumber eksternal tanpa disimpan secara permanen.

## Menjalankan Aplikasi

```bash
php artisan serve
```

Aplikasi akan berjalan di `http://127.0.0.1:8000`. Seluruh endpoint API dapat diakses melalui prefix `/api`, sehingga endpoint yang didaftarkan pada `routes/api.php` diakses dengan awalan tersebut, misalnya `POST http://127.0.0.1:8000/api/extract/website`.

## Dokumentasi Endpoint

Seluruh endpoint mengembalikan response dalam format JSON dengan struktur yang konsisten.

Format response sukses:
```json
{
  "success": true,
  "data": { }
}
```

Format response gagal:
```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Pesan error yang jelas"
  }
}
```

### 1. Website Metadata Extractor
POST /api/extract/website

Body:
```json
{
  "url": "https://paper.id"
}
```

Response:
```json
{
  "success": true,
  "data": {
    "url": "https://paper.id",
    "title": "",
    "description": "",
    "canonical": "",
    "favicon": "",
    "emails": ["support@paper.id"],
    "phones": {
      "confirmed": ["6285219526186"],
      "possible": [
        { "raw": "67732591", "normalized": "67732591", "confidence": "low" }
      ]
    },
    "social_media": ["https://www.instagram.com/paperindonesia/"],
    "open_graph": {
      "title": "",
      "description": "",
      "image": ""
    }
  }
}
```

Field `phones` dikembangkan dari array string sederhana menjadi objek dengan dua kategori. `confirmed` berisi nomor dengan format yang meyakinkan sebagai nomor Indonesia, sedangkan `possible` berisi kandidat lain yang lolos filter dasar namun tidak memenuhi pola tersebut. Pendekatan ini memberi transparansi tingkat keyakinan hasil ekstraksi, mengingat regex pada teks bebas tidak dapat menjamin akurasi penuh.

### 2. Domain Intelligence (RDAP)
POST /api/extract/domain

Body:
```json
{
  "domain": "paper.id"
}
```

Response:
```json
{
  "success": true,
  "data": {
    "domain": "paper.id",
    "handle": "302361_DOMAIN_ID-ID",
    "registrar": "PT Jagat Informasi Solusi",
    "abuse_contact": null,
    "registered_at": "2014-08-15T11:00:45Z",
    "expired_at": "2030-08-15T23:59:59Z",
    "last_updated": "2025-09-29T01:03:31Z",
    "status": ["active"],
    "nameservers": ["jeremy.ns.cloudflare.com", "magali.ns.cloudflare.com"]
  }
}
```

Field `handle` dan `abuse_contact` ditambahkan di luar spesifikasi minimum sebagai informasi tambahan yang relevan untuk konteks domain intelligence. Field `registrar` menunjukkan penyedia jasa registrasi domain, bukan pemilik domain, karena data pemilik domain umumnya disembunyikan oleh RDAP untuk alasan privasi.

### 3. Company Location Finder (Nominatim)
POST /api/extract/location

Body:
```json
{
  "query": "PT Telkom Indonesia"
}
```

Response:
```json
{
  "success": true,
  "data": {
    "display_name": "",
    "latitude": "",
    "longitude": "",
    "importance": 0,
    "osm_type": "",
    "address": { },
    "match_quality": "reliable"
  }
}
```

Field `match_quality` ditambahkan untuk memberi sinyal keandalan hasil pencarian, berdasarkan skor `importance` yang dikembalikan Nominatim. Nilainya `reliable` atau `uncertain`, mengingat Nominatim adalah layanan geocoding berbasis data crowd-sourced dengan cakupan yang tidak merata, khususnya untuk entitas non-fisik seperti kantor perusahaan digital.

### 4. Final Integration
GET /api/company-information?domain=paper.id&company_name=Paper.id

Parameter `domain` wajib diisi, sedangkan `company_name` bersifat opsional namun disarankan untuk hasil pencarian lokasi yang lebih akurat.

Response:
```json
{
  "success": true,
  "data": {
    "query": { "domain": "paper.id", "company_name": "Paper.id" },
    "website": { },
    "website_error": null,
    "domain": { },
    "domain_error": null,
    "location": { },
    "location_error": null
  }
}
```

Ketiga connector dipanggil secara independen. Apabila salah satu connector gagal, connector lain tetap dikembalikan hasilnya, dengan error yang gagal ditandai secara eksplisit pada field `*_error`. Pendekatan ini mencegah kegagalan satu sumber data memengaruhi keseluruhan request, karena ketiga connector tidak saling bergantung satu sama lain.

## Asumsi dan Kendala

Ekstraksi email dan nomor telepon bersifat best-effort. Tidak terdapat struktur HTML baku untuk kedua jenis data tersebut, sehingga digunakan kombinasi pencarian melalui XPath dan pencarian pola menggunakan regex pada teks bebas. Regex nomor telepon dibuat relatif longgar agar tidak kehilangan nomor yang valid, dengan konsekuensi kemungkinan menangkap angka lain yang secara pola menyerupai nomor telepon namun bukan nomor telepon sebenarnya.

Akurasi pencarian lokasi bergantung pada kelengkapan data OpenStreetMap. Untuk entitas seperti perusahaan digital yang umumnya tidak terdaftar sebagai lokasi fisik, pencarian berbasis nama domain saja sering kali tidak menemukan lokasi yang relevan. Parameter opsional `company_name` disediakan untuk meningkatkan relevansi pencarian, namun tidak menjamin akurasi penuh karena keterbatasan yang melekat pada sumber data pihak ketiga.

Data pemilik domain tidak ditampilkan pada hasil Domain Intelligence. RDAP modern menyembunyikan informasi tersebut secara default untuk kepatuhan terhadap kebijakan privasi. Field `registrar` yang ditampilkan merepresentasikan penyedia jasa registrasi domain, bukan pemilik domain yang sebenarnya.

Aplikasi ini tidak menggunakan database. Seluruh data diambil secara langsung dan real-time dari sumber eksternal pada setiap request. Keputusan ini diambil karena menyimpan hasil scraping atau lookup ke database berisiko membuat data menjadi usang dibandingkan sumber aslinya, sehingga kurang sesuai dengan sifat aplikasi ini sebagai acquisition engine.

## Nilai Tambah yang Diimplementasikan

Caching diterapkan secara konsisten pada seluruh connector menggunakan file-based cache dengan TTL 3600 detik, untuk mengurangi jumlah request berulang ke layanan eksternal.

Logging diterapkan pada seluruh service untuk mencatat proses pemanggilan layanan eksternal beserta keberhasilan dan kegagalannya, dapat diperiksa pada berkas `storage/logs/laravel.log`.

Service layer dengan dependency injection berbasis interface diterapkan pada seluruh connector, memisahkan logic bisnis dari HTTP layer dan mempermudah pengujian melalui mocking.

Feature test diterapkan pada seluruh endpoint, mencakup kasus sukses, validasi input, penanganan error dari layanan eksternal, serta skenario kegagalan parsial pada endpoint Final Integration. Total terdapat 15 test dengan 36 assertion, dapat dijalankan melalui `php artisan test`.

## Author

Safina annaja — dikerjakan untuk Technical Challenge PKL Berani Digital ID