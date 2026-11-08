<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Orders\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customers list (built from orders), a customer's page, and the phone lookup the manual order
 * form uses to fill in a returning shopper.
 */
class CustomerController extends Controller
{
    private const FILTERS = ['all', 'repeat', 'new', 'inactive'];

    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): Response
    {
        $filter = in_array($request->query('filter'), self::FILTERS, true) ? (string) $request->query('filter') : 'all';
        $search = trim((string) $request->query('q', ''));

        $query = Customer::query()->orderByDesc('last_order_at')->orderByDesc('id');
        $this->applyFilter($query, $filter);
        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('email', 'like', $like));
        }

        $counts = [];
        foreach (self::FILTERS as $f) {
            $q = Customer::query();
            $this->applyFilter($q, $f);
            $counts[$f] = $q->count();
        }

        return Inertia::render('customers/index', [
            'customers' => $query->paginate(30)->withQueryString()->through(fn (Customer $c) => $c->toCard()),
            'filter' => $filter,
            'search' => $search,
            'counts' => $counts,
            'stats' => [
                'total_spent' => (int) Customer::query()->sum('total_spent'),
                'repeat_rate' => $counts['all'] > 0 ? (int) round($counts['repeat'] * 100 / $counts['all']) : null,
            ],
        ]);
    }

    public function show(int $customer): Response
    {
        $customer = Customer::query()->findOrFail($customer);

        return Inertia::render('customers/show', [
            'customer' => $customer->toCard() + ['notes' => $customer->notes, 'since' => $customer->created_at->toIso8601String()],
            'orders' => Order::query()->where('customer_id', $customer->id)->with('items')->latest('id')->limit(50)->get()->map(fn (Order $o) => $o->toRow())->values(),
        ]);
    }

    /** Find a returning shopper by phone for the manual order form. */
    public function lookup(Request $request): JsonResponse
    {
        $phone = $this->orders->normalisePhone((string) $request->query('phone', ''));
        if ($phone === null || strlen($phone) < 6) {
            return response()->json(['customer' => null]);
        }

        $customer = Customer::query()->where('phone', $phone)->first();

        return response()->json(['customer' => $customer?->toCard()]);
    }

    /** @param  Builder<Customer>  $query */
    private function applyFilter($query, string $filter): void
    {
        match ($filter) {
            'repeat' => $query->where('orders_count', '>=', 2),
            'new' => $query->where('created_at', '>=', Carbon::now()->subDays(30)),
            'inactive' => $query->where('last_order_at', '<', Carbon::now()->subDays(90)),
            default => null,
        };
    }
}
