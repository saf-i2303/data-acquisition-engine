# Data Acquisition Engine

Technical Challenge untuk Program PKL Berani Digital ID. Aplikasi ini adalah engine agregasi data yang terdiri dari tiga connector independen (Website Metadata Extractor, Domain Intelligence via RDAP, dan Company Location Finder via Nominatim/OpenStreetMap), yang digabungkan menjadi satu endpoint integrasi.

## Live Demo

Base URL: `https://data-acquisition-engine-production.up.railway.app/api`

Contoh endpoint POST:
`POST https://data-acquisition-engine-production.up.railway.app/api/extract/website`

Contoh endpoint GET dengan query parameter:
`GET https://data-acquisition-engine-production.up.railway.app/api/company-information?domain=paper.id&company_name=Paper.id`

Catatan: response time RDAP (rdap.org) bisa bervariasi tergantung kondisi jaringan saat itu, mulai dari kurang dari 2 detik sampai lebih dari 10 detik. Timeout untuk connector ini sudah diatur ke 20 detik untuk mengakomodasi variasi tersebut, dan kegagalan tetap tertangani dengan baik lewat error handling yang konsisten.

## Tech Stack

- Framework: Laravel 13 (PHP 8.3)
- HTTP Client: Laravel HTTP Client (Guzzle)
- Parsing HTML: Native PHP DOMDocument/DOMXPath, tanpa dependency eksternal
- Cache: File-based cache
- Database: Tidak digunakan (lihat bagian Asumsi dan Kendala)

## Instalasi

Prasyarat: PHP >= 8.2 dan Composer sudah terpasang.

```bash
git clone https://github.com/saf-i2303/data-acquisition-engine.git
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

Field `phones` sengaja dikembangkan dari sekadar array string jadi objek dengan dua kategori. `confirmed` berisi nomor dengan format yang meyakinkan sebagai nomor Indonesia, sedangkan `possible` berisi kandidat lain yang lolos filter dasar tapi belum tentu benar-benar nomor telepon. Pendekatan ini dipilih karena regex pada teks bebas tidak pernah bisa 100% akurat, jadi lebih baik transparan soal tingkat keyakinannya daripada berpura-pura semua hasil pasti benar.

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

Field `handle` dan `abuse_contact` ditambahkan di luar spesifikasi minimum karena relevan untuk konteks domain intelligence. Field `registrar` di sini menunjukkan penyedia jasa registrasi domain, bukan pemilik domainnya — data pemilik domain umumnya memang disembunyikan RDAP untuk alasan privasi.

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

Field `match_quality` ditambahkan untuk kasih sinyal seberapa bisa diandalkan hasil pencariannya, dihitung dari skor `importance` yang dikembalikan Nominatim. Nilainya `reliable` atau `uncertain`. Ini penting karena Nominatim itu layanan geocoding berbasis data crowd-sourced yang cakupannya nggak merata, terutama untuk entitas non-fisik kayak kantor perusahaan digital.

### 4. Final Integration
GET /api/company-information?domain=paper.id&company_name=Paper.id

Parameter `domain` wajib diisi, sedangkan `company_name` opsional tapi disarankan supaya hasil pencarian lokasinya lebih akurat.

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

Ketiga connector dipanggil secara independen satu sama lain. Kalau salah satunya gagal, connector lain tetap dikembalikan hasilnya seperti biasa, dengan error yang gagal ditandai jelas di field `*_error`. Pendekatan ini dipilih supaya kegagalan di satu sumber data nggak ikut menjatuhkan seluruh request, mengingat ketiga connector ini memang nggak saling bergantung.

## Asumsi dan Kendala

Ekstraksi email dan nomor telepon sifatnya best-effort. Nggak ada struktur HTML baku untuk kedua jenis data ini, jadi dipakai kombinasi pencarian lewat XPath dan pola regex di teks bebas. Regex nomor telepon dibuat agak longgar supaya nggak kehilangan nomor yang valid, konsekuensinya kadang ikut menangkap angka lain yang polanya mirip nomor telepon padahal bukan.

Akurasi pencarian lokasi bergantung sama kelengkapan data OpenStreetMap. Untuk entitas seperti perusahaan digital yang biasanya nggak terdaftar sebagai lokasi fisik, pencarian cuma berdasarkan nama domain sering nggak nemu lokasi yang relevan. Parameter opsional `company_name` disediakan untuk membantu meningkatkan relevansi pencarian, tapi tetap nggak menjamin akurasi penuh karena ini keterbatasan yang melekat di sumber data pihak ketiga.

Data pemilik domain nggak ditampilkan di hasil Domain Intelligence. RDAP modern memang menyembunyikan informasi ini secara default demi kepatuhan privasi. Field `registrar` yang ditampilkan itu penyedia jasa registrasi domainnya, bukan pemilik domain yang sebenarnya.

Aplikasi ini nggak pakai database sama sekali. Semua data diambil langsung secara real-time dari sumber eksternal setiap kali ada request. Keputusan ini diambil karena kalau hasil scraping/lookup disimpan ke database, datanya berisiko jadi usang dibanding sumber aslinya — kurang cocok dengan sifat aplikasi ini sebagai acquisition engine, bukan storage engine.

Pada deployment production, request ke RDAP kadang butuh waktu lebih lama dari biasanya akibat latensi jaringan antar server. Ini murni karakteristik infrastruktur RDAP yang di luar kendali aplikasi, dan sudah diantisipasi dengan menaikkan timeout khusus untuk connector ini serta error handling yang tetap konsisten kalau memang gagal.

## Nilai Tambah yang Diimplementasikan

Untuk mengurangi request berulang ke layanan eksternal, seluruh connector menggunakan file-based cache dengan TTL 3600 detik.

Setiap proses pemanggilan API eksternal juga dicatat lewat logging, baik yang berhasil maupun gagal. Log ini bisa dicek di `storage/logs/laravel.log` kalau butuh debug lebih lanjut.

Dari sisi arsitektur, tiap connector punya service layer sendiri dengan dependency injection berbasis interface. Ini memisahkan logic bisnis dari HTTP layer, sekaligus memudahkan proses testing karena service-nya bisa di-mock.

Testing dilakukan lewat feature test yang mencakup kasus sukses, validasi input, error dari layanan eksternal, hingga skenario kegagalan parsial di endpoint Final Integration. Total ada 15 test dengan 36 assertion — bisa dijalankan dengan `php artisan test`.

## Author

Safina annaja — dikerjakan untuk Technical Challenge PKL Berani Digital ID