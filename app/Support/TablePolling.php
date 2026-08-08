<?php

namespace App\Support;

use Closure;

class TablePolling
{
    /**
     * Interval polling tabel Filament yang mati sendiri selama ada modal aksi
     * terbuka.
     *
     * `wire:poll` memicu `$refresh` pada seluruh komponen Livewire. Kalau itu
     * jalan saat admin sedang mengisi modal (mis. "Set Harga"), morph DOM
     * menimpa isian yang belum ter-sync ke server dan klik Submit hilang
     * ditelan request polling yang sedang jalan — tombolnya terasa mati.
     *
     * Filament mengevaluasi ulang closure ini tiap render, dan membuka/menutup
     * modal selalu memicu render. Jadi atribut `wire:poll` benar-benar lepas
     * dari DOM selama modal terbuka, lalu pasang lagi setelah ditutup.
     */
    public static function whileIdle(string $interval = '2s'): Closure
    {
        return fn ($livewire) => filled($livewire->mountedTableActions)
            || filled($livewire->mountedActions)
            || filled($livewire->mountedTableBulkAction)
                ? null
                : $interval;
    }
}
