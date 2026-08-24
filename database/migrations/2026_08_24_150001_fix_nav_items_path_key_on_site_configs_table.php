<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('site_configs')->whereNotNull('nav_items')->get() as $config) {
            $items = json_decode($config->nav_items, true) ?? [];

            $fixed = array_map(function ($item) {
                if (isset($item['path']) && ! isset($item['url'])) {
                    $item['url'] = $item['path'];
                    unset($item['path']);
                }

                return $item;
            }, $items);

            DB::table('site_configs')
                ->where('id', $config->id)
                ->update(['nav_items' => json_encode($fixed)]);
        }
    }

    public function down(): void
    {
        // Data fix only, not reversible.
    }
};
