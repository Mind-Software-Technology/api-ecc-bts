<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $events = Event::query()
            ->with('category')
            ->where('is_active', true)
            ->when($request->query('category'), function ($query, $categorySlug) {
                $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
            })
            ->orderBy('sort_order')
            ->orderBy('starts_at')
            ->get();

        return ['data' => EventResource::collection($events)];
    }

    public function show(int $id)
    {
        // is_active ikut disaring: kegiatan yang dinonaktifkan admin harus
        // hilang dari detail juga, bukan cuma dari daftar.
        $event = Event::with('category')
            ->where('is_active', true)
            ->findOrFail($id);

        return new EventResource($event);
    }
}
