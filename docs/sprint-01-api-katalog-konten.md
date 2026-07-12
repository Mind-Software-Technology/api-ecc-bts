# Sprint 1 — API Katalog & Konten (Fase 1)

**Sumber:** `docs/Backend-DB-Midtrans-Integration-Analysis-Laravel.docx`, Bagian 5.A & Bagian 8 (Roadmap)
**Status database:** Selesai — 16 migration + model sudah jalan di MySQL (`backend-ecc-bts`)
**Dibuat:** 2026-07-12

## Kenapa API ini duluan

- Read-only, tanpa risiko uang/keamanan — beda dengan Fase 2 (cart/checkout) dan Fase 3 (payment Midtrans) yang mengubah state dan pegang uang.
- Frontend saat ini 100% data statis di `src/data/*.js`. API ini penggantinya 1:1 — tinggal ganti import jadi fetch, **tanpa perubahan visual**.
- Fase 2 & 3 bergantung pada `services`/`categories` sudah bisa diakses lewat API — jadi ini blocker buat semua fase berikutnya.

## Prasyarat teknis (belum ada di project)

- [ ] `routes/api.php` **belum didaftarkan** — `bootstrap/app.php` `withRouting()` belum punya key `api:`. Tanpa ini tidak ada endpoint yang bisa jalan.
- [ ] Belum ada Controller/API Resource apapun di `app/Http/`.

## Scope sprint ini

### Prioritas 1 — blocking (dipakai di navbar/footer/hampir semua halaman)

| # | Endpoint | Controller | Catatan |
|---|----------|-----------|---------|
| 1 | `GET /api/site-config` | `SiteConfigController@show` | Singleton — brand/kontak/sosial/nav |
| 2 | `GET /api/categories` | `CategoryController@index` | |
| 3 | `GET /api/categories/:slug` | `CategoryController@show` | Kategori + layanan-layanannya |
| 4 | `GET /api/services` | `ServiceController@index` | Query: `category`, `q`, `sort`, `page`, `limit` |
| 5 | `GET /api/services/:slug` | `ServiceController@show` | |

### Prioritas 2 — konten homepage/marketing

| # | Endpoint | Controller |
|---|----------|-----------|
| 6 | `GET /api/testimonials` | `TestimonialController@index` |
| 7 | `GET /api/faqs` | `FaqController@index` |
| 8 | `GET /api/stats` | `StatController@index` |
| 9 | `GET /api/advantages` | `AdvantageController@index` |
| 10 | `GET /api/process-steps` | `ProcessStepController@index` |

### Eksplisit di luar scope sprint ini

Cart (`/api/cart`), Order/Checkout (`/api/orders`), Payment Midtrans (`/api/payments/*`), Auth/Sanctum (`/api/auth/*`) — masuk Fase 2/3/4 sesuai roadmap dokumen. Tabelnya sudah ada di database, tapi belum ada API-nya.

## Backlog

| Task | Deliverable |
|------|-------------|
| T0 | Daftarkan `routes/api.php` di `bootstrap/app.php`, buat file kosong berisi group `prefix('api')` |
| T1 | `CategoryController` (index, show) + `CategoryResource` |
| T2 | `ServiceController` (index dengan filter `category/q/sort/page/limit`, show) + `ServiceResource` |
| T3 | `TestimonialController@index` + `TestimonialResource` |
| T4 | `FaqController@index` + `FaqResource` |
| T5 | `StatController@index` + `StatResource` |
| T6 | `AdvantageController@index` + `AdvantageResource` |
| T7 | `ProcessStepController@index` + `ProcessStepResource` |
| T8 | `SiteConfigController@show` (singleton — ambil baris pertama) + `SiteConfigResource` |
| T9 | Seeder untuk 8 tabel di atas (isi awal katalog/konten) |
| T10 | Format error konsisten `{ error: { code, message } }` lewat exception handler |
| T11 | Format list konsisten `{ data, meta: { page, limit, total } }` untuk endpoint yang paginasi (`services`) |

## Definition of Done

- Semua 10 endpoint di atas merespons 200 dengan data dari MySQL (bukan hardcode).
- Response shape sesuai Bagian 5 dokumen analisis (`data`/`meta` untuk list, `withoutWrapping()` untuk single resource).
- Tidak ada perubahan skema database tambahan di luar yang sudah dimigrasikan.
- Bisa diverifikasi manual (curl/Postman) tanpa perlu frontend jalan.

## Referensi

- `docs/Backend-DB-Midtrans-Integration-Analysis-Laravel.docx` — Bagian 5.A (kontrak endpoint), Bagian 8 (roadmap Fase 1–4)
