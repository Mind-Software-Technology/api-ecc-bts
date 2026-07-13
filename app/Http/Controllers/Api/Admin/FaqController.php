<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\FaqResource;
use App\Models\Faq;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function index()
    {
        $faqs = Faq::orderBy('sort_order')->get();

        return ['data' => FaqResource::collection($faqs)];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'question' => 'required|string|max:255',
            'answer' => 'required|string',
            'sort_order' => 'integer',
        ]);

        return new FaqResource(Faq::create($data));
    }

    public function update(Request $request, Faq $faq)
    {
        $data = $request->validate([
            'question' => 'sometimes|required|string|max:255',
            'answer' => 'sometimes|required|string',
            'sort_order' => 'sometimes|integer',
        ]);

        $faq->update($data);

        return new FaqResource($faq);
    }

    public function destroy(Faq $faq)
    {
        $faq->delete();

        return response()->noContent();
    }
}
