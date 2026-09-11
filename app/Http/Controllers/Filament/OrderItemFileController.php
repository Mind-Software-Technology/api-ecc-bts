<?php

namespace App\Http\Controllers\Filament;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\OrderItemAttachment;
use App\Models\OrderItemResult;
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

    /**
     * One order item can carry several attachments/results (up to its qty) —
     * these two serve one specific row, unlike attachment()/result() above
     * which only ever serve the single latest one.
     */
    public function attachmentFile(OrderItemAttachment $orderItemAttachment)
    {
        return Storage::disk('local')->response($orderItemAttachment->path, $orderItemAttachment->original_name);
    }

    public function resultFile(OrderItemResult $orderItemResult)
    {
        return Storage::disk('local')->response($orderItemResult->path, $orderItemResult->original_name);
    }

    private function stream(OrderItem $orderItem, string $type)
    {
        $pathField = "{$type}_path";
        $nameField = "{$type}_original_name";

        abort_if(! $orderItem->$pathField, 404);

        return Storage::disk('local')->response($orderItem->$pathField, $orderItem->$nameField);
    }
}
