<?php

namespace App\Modules\Accounts\Services;

use App\Core\Tenancy\CurrentStore;
use App\Modules\Accounts\Models\User;
use App\Modules\Orders\Models\Customer;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\ReturnRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Storefront shopper accounts: email/password registration, Google sign-in,
 * and per-store account overview (order history, saved addresses, return requests).
 * FR-ACC-01, FR-ACC-02
 */
class ShopperAccountService
{
    public function __construct(private readonly CurrentStore $current) {}

    /**
     * Register a storefront shopper with email and password without creating a store. FR-ACC-01
     */
    public function register(string $name, string $email, string $password): User
    {
        $normalizedEmail = strtolower(trim($email));

        if (User::query()->whereRaw('LOWER(email) = ?', [$normalizedEmail])->exists()) {
            throw ValidationException::withMessages([
                'email' => __('validation.unique', ['attribute' => 'email']),
            ]);
        }

        return User::query()->create([
            'name' => trim($name),
            'email' => $normalizedEmail,
            'password' => $password,
        ]);
    }

    /**
     * Sign in or register a shopper using Google OAuth identity (passwordless, verified email). FR-ACC-01
     */
    public function loginWithGoogle(string $email, string $name, string $googleId): User
    {
        if (trim($googleId) === '') {
            throw new InvalidArgumentException('Google account ID cannot be empty.');
        }

        $normalizedEmail = strtolower(trim($email));
        $existing = User::query()->whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();

        if ($existing !== null) {
            if ($existing->email_verified_at === null) {
                $existing->email_verified_at = Carbon::now();
                $existing->save();
            }

            return $existing;
        }

        $user = new User([
            'name' => trim($name),
            'email' => $normalizedEmail,
        ]);
        $user->password = null;
        $user->email_verified_at = Carbon::now();
        $user->save();

        return $user;
    }

    /**
     * Return the shopper's order history, saved addresses, and return requests in the current store. FR-ACC-02
     *
     * @return array{
     *     orders: list<array<string, mixed>>,
     *     saved_addresses: list<array{name: string, phone: string|null, governorate: string|null, city: string|null, address: string|null}>,
     *     return_requests: list<array{id: int, order_id: int, type: string, reason: string, status: string, refund_amount: int, created_at: string}>
     * }
     */
    public function accountOverview(User $shopper): array
    {
        $this->current->getOrFail();

        $normalizedEmail = strtolower(trim($shopper->email));

        $customers = Customer::query()
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->get();

        $customerIds = $customers->pluck('id')->all();

        $orders = Order::query()
            ->with(['items', 'returnRequests'])
            ->where(function ($query) use ($normalizedEmail, $customerIds): void {
                $query->whereRaw('LOWER(email) = ?', [$normalizedEmail]);
                if ($customerIds !== []) {
                    $query->orWhereIn('customer_id', $customerIds);
                }
            })
            ->latest('id')
            ->get();

        $addresses = [];
        $seen = [];

        $appendAddress = static function (
            string $name,
            ?string $phone,
            ?string $governorate,
            ?string $city,
            ?string $address,
        ) use (&$addresses, &$seen): void {
            if (trim((string) $address) === '' && trim((string) $city) === '' && trim((string) $governorate) === '') {
                return;
            }

            $fingerprint = strtolower(implode('|', [
                trim((string) $governorate),
                trim((string) $city),
                trim((string) $address),
            ]));

            if (isset($seen[$fingerprint])) {
                return;
            }

            $seen[$fingerprint] = true;
            $addresses[] = [
                'name' => $name,
                'phone' => $phone,
                'governorate' => $governorate,
                'city' => $city,
                'address' => $address,
            ];
        };

        foreach ($customers as $customer) {
            $appendAddress($customer->name, $customer->phone, $customer->governorate, $customer->city, $customer->address);
        }

        foreach ($orders as $order) {
            $appendAddress($order->customer_name, $order->phone, $order->governorate, $order->city, $order->address);
        }

        $orderIds = $orders->pluck('id')->all();

        $returnRequests = $orderIds === []
            ? []
            : ReturnRequest::query()
                ->whereIn('order_id', $orderIds)
                ->latest('id')
                ->get()
                ->map(fn (ReturnRequest $return): array => [
                    'id' => $return->id,
                    'order_id' => $return->order_id,
                    'type' => $return->type,
                    'reason' => $return->reason,
                    'status' => $return->status,
                    'refund_amount' => $return->refund_amount,
                    'created_at' => $return->created_at->toIso8601String(),
                ])
                ->values()
                ->all();

        return [
            'orders' => $orders->map(fn (Order $order): array => $order->toRow())->values()->all(),
            'saved_addresses' => $addresses,
            'return_requests' => $returnRequests,
        ];
    }
}
