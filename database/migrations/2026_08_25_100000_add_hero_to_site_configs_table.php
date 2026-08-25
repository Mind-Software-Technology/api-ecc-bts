<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Teks hero halaman depan dipindah ke database supaya admin bisa
     * mengubahnya sendiri lewat Pengaturan Situs.
     *
     * Satu kolom JSON, bukan delapan kolom teks terpisah — mengikuti idiom
     * yang sudah dipakai model ini untuk `social_links` dan `nav_items`.
     */
    public function up(): void
    {
        Schema::table('site_configs', function (Blueprint $table) {
            $table->json('hero')->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            // /api/site-config dipanggil Navbar di SETIAP halaman, dan sekarang
            // ikut menghitung pesanan lunas. Tanpa index ini kedua COUNT itu
            // memindai seluruh tabel orders tiap kali halaman mana pun dibuka.
            $table->index('status');
        });

        // Isi dengan teks yang selama ini di-hardcode di Hero.jsx, supaya form
        // admin langsung terisi apa adanya — bukan kosong yang bikin bingung.
        DB::table('site_configs')->whereNull('hero')->update([
            'hero' => json_encode([
                'eyebrow' => 'ECC • Best To Solution',
                'title' => 'Temukan Layanan untuk',
                'title_highlight' => 'Karya Ilmiah Anda',
                'subtitle' => 'Dari cek similarity, olah data, hingga publikasi dan penerbitan buku — semua layanan dalam satu tempat, dikerjakan tim ahli yang profesional dan terpercaya.',
                'stat_works_label' => 'Karya selesai',
                'stat_clients_label' => 'Klien puas',
                'stat_quality_value' => '100%',
                'stat_quality_label' => 'Komitmen kualitas',
            ]),
        ]);
    }

    public function down(): void
    {
        Schema::table('site_configs', function (Blueprint $table) {
            $table->dropColumn('hero');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });
    }
};
