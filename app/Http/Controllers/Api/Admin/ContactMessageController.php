<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContactMessageResource;
use App\Models\ContactMessage;
use Illuminate\Http\Request;

class ContactMessageController extends Controller
{
    public function index(Request $request)
    {
        $limit = min((int) $request->query('limit', 20) ?: 20, 100);

        $messages = ContactMessage::query()
            ->latest('id')
            ->paginate($limit, ['*'], 'page', (int) $request->query('page', 1));

        return [
            'data' => ContactMessageResource::collection($messages->items()),
            'meta' => [
                'page' => $messages->currentPage(),
                'limit' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ];
    }

    public function destroy(ContactMessage $contact_message)
    {
        $contact_message->delete();

        return response()->noContent();
    }
}
