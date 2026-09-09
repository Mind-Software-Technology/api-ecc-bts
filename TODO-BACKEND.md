# Catatan Perbaikan Backend (belum diterapkan)

Daftar bug & hal yang perlu diperbaiki/ditambahkan di backend, ditemukan lewat pengujian
end-to-end dari sisi frontend. **Belum ada satupun dari perubahan ini yang diterapkan di kode** —
semua sempat dicoba lalu di-revert supaya kerjaan backend tetap milik yang mengerjakan backend.
Urutan dari yang paling kritis.

## 1. Migration duplikat bikin seluruh test attachment gagal

**File:** `database/migrations/2026_07_31_073712_add_attachment_to_order_items_table.php`

Migration ini menambah kolom `attachment_path` + `attachment_name` ke `order_items` — padahal
kolom `attachment_path` (dan `attachment_original_name`) **sudah** ditambahkan lebih dulu oleh
`2026_07_30_135612_add_attachment_and_result_to_order_items_table.php`. Hasilnya: migration ini
gagal dengan `duplicate column name: attachment_path` setiap kali database di-migrate dari nol
(misalnya `RefreshDatabase` di test, atau `migrate:fresh`).

**Sudah diverifikasi:** `php artisan test --filter=CartCheckoutTest` gagal 5/5 dengan error ini.

**Saran:** hapus file migration ini (kolom yang benar-benar dipakai adalah
`attachment_original_name`, bukan `attachment_name`).

## 2. `OrderController::downloadAttachment()` baca kolom yang salah

**File:** `app/Http/Controllers/Api/OrderController.php`

```php
return Storage::disk('local')->download($item->attachment_path, $item->attachment_name);
```

