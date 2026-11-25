<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Core\Permissions\PermissionMatrix;
use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\InventoryService;
use App\Modules\Checkout\Models\Payment;
use App\Modules\Notifications\Models\Alert;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\ReturnRequest;
use App\Modules\Orders\Services\ShippingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Live Inertia page controllers and form actions for Commerce & Settings dashboard screens.
 */
class CommercePagesController extends Controller
{
    public function __construct(
        private readonly CurrentStore $current,
        private readonly InventoryService $inventory,
        private readonly ShippingService $shipping,
    ) {}

    public function categories(): Response
    {
        $categories = Category::query()
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'name_ar' => $category->name_ar,
                'slug' => $category->slug,
                'sort_order' => $category->sort_order,
                'products_count' => (int) $category->products_count,
            ])
            ->values()
            ->all();

        return Inertia::render('categories/index', [
            'categories' => $categories,
        ]);
    }

    public function inventory(): Response
    {
        $products = Product::query()
            ->orderBy('stock')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'name_ar' => $product->name_ar,
                'sku' => $product->sku,
                'stock' => $product->stock,
                'reorder_threshold' => $product->reorder_threshold,
                'status' => $product->status->value,
                'price' => $product->price,
            ])
            ->values()
            ->all();

        return Inertia::render('products/inventory', [
            'products' => $products,
        ]);
    }

    public function adjustStock(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'delta' => ['required', 'integer', 'between:-100000,100000', 'not_in:0'],
        ]);

        $this->inventory->adjustStock($product, (int) $data['delta']);

        return back()->with('success', __('dashboard.saved'));
    }

    public function returns(): Response
    {
        $returns = ReturnRequest::query()
            ->with('order')
            ->latest()
            ->get()
            ->map(fn (ReturnRequest $returnRequest) => [
                'id' => $returnRequest->id,
                'order_id' => $returnRequest->order_id,
                'order_number' => $returnRequest->order?->number,
                'customer_name' => $returnRequest->order?->customer_name,
                'type' => $returnRequest->type,
                'reason' => $returnRequest->reason,
                'status' => $returnRequest->status,
                'refund_amount' => $returnRequest->refund_amount,
                'created_at' => $returnRequest->created_at->toIso8601String(),
                'reviewed_at' => $returnRequest->reviewed_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $deliveredOrders = Order::query()
            ->where('status', OrderStatus::Delivered)
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'number' => $order->number,
                'customer_name' => $order->customer_name,
                'total' => $order->total,
                'delivered_at' => $order->delivered_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return Inertia::render('orders/returns', [
            'returns' => $returns,
            'deliveredOrders' => $deliveredOrders,
        ]);
    }

    public function shipping(): Response
    {
        $zones = $this->shipping->listZones()
            ->map(fn ($zone) => [
                'id' => $zone->id,
                'governorate' => $zone->governorate,
                'rate' => $zone->rate,
                'delivery_days' => $zone->delivery_days,
                'sort_order' => $zone->sort_order,
            ])
            ->values()
            ->all();

        return Inertia::render('settings/shipping', [
            'zones' => $zones,
            'rates' => $this->shipping->rates(),
            'governorates' => ShippingService::GOVERNORATES,
        ]);
    }

    public function payments(): Response
    {
        $mapPayment = fn (Payment $payment) => [
            'id' => $payment->id,
            'order_id' => $payment->order_id,
            'order_number' => $payment->order?->number,
            'customer_name' => $payment->order?->customer_name,
            'gateway' => $payment->gateway,
            'mode' => $payment->mode,
            'status' => $payment->status,
            'amount' => $payment->amount,
            'gateway_fee' => $payment->gateway_fee,
            'cod_fee' => $payment->cod_fee,
            'external_reference' => $payment->external_reference,
            'receipt_path' => $payment->receipt_path,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'created_at' => $payment->created_at->toIso8601String(),
        ];

        $pendingInstaPay = Payment::query()
            ->with('order')
            ->where('gateway', 'instapay')
            ->where('status', Payment::STATUS_PENDING)
            ->latest()
            ->get()
            ->map($mapPayment)
            ->values()
            ->all();

        $recentPayments = Payment::query()
            ->with('order')
            ->latest()
            ->limit(20)
            ->get()
            ->map($mapPayment)
            ->values()
            ->all();

        return Inertia::render('settings/payments', [
            'pendingInstaPay' => $pendingInstaPay,
            'recentPayments' => $recentPayments,
        ]);
    }

    public function notifications(): Response
    {
        $alerts = Alert::query()
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (Alert $alert) => [
                'id' => $alert->id,
                'type' => $alert->type,
                'severity' => $alert->severity,
                'title' => $alert->title,
                'body' => $alert->body,
                'action_url' => $alert->action_url,
                'read_at' => $alert->read_at?->toIso8601String(),
                'created_at' => $alert->created_at->toIso8601String(),
            ])
            ->values()
            ->all();

        return Inertia::render('settings/notifications', [
            'alerts' => $alerts,
        ]);
    }

    public function domain(Request $request): Response
    {
        $store = $this->current->getOrFail();
        /** @var User|null $user */
        $user = $request->user();
        $role = $user?->roleIn($store);

        return Inertia::render('settings/domain', [
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'subdomain' => $store->subdomain,
                'url' => $store->url(),
                'status' => $store->status()->value,
                'role' => $role?->value,
                'permissions' => $role === null ? [] : PermissionMatrix::for($role),
                'domain' => (string) config('minivora.domain'),
                'maintenance_mode' => $store->isInMaintenanceMode(),
                'maintenance_message' => (string) ($store->profile['maintenance_message'] ?? ''),
                'published_at' => $store->published_at?->toIso8601String(),
            ],
        ]);
    }

    public function updateMaintenance(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'maintenance_mode' => ['required', 'boolean'],
            'maintenance_message' => ['nullable', 'string', 'max:500'],
        ]);

        $store = $this->current->getOrFail();
        $profile = $store->profile ?? [];
        $profile['maintenance_mode'] = (bool) $data['maintenance_mode'];
        $profile['maintenance_message'] = isset($data['maintenance_message'])
            ? trim((string) $data['maintenance_message'])
            : '';

        $store->profile = $profile;
        $store->save();

        return back()->with('success', __('dashboard.saved'));
    }
}
