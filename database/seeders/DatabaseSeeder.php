<?php

namespace Database\Seeders;

use App\Models\Advantage;
use App\Models\Category;
use App\Models\Faq;
use App\Models\ProcessStep;
use App\Models\Service;
use App\Models\SiteConfig;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Placeholder content for the catalog/marketing tables — the real copy
     * lives in the frontend's src/data/*.js and should replace this once
     * that repo is available to migrate from.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $adminPassword = env('ADMIN_SEED_PASSWORD', Str::random(16));

        User::factory()->create([
            'name' => 'Admin ECC-BTS',
            'email' => 'admin@ecc-bts.id',
            'password' => $adminPassword,
            'role' => 'admin',
        ]);

        if (! env('ADMIN_SEED_PASSWORD')) {
            $this->command?->warn("Admin password (generated): {$adminPassword}");
        }

        $akademik = Category::create([
            'slug' => 'akademik', 'title' => 'Layanan Akademik',
            'short_desc' => 'Bantuan tugas, skripsi, dan konsultasi akademik',
            'description' => 'Layanan pendampingan akademik untuk mahasiswa, mulai dari tugas kuliah hingga skripsi.',
            'icon' => 'FiBookOpen', 'accent' => 'blue', 'sort_order' => 1,
        ]);
        $statistik = Category::create([
            'slug' => 'statistik', 'title' => 'Olah Data & Statistik',
            'short_desc' => 'Analisis data penelitian dengan SPSS/olah statistik',
            'description' => 'Pengolahan dan analisis data penelitian untuk kebutuhan skripsi maupun jurnal.',
            'icon' => 'FiBarChart2', 'accent' => 'green', 'sort_order' => 2,
        ]);
        $bahasa = Category::create([
            'slug' => 'bahasa', 'title' => 'Bahasa & Penulisan',
            'short_desc' => 'Proofread, parafrase, dan terjemahan',
            'description' => 'Perbaikan naskah, parafrase bebas plagiarisme, dan penerjemahan dokumen akademik.',
            'icon' => 'FiEdit3', 'accent' => 'indigo', 'sort_order' => 3,
        ]);

        $services = [
            ['slug' => 'turnitin', 'category_id' => $akademik->id, 'title' => 'Cek Similarity Turnitin',
                'tagline' => 'Cek tingkat plagiarisme naskah sebelum submit', 'price' => 25000, 'badge' => 'Terlaris'],
            ['slug' => 'konsultasi-skripsi', 'category_id' => $akademik->id, 'title' => 'Konsultasi Skripsi',
                'tagline' => 'Pendampingan bimbingan skripsi dari bab 1 sampai selesai', 'price' => 200000, 'badge' => 'Unggulan'],
            ['slug' => 'statistik', 'category_id' => $statistik->id, 'title' => 'Olah Data Statistik (SPSS)',
                'tagline' => 'Analisis data kuantitatif untuk skripsi dan jurnal', 'price' => 150000, 'badge' => 'Populer'],
            ['slug' => 'parafrase', 'category_id' => $bahasa->id, 'title' => 'Parafrase & Bebas Plagiarisme',
                'tagline' => 'Menyusun ulang naskah tanpa mengubah makna', 'price' => 75000, 'badge' => null],
            ['slug' => 'proofread', 'category_id' => $bahasa->id, 'title' => 'Proofreading Naskah',
                'tagline' => 'Koreksi tata bahasa, ejaan, dan format penulisan', 'price' => 100000, 'badge' => 'Baru'],
            ['slug' => 'translate-abstrak', 'category_id' => $bahasa->id, 'title' => 'Translate Abstrak',
                'tagline' => 'Terjemahan abstrak Indonesia-Inggris untuk jurnal', 'price' => 50000, 'badge' => null],
        ];

        foreach ($services as $i => $service) {
            Service::create($service + [
                'description' => $service['tagline'].'. Dikerjakan oleh tim berpengalaman dengan hasil akurat dan tepat waktu.',
                'points' => ['Hasil akurat', 'Dikerjakan tim berpengalaman', 'Revisi jika diperlukan'],
                'icon' => 'FiCheckCircle', 'accent' => 'blue',
                'rating' => 4.5, 'reviews_count' => 20 + $i * 5, 'is_active' => true,
            ]);
        }

        $testimonials = [
            ['name' => 'Ayu Lestari', 'role' => 'Mahasiswa S1', 'text' => 'Prosesnya cepat dan hasilnya rapi, sangat membantu deadline skripsi saya.', 'rating' => 5.0],
            ['name' => 'Budi Santoso', 'role' => 'Mahasiswa S2', 'text' => 'Olah data statistiknya detail dan mudah dipahami penjelasannya.', 'rating' => 4.8],
            ['name' => 'Citra Dewi', 'role' => 'Mahasiswa S1', 'text' => 'Layanan turnitin responsif, hasil similarity keluar cepat.', 'rating' => 4.7],
        ];
        foreach ($testimonials as $i => $t) {
            Testimonial::create($t + ['sort_order' => $i + 1]);
        }

        $faqs = [
            ['question' => 'Berapa lama proses pengerjaan?', 'answer' => 'Bervariasi tergantung layanan, umumnya 1-3 hari kerja.'],
            ['question' => 'Apakah data saya aman?', 'answer' => 'Ya, seluruh dokumen dan data pelanggan bersifat rahasia.'],
            ['question' => 'Metode pembayaran apa saja yang tersedia?', 'answer' => 'QRIS, transfer bank, e-wallet, kartu kredit/debit, dan retail (Alfamart/Indomaret).'],
            ['question' => 'Apakah ada garansi revisi?', 'answer' => 'Ya, revisi tersedia sesuai ketentuan masing-masing layanan.'],
        ];
        foreach ($faqs as $i => $f) {
            Faq::create($f + ['sort_order' => $i + 1]);
        }

        $advantages = [
            ['icon' => 'FiClock', 'title' => 'Cepat & Tepat Waktu', 'description' => 'Pengerjaan sesuai deadline yang disepakati.'],
            ['icon' => 'FiShield', 'title' => 'Data Terjamin Aman', 'description' => 'Kerahasiaan dokumen dan data pelanggan terjaga.'],
            ['icon' => 'FiUsers', 'title' => 'Tim Berpengalaman', 'description' => 'Dikerjakan oleh tim yang kompeten di bidangnya.'],
            ['icon' => 'FiDollarSign', 'title' => 'Harga Terjangkau', 'description' => 'Harga bersaing dengan kualitas terjamin.'],
        ];
        foreach ($advantages as $i => $a) {
            Advantage::create($a + ['sort_order' => $i + 1]);
        }

        $processSteps = [
            ['step_number' => 1, 'icon' => 'FiSearch', 'title' => 'Pilih Layanan', 'description' => 'Pilih layanan yang sesuai kebutuhan.'],
            ['step_number' => 2, 'icon' => 'FiShoppingCart', 'title' => 'Checkout', 'description' => 'Masukkan detail pesanan dan kontak.'],
            ['step_number' => 3, 'icon' => 'FiCreditCard', 'title' => 'Bayar', 'description' => 'Selesaikan pembayaran lewat channel pilihan.'],
            ['step_number' => 4, 'icon' => 'FiCheckCircle', 'title' => 'Terima Hasil', 'description' => 'Hasil dikirim sesuai estimasi waktu pengerjaan.'],
        ];
        foreach ($processSteps as $i => $p) {
            ProcessStep::create($p + ['sort_order' => $i + 1]);
        }

        SiteConfig::create([
            'brand_name' => 'ECC',
            'logo_url' => null,
            'contact_email' => 'ecc-bts@email.com',
            'contact_phone' => '+62 812-0000-0000',
            'address' => 'Indonesia',
            'social_links' => ['instagram' => 'https://instagram.com/eccbts', 'whatsapp' => 'https://wa.me/6281200000000'],
            'hero' => [
                'eyebrow' => 'ECC • Best To Solution',
                'title' => 'Temukan Layanan untuk',
                'title_highlight' => 'Karya Ilmiah Anda',
                'subtitle' => 'Dari cek similarity, olah data, hingga publikasi dan penerbitan buku — semua layanan dalam satu tempat, dikerjakan tim ahli yang profesional dan terpercaya.',
                'stat_works_label' => 'Karya selesai',
                'stat_clients_label' => 'Klien puas',
                'stat_quality_value' => '100%',
                'stat_quality_label' => 'Komitmen kualitas',
            ],
            'nav_items' => [
                ['label' => 'Beranda', 'url' => '/'],
                ['label' => 'Kategori', 'url' => '/kategori'],
                ['label' => 'Produk', 'url' => '/produk'],
                ['label' => 'Kegiatan', 'url' => '/kegiatan'],
                ['label' => 'Tentang Kami', 'url' => '/tentang'],
                ['label' => 'Kontak', 'url' => '/kontak'],
            ],
        ]);
    }
}
