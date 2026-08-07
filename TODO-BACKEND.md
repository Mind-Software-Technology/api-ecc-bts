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
