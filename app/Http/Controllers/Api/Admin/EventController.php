<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EventController extends Controller
{
    public function index()
    {
        $events = Event::with('category')->orderBy('sort_order')->orderBy('id')->get();

        return ['data' => EventResource::collection($events)];
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        if ($request->hasFile('flyer')) {
            $data['flyer_path'] = $request->file('flyer')->store('events', 'public');
        }

        return new EventResource(Event::create($data)->load('category'));
    }

    public function update(Request $request, Event $event)
    {
        $data = $request->validate($this->rules(sometimes: true));

        if ($request->hasFile('flyer')) {
            if ($event->flyer_path) {
                Storage::disk('public')->delete($event->flyer_path);
            }

            $data['flyer_path'] = $request->file('flyer')->store('events', 'public');
        }

        $event->update($data);

        return new EventResource($event->load('category'));
    }

    public function destroy(Event $event)
    {
        if ($event->flyer_path) {
            Storage::disk('public')->delete($event->flyer_path);
        }

        $event->delete();

        return response()->noContent();
    }

    private function rules(bool $sometimes = false): array
    {
        $prefix = $sometimes ? 'sometimes|required|' : 'required|';

        return [
            'category_id' => 'nullable|integer|exists:categories,id',
            'title' => $prefix.'string|max:160',
            'description' => 'nullable|string',
            'flyer' => 'nullable|image|max:4096',
            'location' => 'nullable|string|max:160',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'sort_order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
