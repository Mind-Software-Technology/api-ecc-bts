<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menandai seluruh domain ini (api.ecc-bts.id) sebagai noindex.
 *
 * Isinya cuma endpoint JSON dan halaman login Filament — tidak ada nilainya
 * di hasil pencarian, dan halaman /admin yang terindeks sama saja dengan
 * memasang papan penunjuk ke permukaan admin. Yang boleh muncul di Google
 * hanya situs pengunjung di www.ecc-bts.id.
 *
 * Sengaja lewat header, BUKAN `Disallow: /` di public/robots.txt: robots.txt
 * hanya melarang merayapi, bukan mengindeks. URL yang dilarang dirayapi tapi
 * ditautkan dari tempat lain tetap bisa nongol di hasil pencarian tanpa
 * deskripsi — dan justru karena dilarang merayapi, Google tidak akan pernah
 * membaca noindex-nya. Keduanya saling meniadakan, jadi robots.txt sengaja
 * dibiarkan mengizinkan crawl supaya header ini terbaca.
 */
class NoIndex
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // headers->set(), bukan ->header(): endpoint unduhan lampiran/hasil
        // memakai Storage::download() yang mengembalikan StreamedResponse —
        // kelas Symfony polos yang tidak punya helper ->header() milik Laravel.
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
