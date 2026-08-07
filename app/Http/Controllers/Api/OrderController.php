<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderItemResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\TestimonialResource;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Testimonial;
use App\Models\User;
use App\Notifications\NewQuoteRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    /**
     * Allowed attachment mime/extensions and max size (KB) for uploadAttachment().
     */
    private const ATTACHMENT_RULES = 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240';

    public function store(Request $request)
    {
        $cart = Cart::forRequest($request);
        abort_if(! $cart || $cart->items()->count() === 0, 422, 'Cart is empty.');

        $user = $request->user();
        $items = $cart->items()->with('service')->get();

        $data = $request->validate([
            'guest_name' => $user ? 'nullable|string|max:255' : 'required|string|max:255',
            'guest_email' => $user ? 'nullable|email|max:255' : 'required|email|max:255',
            'guest_phone' => 'nullable|string|max:30',
        ]);

        $order = DB::transaction(function () use ($cart, $items, $user, $data) {
            $order = Order::create([
                'order_no' => $this->generateOrderNo(),
                'user_id' => $user->id,
                'guest_name' => $data['guest_name'],
                'guest_email' => $user->email,
                'guest_phone' => $data['guest_phone'] ?? null,
                'status' => 'awaiting_quote',
                'subtotal' => null,
                'total' => null,
            ]);

            foreach ($items as $item) {
                $order->items()->create([
                    'service_id' => $item->service_id,
                    'title_snapshot' => $item->service->title,
                    'price_snapshot' => null,
                    'qty' => $item->qty,
                    'line_total' => null,
                ]);
            }

            $cart->items()->delete();

            return $order;
        });

        $order->load('items');
        Notification::send(User::where('role', 'admin')->get(), new NewQuoteRequest($order));

        return new OrderResource($order);
    }

    /**
     * Customer uploads/replaces the attachment for one order item — a
     * separate step from store() now, done after the WhatsApp consultation
     * (order already exists by then, admin prices it off the item list).
     */
    public function uploadAttachment(Request $request, string $order_no, int $item)
    {
        $order = Order::findAccessibleOrFail($order_no, $request);
        abort_unless(
            in_array($order->status, ['awaiting_quote', 'quoted']),
            422,
            'Lampiran hanya bisa diunggah selama pesanan belum disetujui/dibayar.',
        );

        $orderItem = $order->items->firstWhere('id', $item);
        abort_if(! $orderItem, 404);

        $data = $request->validate(['attachment' => self::ATTACHMENT_RULES]);

        if ($orderItem->attachment_path) {
            Storage::disk('local')->delete($orderItem->attachment_path);
        }

        $file = $data['attachment'];
        $orderItem->update([
            'attachment_path' => $file->store('order-attachments', 'local'),
            'attachment_original_name' => $file->getClientOriginalName(),
        ]);

        return new OrderItemResource($orderItem);
    }


    public function downloadResult(Request $request, string $order_no, int $item)
    {
        return $this->downloadOrderItemFile($request, $order_no, $item, 'result');
    }

    private function downloadOrderItemFile(Request $request, string $order_no, int $item, string $type)
    {
        $order = Order::findAccessibleOrFail($order_no, $request);
        $orderItem = $order->items->firstWhere('id', $item);
        abort_if(! $orderItem, 404);

        $pathField = "{$type}_path";
        $nameField = "{$type}_original_name";
        abort_if(! $orderItem->$pathField, 404);

        return Storage::disk('local')->download($orderItem->$pathField, $orderItem->$nameField);
    }

    public function show(Request $request, string $order_no)
    {
        return new OrderResource(Order::findAccessibleOrFail($order_no, $request));
    }

    public function update(Request $request, string $order_no)
    {
        $order = Order::findAccessibleOrFail($order_no, $request);
        abort_if(
            in_array($order->status, ['paid', 'failed', 'cancelled', 'expired']),
            422,
            'Data pemesan tidak bisa diubah karena pesanan sudah berstatus final.',
        );

        $data = $request->validate([
            'guest_name' => 'required|string|max:255',
        ]);

        $order->update($data);

        return new OrderResource($order->load('items'));
    }

    public function submitTestimonial(Request $request, string $order_no)
    {
        $order = Order::findAccessibleOrFail($order_no, $request);

        $fullyDelivered = $order->status === 'paid'
            && $order->items->isNotEmpty()
            && $order->items->every(fn ($item) => $item->result_delivered_at !== null);
        abort_unless($fullyDelivered, 422, 'Testimoni hanya bisa diberikan setelah pesanan berhasil dibayar dan seluruh hasilnya sudah dikirim.');

        abort_if(Testimonial::where('order_id', $order->id)->exists(), 422, 'Anda sudah memberikan testimoni untuk pesanan ini.');

        $data = $request->validate([
            'role' => 'required|string|max:255',
            'text' => 'required|string|max:2000',
            'rating' => 'required|numeric|min:1|max:5',
        ]);

        $testimonial = Testimonial::create($data + [
            'user_id' => $request->user()->id,
            'order_id' => $order->id,
            'name' => $request->user()->name,
            'sort_order' => 0,
        ]);

        return new TestimonialResource($testimonial);
    }

    public function acceptQuote(Request $request, string $order_no)
    {
        $order = Order::findAccessibleOrFail($order_no, $request);
        abort_unless($order->status === 'quoted', 422, 'Pesanan ini belum memiliki penawaran harga untuk disetujui.');

        $order->update(['status' => 'pending']);

        return new OrderResource($order->load('items'));
    }

    public function decline(Request $request, string $order_no)
    {
        $order = Order::findAccessibleOrFail($order_no, $request);
        abort_unless(
            in_array($order->status, ['awaiting_quote', 'quoted']),
            422,
            'Pesanan ini tidak bisa dibatalkan pada status saat ini.',
        );

        $order->update(['status' => 'cancelled']);

        return new OrderResource($order->load('items'));
    }

    public function downloadAttachment(Request $request, string $order_no, int $item_id): StreamedResponse
    {
        $order = Order::findAccessibleOrFail($order_no, $request);
        $item = $order->items()->whereKey($item_id)->firstOrFail();
        abort_unless($item->attachment_path && Storage::disk('local')->exists($item->attachment_path), 404);

        return Storage::disk('local')->download($item->attachment_path, $item->attachment_original_name);
    }

    public function index(Request $request)
    {
        $limit = min((int) $request->query('limit', 20) ?: 20, 100);
        $page = (int) $request->query('page', 1);

        $orders = Order::where('user_id', $request->user()->id)
            ->withExists('testimonial')
            ->with('items.service')
            ->latest()->paginate($limit, ['*'], 'page', $page);

        return [
            'data' => OrderResource::collection($orders->items()),
            'meta' => [
                'page' => $orders->currentPage(),
                'limit' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ];
    }

    /**
     * INV-{Ymd}-{4 random digits}; retry on the rare collision.
     */
    private function generateOrderNo(): string
    {
        do {
            $orderNo = 'INV-'.now()->format('Ymd').'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Order::where('order_no', $orderNo)->exists());

        return $orderNo;
    }
}
