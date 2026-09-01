<?php

namespace Tests\Unit;

use App\Models\SiteConfig;
use PHPUnit\Framework\TestCase;

/**
 * Nilai maps_embed_url berakhir sebagai src <iframe> di halaman kontak, jadi
 * hanya URL embed Google Maps yang boleh lolos — lihat SiteConfig::mapsEmbedUrl.
 */
class SiteConfigMapsEmbedTest extends TestCase
{
    public function test_cuplikan_iframe_dipangkas_jadi_url_saja(): void
    {
        $config = new SiteConfig;
        $config->maps_embed_url = '<iframe src="https://www.google.com/maps/embed?pb=!1m18!2sid" width="600" height="450" style="border:0;" allowfullscreen></iframe>';

        $this->assertSame('https://www.google.com/maps/embed?pb=!1m18!2sid', $config->maps_embed_url);
    }

    public function test_url_embed_polos_diterima_apa_adanya(): void
    {
        $config = new SiteConfig;
        $config->maps_embed_url = 'https://www.google.com/maps/embed?pb=!1m18!2sid';

        $this->assertSame('https://www.google.com/maps/embed?pb=!1m18!2sid', $config->maps_embed_url);
    }

    public function test_tautan_selain_embed_google_maps_ditolak(): void
    {
        foreach (['https://maps.app.goo.gl/abc123', '<iframe src="https://evil.example.com/x"></iframe>', ''] as $input) {
            $config = new SiteConfig;
            $config->maps_embed_url = $input;

            $this->assertNull($config->maps_embed_url, "seharusnya null untuk: {$input}");
        }
    }
}