`store()` menyimpan nama file asli ke kolom `attachment_original_name`, bukan `attachment_name`
(kolom `attachment_name` cuma ada gara-gara migration duplikat di poin #1, dan tidak pernah diisi).
Akibatnya file hasil download attachment selalu pakai nama yang salah/kosong.

**Saran:** ganti jadi `$item->attachment_original_name`. Ini otomatis wajib dilakukan begitu poin
#1 di atas dibereskan (kolom `attachment_name` akan hilang sama sekali).

## 3. Kode mati di `OrderController::store()` bisa bikin crash 500

**File:** `app/Http/Controllers/Api/OrderController.php`, di dalam transaksi `store()`

```php
$file = $request->file("attachments.{$item->service_id}");
$path = $file->store("attachments/{$order->order_no}", 'local');
```

Dua baris ini sisa kode lama, hasilnya tidak pernah dipakai (`$path` tidak dipakai lagi setelahnya
— logic penyimpanan attachment yang benar ada beberapa baris di bawahnya). Karena attachment kini
opsional untuk service yang `requires_attachment = false`, `$request->file(...)` bisa saja
`null` — lalu `$file->store(...)` jadi **fatal error** (`Call to a member function store() on
null`) → response 500 saat checkout tanpa attachment untuk layanan yang tidak mewajibkannya.

**Saran:** hapus dua baris itu saja.

## 4. Test `CartCheckoutTest` sudah tidak sesuai kode saat ini

**File:** `tests/Feature/CartCheckoutTest.php`

- `makeService()` belum punya parameter untuk `requires_attachment`, padahal `OrderController::store()`
  sudah pakai `$item->service->requires_attachment` untuk menentukan attachment wajib/opsional.
- `test_checkout_fails_without_attachment()` asumsinya attachment selalu wajib — begitu poin #3
  dibereskan (attachment jadi benar-benar opsional untuk service yang tidak mewajibkan), test ini
  jadi salah asumsi dan perlu split jadi dua skenario: gagal ketika `requires_attachment = true`,
  sukses ketika `false`.
- Asersi `assertJsonPath('items.0.attachment_name', 'naskah.pdf')` harus jadi
  `attachment_original_name` (field yang benar-benar dikembalikan API, lihat `OrderItemResource`).

## 5. Rute cart/order/payment duplikat di `routes/api.php`

**File:** `routes/api.php`

Baris 52–70 (tanpa middleware `auth:sanctum`) mendaftarkan rute yang **sama persis** dengan yang
ada di dalam grup `auth:sanctum` (baris 89–102) — sisa dari sebelum alur checkout diubah jadi
wajib login. Karena Laravel pakai rute pertama yang cocok, rute publik (tanpa auth) itu yang
sebenarnya aktif, bukan yang di dalam grup auth. Tidak sampai jadi lubang keamanan (tanpa login,
`Order::findAccessibleOrFail` tetap 404 dan `OrderController::store()` crash karena `$user` null),
tapi ini kode mati yang membingungkan dan idealnya dibersihkan — cukup hapus blok baris 52–70,
sisakan yang di dalam grup `auth:sanctum`.

Terkait: rute download **hasil** (`orders/{order_no}/items/{item}/result`) cuma terdaftar di blok
publik itu, tidak didaftarkan ulang di dalam grup `auth:sanctum` — beda pola dengan rute attachment
yang didaftarkan di kedua blok. Perlu diselaraskan saat membereskan poin ini.

## 6. Status pembayaran bisa "stuck" kalau webhook Midtrans tidak sampai

**File:** `app/Http/Controllers/Api/PaymentController.php` (`status()`), `PaymentNotificationController.php`

Status order (`awaiting_payment` → `paid`) murni bergantung pada webhook Midtrans
(`POST /api/payments/notification`). Kalau backend jalan di `localhost` (dev lokal) atau webhook
URL di dashboard Midtrans belum di-set/tidak reachable, webhook **tidak akan pernah sampai**, dan
halaman pembayaran di frontend akan terus polling status yang sama tanpa berubah selamanya.

**Saran perbaikan** (sempat dicoba, hasilnya bagus, tapi di-revert supaya keputusan desain tetap di
tangan yang mengerjakan backend):
- Tambah helper bersama (mis. `App\Support\PaymentStatusSync`) yang menerapkan status Midtrans ke
  `Payment` + `Order` secara idempotent (pakai `lockForUpdate()`), dipakai baik oleh webhook maupun
  endpoint status.
- Di `PaymentController::status()`, kalau payment masih belum status final, panggil langsung
  `\Midtrans\Transaction::status($payment->midtrans_order_id)` dan terapkan hasilnya lewat helper
  di atas. Jadi polling dari frontend tetap bisa "menyembuhkan diri" walau webhook tidak pernah
  sampai — dibungkus try/catch supaya kalau Midtrans tidak bisa dihubungi, tidak menjatuhkan
  endpoint (biarkan polling berikutnya coba lagi).
- Alternatif/tambahan di luar kode: pastikan `APP_URL` publik (pakai tunnel semacam ngrok saat dev)
  dan Payment Notification URL di dashboard Midtrans Sandbox di-set ke URL itu.

## 7. Endpoint `PATCH /orders/{order_no}` belum ada — dipakai aktif oleh frontend, sekarang error

**Status: sudah bukan opsional.** Frontend (`app/bayar/data/page.jsx`, mode edit — dibuka dari
tombol "Ubah data pemesan" di halaman `/bayar`) sudah memanggil
`PATCH /api/orders/{order_no}` (lihat `lib/api.js` → `api.orders.update()`) untuk mengubah
`guest_name`/`guest_phone` sebelum bayar. Karena route-nya belum terdaftar di backend, user dapat
error nyata di production/testing:

```
The PATCH method is not supported for route api/orders/INV-20260804-4312. Supported methods: GET, HEAD.
```

**Yang perlu ditambahkan:**
- Route baru: `Route::patch('orders/{order_no}', [OrderController::class, 'update'])` di dalam
  grup `auth:sanctum` (baris ~89–102 di `routes/api.php`, sekelompok dengan route `orders` lain).
- Method `OrderController::update(Request $request, string $order_no)`:
  - `Order::findAccessibleOrFail($order_no, $request)` untuk resolve + otorisasi order-nya.
  - Validasi `guest_name` (`nullable|string|max:255`), `guest_phone` (`nullable|string|max:30`) —
    ikuti aturan yang sama dengan `store()`.
  - Tolak (422) kalau `order->status` sudah final (`paid`/`failed`/`cancelled`/`expired`) — sejalan
    dengan komentar di frontend ("items/attachments stay locked once an order exists"), field yang
    boleh diubah cuma nama & no. WhatsApp, bukan item/attachment.
  - Simpan lalu `return new OrderResource($order->fresh())`.

Draft lama pernah ada di git stash lokal (`stash@{0}`, sudah tidak di working tree) tapi strukturnya
sudah agak beda dari kode sekarang — sebaiknya ditulis ulang dari nol mengikuti pola di atas,
bukan restore stash.

## 8. Eager loading `->with('items')` di listing order (minor, perf)

`Admin\OrderController::index()` dan `OrderController::index()` (customer) melakukan query
`Order::...->paginate(...)` tanpa `->with('items')`, sementara `OrderResource` mengakses
`$this->whenLoaded('items')` — kalau nanti field items ini ditampilkan di listing (bukan cuma di
`show()`), berpotensi N+1 query. Belum berdampak sekarang karena listing tidak menampilkan items,
tapi baik untuk diwaspadai kalau field itu dipakai di tampilan admin/riwayat order nanti.

## 9. Field `services.price` sudah tidak dipakai — putuskan mau dibuang atau tidak

**File:** `app/Models/Service.php`, `app/Http/Resources/ServiceResource.php`,
`app/Filament/Resources/ServiceResource.php`, `database/migrations/2026_07_12_044326_create_services_table.php`

Sejak alur konsultasi harga dipakai, harga tidak lagi ditentukan di katalog: `OrderController::store()`
membuat item dengan `price_snapshot = null`, dan harga sebenarnya baru diisi admin per pesanan lewat
`Admin\OrderController::quote()` / action "Set Harga" di panel Filament. Frontend juga sudah berhenti
menampilkan `service.price` sama sekali (harga di halaman detail produk dan keranjang sudah dihapus).

Jadi `services.price` sekarang **kolom yatim**: masih ada di database, masih dikirim API, masih bisa
diisi admin di form Filament (`ServiceResource`, field "Harga" + kolom tabel `money('IDR')`), tapi
tidak ada satupun konsumen yang memakainya. Risikonya bikin bingung admin — mereka mengisi harga di
katalog dan mengira itu yang ditagihkan ke pelanggan, padahal diabaikan.

**Pilihannya, tinggal pilih satu:**
- **Buang total** — hapus field dari form + kolom tabel Filament, dari `ServiceResource` (API), dari
  `#[Fillable]` di model, lalu migration `dropColumn('price')`. Paling bersih, tapi tidak bisa
  mundur kalau ternyata nanti mau ada harga acuan.
- **Simpan sebagai harga acuan internal** — kolom tetap ada, tapi di Filament diberi label jelas
  (mis. "Harga acuan (tidak ditagihkan)") dan `->helperText()` yang menyebut harga final ditentukan
  saat quote. Dipakai admin sebagai contekan waktu konsultasi WhatsApp. Berhenti dikirim di
  `ServiceResource` (API) karena frontend tidak butuh.
- **Biarkan** — tidak mengganggu fungsi apapun, cuma tetap membingungkan admin.

Condong ke opsi kedua: admin tetap butuh angka pegangan saat menawar via WhatsApp, dan biayanya cuma
label + helper text. Tapi ini keputusan produk, bukan teknis.

## 10. `GET /services` (list) tidak eager-load `category` — halaman `/kategori` di frontend selalu tampil "0 layanan"

**File:** `app/Http/Controllers/Api/ServiceController.php`, method `index()`

`index()` query servicenya tanpa `->with('category')`:

```php
$services = Service::query()
    ->where('is_active', true)
    ->when($request->query('category'), function ($query, $categorySlug) {
        $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
    })
    ...
    ->paginate($limit, ['*'], 'page', (int) $request->query('page', 1));
```

Filter `category=<slug>` di atas jalan (pakai `whereHas`), tapi relasinya tidak di-load ke tiap
item. `ServiceResource::toArray()` menulis `'category' => new CategoryResource($this->whenLoaded('category'))`
— karena tidak pernah `with('category')`, `whenLoaded` selalu balikin `MissingValue`, dan field
`category` hilang total dari tiap item di response `GET /services` (dicek langsung: response-nya
tidak punya key `category` sama sekali). Sebagai perbandingan, `show()` (single service) sudah benar
— dia pakai `->with('category')`.

**Dampak ke frontend:** halaman `/kategori` (`demo-eksternal-ecc-bts/app/(site)/kategori/page.jsx`)
fetch semua service lewat `api.services.list({ limit: 100 })` lalu hitung jumlah per kategori di
client dengan `services.filter((s) => s.category?.slug === c.slug)`. Karena `s.category` selalu
`undefined`, hasilnya selalu 0 — setiap kartu kategori di halaman itu menampilkan "0 layanan",
untuk semua kategori, meskipun datanya ada. Sudah diverifikasi langsung di browser (screenshot +
respons API `GET /services?limit=100`, tidak ada key `category` di item manapun).

**Saran:** tambah `->with('category')` di query `index()`, sejajar dengan yang sudah dilakukan
`show()`.

## 11. `testimonials` tidak punya field foto klien — frontend selalu jatuh ke avatar inisial

**File:** `app/Models/Testimonial.php`, `app/Http/Resources/TestimonialResource.php`, migrasi
`create_testimonials_table`

Diminta polish di section "Apa Kata Mereka" (`demo-eksternal-ecc-bts/components/sections/Testimonials.jsx`)
supaya foto asli klien dipakai kalau tersedia, fallback ke avatar inisial kalau tidak. Sudah
diimplementasi di frontend (komponen `Avatar` membaca `t.photo_url`, fallback ke inisial), tapi
`TestimonialResource::toArray()` sekarang tidak mengirim field itu sama sekali — model/migrasi juga
tidak punya kolomnya — jadi untuk semua testimoni yang ada sekarang, hasilnya selalu fallback ke
inisial.

**Saran:** kalau memang mau dipakai, tambah kolom `photo_url` (nullable string) ke tabel
`testimonials`, isi field itu di `TestimonialResource::toArray()`, dan sediakan cara upload/isi
fotonya di sisi admin. Kalau tidak ada rencana mengumpulkan foto klien, ini bisa diabaikan — fallback
inisial di frontend sudah aman dipakai selamanya.

---
---

# Temuan audit bug menyeluruh — 2026-09-05

Semua di atas (#1–#11) adalah catatan dari sesi-sesi sebelumnya. Poin-poin di bawah ini **baru**,
ditemukan lewat audit bug menyeluruh (backend + frontend) atas permintaan user, terpisah dari
riwayat di atas. Sama seperti sebelumnya: murni laporan hasil investigasi read-only, belum ada
satupun yang diterapkan ke kode.

## 12. Login Google menerima ID token dari client Google mana pun, bukan cuma milik aplikasi ini

**File:** `app/Support/GoogleIdTokenVerifier.php:14`

```php
$payload = $client->verifyIdToken($idToken, ['audience' => config('services.google.client_id')]);
```

`GOOGLE_CLIENT_ID` tidak diset di `.env` (dicek langsung, kosong di semua environment saat ini),
jadi `audience` yang dikirim ke verifier selalu `null`. Di internal `google/apiclient` /
`google/auth` (`AccessToken::verify()`), pengecekan klaim `aud` dibungkus `if ($audience) { ... }`
— audience yang falsy membuat pengecekan itu **di-skip total**. Signature dan issuer tetap
diverifikasi (jadi token tetap harus benar-benar terbit dari Google), tapi **token ID dari client
Google App manapun** — bukan cuma dari frontend ECC-BTS — akan diterima `POST /api/auth/google`.

**Skenario konkret:** seseorang login "Sign in with Google" di situs pihak ketiga yang sama sekali
tidak berhubungan (Google account asli, email X), lalu ID token dari situs itu direplay ke endpoint
ini — sistem akan membuat/login akun ECC-BTS untuk email X, seolah-olah user itu login lewat Google
di situs ini.

**Saran:** isi `GOOGLE_CLIENT_ID` di `.env` setiap environment (dev, staging, prod) dengan OAuth
client ID yang benar-benar dipakai frontend, supaya pengecekan `audience` aktif.

## 13. Refund dari Midtrans tidak pernah diproses setelah pembayaran mencapai status lunas

**File:** `app/Support/PaymentStatusSync.php:15,22-25`

```php
private const TERMINAL_STATUSES = ['settlement', 'capture', 'deny', 'cancel', 'expire', 'refund', 'partial_refund'];
...
if (in_array($locked->transaction_status, self::TERMINAL_STATUSES, true)) {
    return;
}
```

List yang sama dipakai untuk dua tujuan berbeda: "jangan proses ulang webhook yang statusnya sudah
final" (memang perlu, supaya idempotent) DAN "jangan proses apapun lagi setelah salah satu status
ini" (yang jadi masalah). Karena `settlement` ada di list yang sama dengan `refund`/`partial_refund`,
begitu payment mencapai `settlement` (lunas), webhook refund yang datang belakangan — misalnya
ketika admin melakukan refund manual lewat dashboard Midtrans — langsung dianggap "sudah final" dan
di-drop diam-diam. Tidak ada log, tidak ada error. Order tetap tercatat berstatus `paid` selamanya,
tanpa jejak refund apapun di sistem lokal.

**Saran:** pisahkan konsep "status transaksi mana yang sudah final, tidak akan berubah lagi
(mis. `deny`, `cancel`, `expire`)" dari "status transaksi mana yang boleh ditimpa status berikutnya
(mis. `settlement` → `refund`/`partial_refund` adalah transisi valid)". `refund`/`partial_refund`
seharusnya tetap diterima dan diproses meski payment sedang di `settlement`.

## 14. Testimoni yang disubmit pelanggan langsung tayang publik tanpa moderasi

**File:** `app/Http/Controllers/Api/OrderController.php:178-183` (`submitTestimonial`), migrasi
`2026_08_04_075340_add_user_and_order_to_testimonials_table.php:17`

`submitTestimonial()` membuat `Testimonial` baru dari input pelanggan (teks bebas hingga 2000
karakter + rating 1–5) tapi tidak pernah men-set `is_active` secara eksplisit; kolom itu default
`true` di migration. `TestimonialController::index()` menampilkan semua testimoni dengan
`is_active = true`. Konsekuensinya: **siapa pun yang order-nya berstatus selesai bisa langsung
mempublikasikan teks apapun ke section "Apa Kata Mereka" di homepage**, tanpa ada satu langkah
approval admin pun.

**Saran:** set `is_active => false` (atau kolom status baru seperti `pending`/`approved`) saat
membuat testimoni dari `submitTestimonial()`, dan sediakan aksi approve di Filament sebelum tayang
publik.

## 15. Route `cart`/`orders` tanpa middleware auth terdaftar duluan — bisa dipicu crash 500 alih-alih 401

**File:** `routes/api.php:45-49,52,55` (dibanding versi yang benar di `:88-99`)

Ada dua kelompok route yang mendaftarkan path `cart`/`orders` yang sama: satu tanpa
`auth:sanctum` (baris 45-49, 52, 55 — sisa kode lama, sudah disinggung di TODO #5 sebagai "kode
mati"), satu lagi dengan `auth:sanctum` yang benar (baris 88-99). Laravel memakai route pertama
yang cocok, jadi **kelompok tanpa auth itu yang sebenarnya aktif melayani request**, bukan yang
bermiddleware. `Cart::forRequest()` (`app/Models/Cart.php:32-36`) dan `OrderController::store()`/
`index()` (`:58`, `:226`) memanggil `$request->user()->id` tanpa null-check, dengan asumsi
`auth:sanctum` sudah menjamin ada user. Request TANPA login ke `GET/DELETE /api/cart`,
`POST /api/cart/items`, `POST /api/orders`, atau `GET /api/orders` akan crash dengan error mentah
("call to member function id() on null") → generic 500, bukan 401 yang bersih. Tidak ada kebocoran
data (tidak ada data yang benar-benar dikembalikan), tapi ini crash path yang bisa dipicu siapa
saja tanpa login.

**Saran:** hapus blok route lama tanpa `auth:sanctum` ini (sudah direkomendasikan di TODO #5), yang
otomatis membuat request tanpa login jatuh ke route bermiddleware yang benar → 401 rapi.

## 16. Race condition minor saat membuat pembayaran bisa memicu 500 mentah pada double-submit

**File:** `app/Http/Controllers/Api/PaymentController.php:41`

`$order->payments()->count() + 1` dipakai untuk membentuk `midtrans_order_id`, dihitung di luar
lock/transaction apapun. Kolom `payments.midtrans_order_id` punya unique constraint di database
(`2026_07_12_044335_create_payments_table.php`). Kalau ada double-click atau dua tab yang submit
pembayaran hampir bersamaan untuk order yang sama, kedua request bisa menghitung id yang sama
persis; request kedua akan gagal dengan `QueryException` tak tertangkap → 500 mentah. Tidak ada
korupsi data (unique index mencegah itu), tapi pengalaman penggunanya jelek (error server generik,
bukan pesan "pembayaran sedang diproses").

**Saran:** hitung nomor urut pembayaran di dalam transaction dengan lock (mis.
`lockForUpdate()` pada order), atau pakai mekanisme id yang tidak bergantung pada count baris
(mis. UUID/ULID), lalu tangkap `QueryException` unique-violation dan kembalikan pesan yang jelas.

## 17. `User` model mengizinkan mass-assignment kolom `role` — jebakan privilege-escalation laten

**File:** `app/Models/User.php:16`

```php
#[Fillable(['name', 'email', 'password', 'role', ...])]
```

Saat ini belum tereksploitasi — `AuthController::register()` membangun `$data` secara eksplisit
dari input yang sudah divalidasi dan tidak pernah menyertakan `role`, jadi tidak ada jalan bagi user
biasa untuk mendaftar sebagai admin lewat endpoint yang ada sekarang. Tapi selama `role` tetap ada
di daftar `Fillable`, kode baru manapun yang lengah — misalnya suatu saat memakai
`User::create($request->all())` atau `$user->update($request->validated())` dengan validasi yang
kurang ketat — bisa membuka celah privilege-escalation tanpa disadari.

**Saran:** keluarkan `role` dari `#[Fillable]`, dan set/ubah kolom itu secara eksplisit
(`$user->role = 'admin'; $user->save();`) di tempat-tempat yang memang sengaja mengubahnya (mis.
seeder, aksi admin di Filament).
