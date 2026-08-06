<?php

namespace App\Http\Controllers\Filament;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Storage;

class OrderItemFileController extends Controller
{
    public function attachment(OrderItem $orderItem)
    {
        return $this->stream($orderItem, 'attachment');
    }

    public function result(OrderItem $orderItem)
    {
        return $this->stream($orderItem, 'result');
    }

    private function stream(OrderItem $orderItem, string $type)
    {
        $pathField = "{$type}_path";
        $nameField = "{$type}_original_name";

        abort_if(! $orderItem->$pathField, 404);

        return Storage::disk('local')->response($orderItem->$pathField, $orderItem->$nameField);
    }
}
