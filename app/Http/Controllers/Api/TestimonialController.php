<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TestimonialResource;
use App\Models\Testimonial;

class TestimonialController extends Controller
{
    /** Beranda hanya menampilkan sebagian testimoni. */
    private const LIMIT = 6;

    public function index()
    {
        // Tanpa batas, section testimoni di beranda tumbuh selamanya — 50
        // testimoni = 17 baris kartu. Admin memilih mana yang tampil lewat
        // sort_order dan tombol Nonaktifkan di panel, bukan lewat setelan baru.
        //
        // sort_order testimoni dari pelanggan selalu 0, jadi tanpa urutan
        // kedua susunannya tidak menentu; id menurun = yang terbaru menang.
        $testimonials = Testimonial::where('is_active', true)
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();

        return ['data' => TestimonialResource::collection($testimonials)];
    }
}
