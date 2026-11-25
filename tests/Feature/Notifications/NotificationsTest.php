<?php

use App\Core\AuditLog\ActivityLog;
use App\Core\Permissions\Role;
use App\Core\Tenancy\CurrentStore;
use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Notifications\Http\Controllers\AlertController;
use App\Modules\Notifications\Mail\OrderConfirmationMail;
use App\Modules\Notifications\Mail\OrderShippedMail;
use App\Modules\Notifications\Mail\OwnerAlertMail;
use App\Modules\Notifications\Mail\TestDiagnosticMail;
use App\Modules\Notifications\Models\Alert;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Platform\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

test('FR-NTF-01 shopper receives branded order confirmation, lifecycle, and shipped emails with courier and tracking URL', function () {
    Mail::fake();
    [$owner, $store] = signedInMember();
    $store->update(['name' => 'Cairo Atelier', 'default_locale' => 'en']);

    app(CurrentStore::class)->runAs($store, function () use ($owner, $store) {
        $product = Product::factory()->live()->create(['name' => 'Linen Shirt', 'price' => 55000, 'stock' => 10]);
        $orders = app(OrderService::class);
        $notifications = app(NotificationService::class);

        $order = $orders->createManual([
            'customer_name' => 'Mona Adel',
            'phone' => '01001234567',
            'email' => 'mona@example.com',
            'governorate' => 'cairo',
            'channel' => 'website',
            'payment_method' => 'cod',
        ], [['product_id' => $product->id, 'quantity' => 1]], $owner);

        foreach (['confirmation', 'paid', 'cancelled', 'refunded', 'return_approved'] as $event) {
            expect($notifications->notifyShopperOrderEvent($order, $event, 'en'))->toBeTrue();
        }

        Mail::assertSent(OrderConfirmationMail::class, 5);
        Mail::assertSent(OrderConfirmationMail::class, function (OrderConfirmationMail $mail) use ($order, $store) {
            if ($mail->event !== 'confirmation') {
                return false;
            }
            $html = $mail->render();

            return $mail->hasTo('mona@example.com')
                && str_contains($html, $store->name)
                && str_contains($html, '#'.$order->number)
                && str_contains($html, 'Mona Adel')
                && str_contains($html, 'Linen Shirt');
        });

        $orders->updateFulfilment($order, 'Bosta', 'https://bosta.co/track/TRK123');
        $order = $order->fresh();

        expect($notifications->notifyShopperOrderEvent($order, 'shipped', 'en'))->toBeTrue();

        Mail::assertSent(OrderShippedMail::class, function (OrderShippedMail $mail) use ($order, $store) {
            $html = $mail->render();

            return $mail->hasTo('mona@example.com')
                && str_contains($html, $store->name)
                && str_contains($html, '#'.$order->number)
                && str_contains($html, 'Bosta')
                && str_contains($html, 'https://bosta.co/track/TRK123');
        });

        // Order without email returns false and sends nothing extra.
        $noEmailOrder = $orders->createManual([
            'customer_name' => 'Walk-in Shopper',
            'phone' => '01009998877',
            'email' => null,
            'governorate' => 'cairo',
            'channel' => 'phone',
            'payment_method' => 'cod',
        ], [['product_id' => $product->id, 'quantity' => 1]], $owner);

        expect($notifications->notifyShopperOrderEvent($noEmailOrder, 'confirmation'))->toBeFalse();
        Mail::assertSentCount(6);
    });
});

test('FR-NTF-02 shopper order emails render bilingual AR/EN copy and RTL/LTR direction based on locale', function () {
    Mail::fake();
    [$owner, $store] = signedInMember();
    $store->update(['name' => 'Nile Craft', 'default_locale' => 'ar']);

    app(CurrentStore::class)->runAs($store, function () use ($owner) {
        $product = Product::factory()->live()->create(['name' => 'Leather Bag', 'price' => 120000, 'stock' => 5]);
        $orders = app(OrderService::class);
        $notifications = app(NotificationService::class);

        $order = $orders->createManual([
            'customer_name' => 'أحمد علي',
            'phone' => '01112223344',
            'email' => 'ahmed@example.com',
            'governorate' => 'giza',
            'channel' => 'website',
            'payment_method' => 'instapay',
        ], [['product_id' => $product->id, 'quantity' => 1]], $owner);

        // Uses store default locale ('ar') when locale is null.
        expect($notifications->notifyShopperOrderEvent($order, 'paid'))->toBeTrue();
        // Explicit English override.
        expect($notifications->notifyShopperOrderEvent($order, 'return_approved', 'en'))->toBeTrue();

        Mail::assertSent(OrderConfirmationMail::class, function (OrderConfirmationMail $mail) {
            if ($mail->event !== 'paid') {
                return false;
            }
            $html = $mail->render();

            return $mail->mailLocale === 'ar'
                && str_contains($html, 'dir="rtl"')
                && str_contains($html, 'تم استلام الدفع بنجاح')
                && str_contains($html, 'ج.م');
        });

        Mail::assertSent(OrderConfirmationMail::class, function (OrderConfirmationMail $mail) {
            if ($mail->event !== 'return_approved') {
                return false;
            }
            $html = $mail->render();

            return $mail->mailLocale === 'en'
                && str_contains($html, 'dir="ltr"')
                && str_contains($html, 'Your return request is approved')
                && str_contains($html, 'EGP');
        });

        expect(fn () => $notifications->notifyShopperOrderEvent($order, 'unknown_event'))
            ->toThrow(InvalidArgumentException::class);
    });
});

test('FR-NTF-03 diagnostic test email includes store name, timestamp, sender diagnostics, and logs activity', function () {
    Mail::fake();
    [$owner, $store] = signedInMember();
    $store->update(['name' => 'Alexandria Goods']);

    app(CurrentStore::class)->runAs($store, function () use ($owner, $store) {
        $controller = app(AlertController::class);

        $request = Request::create('/dashboard/alerts/test-email', 'POST', [
            'email' => 'diag@example.com',
        ]);
        $request->setUserResolver(fn () => $owner);

        $response = $controller->sendTestEmail($request);
        expect($response->getStatusCode())->toBe(200)
            ->and($response->getData(true))->toBe([
                'status' => 'sent',
                'to' => 'diag@example.com',
            ]);

        Mail::assertSent(TestDiagnosticMail::class, function (TestDiagnosticMail $mail) use ($store, $owner) {
            $html = $mail->render();

            return $mail->hasTo('diag@example.com')
                && str_contains($html, $store->name)
                && str_contains($html, $mail->sentAt->toIso8601String())
                && str_contains($html, $mail->mailerDriver)
                && str_contains($html, $mail->fromAddress)
                && str_contains($html, $owner->email);
        });

        $log = ActivityLog::query()
            ->where('store_id', $store->id)
            ->where('action', 'notifications.diagnostic_email_sent')
            ->latest('id')
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->actor_id)->toBe($owner->id)
            ->and($log->properties['recipient'])->toBe('diag@example.com');
    });
});

test('FR-NTF-04 owner alerts persist store-scoped alerts, email only owners, and support listing and marking read', function () {
    Mail::fake();
    [$owner, $store] = signedInMember();
    $staff = User::factory()->create(['email' => 'staff@example.com']);
    $store->users()->attach($staff->id, ['role' => Role::Staff->value]);

    $otherOwner = User::factory()->create(['email' => 'other-owner@example.com']);
    $otherStore = Store::factory()->withMember($otherOwner, Role::Owner)->create();

    app(CurrentStore::class)->runAs($store, function () use ($owner, $staff, $store) {
        $notifications = app(NotificationService::class);
        $controller = app(AlertController::class);

        $alert1 = $notifications->notifyOwner(
            type: 'new_order',
            title: 'New order #1001',
            body: 'A new order was placed for 550.00 EGP.',
            meta: ['order_number' => 1001],
            severity: 'info',
            actionUrl: 'https://example.com/dashboard/orders/1',
            emailOwner: true,
        );

        $alert2 = $notifications->notifyOwner(
            type: 'low_stock',
            title: 'Low stock: Linen Shirt',
            body: 'Only 2 units remaining.',
            meta: ['remaining' => 2],
            severity: 'warning',
            actionUrl: null,
            emailOwner: false,
        );

        $alert3 = $notifications->notifyOwner(
            type: 'anomaly',
            title: 'Unusual refund spike',
            body: 'Refund rate exceeded threshold.',
            severity: 'critical',
            emailOwner: true,
        );

        expect($alert1->store_id)->toBe($store->id)
            ->and($alert1->isRead())->toBeFalse()
            ->and($alert2->severity)->toBe('warning')
            ->and($alert3->severity)->toBe('critical');

        // Only alert1 and alert3 sent emails, and only to owner (not staff).
        Mail::assertSent(OwnerAlertMail::class, 2);
        Mail::assertSent(OwnerAlertMail::class, function (OwnerAlertMail $mail) use ($owner) {
            $html = $mail->render();

            return $mail->hasTo($owner->email)
                && str_contains($html, 'New order #1001')
                && str_contains($html, 'https://example.com/dashboard/orders/1');
        });
        Mail::assertNotSent(OwnerAlertMail::class, fn (OwnerAlertMail $mail) => $mail->hasTo($staff->email));

        // Controller index lists alerts and unread count.
        $indexResponse = $controller->index(Request::create('/dashboard/alerts', 'GET'));
        expect($indexResponse->getStatusCode())->toBe(200)
            ->and($indexResponse->getData(true)['unread_count'])->toBe(3)
            ->and($indexResponse->getData(true)['alerts']['total'])->toBe(3);

        // Filter by type.
        $filtered = $controller->index(Request::create('/dashboard/alerts', 'GET', ['type' => 'low_stock']));
        expect($filtered->getData(true)['alerts']['total'])->toBe(1);

        // Mark read updates read_at and unread_count.
        $markResponse = $controller->markRead(Request::create("/dashboard/alerts/{$alert1->id}/read", 'POST'), $alert1->id);
        expect($markResponse->getStatusCode())->toBe(200)
            ->and($markResponse->getData(true)['alert']['read_at'])->not->toBeNull()
            ->and(Alert::query()->unread()->count())->toBe(2);

        // Filter unread only.
        $unreadOnly = $controller->index(Request::create('/dashboard/alerts', 'GET', ['unread' => '1']));
        expect($unreadOnly->getData(true)['alerts']['total'])->toBe(2);
    });

    // Store isolation: otherStore cannot see alerts from $store.
    app(CurrentStore::class)->runAs($otherStore, function () {
        expect(Alert::query()->count())->toBe(0);
    });
});

test('FR-NTF-05 click-to-WhatsApp URL normalizes Egyptian phone formats and prefills bilingual order updates', function () {
    [$owner, $store] = signedInMember();
    $store->update(['name' => 'Minivora Shop']);

    app(CurrentStore::class)->runAs($store, function () use ($owner) {
        $product = Product::factory()->live()->create(['name' => 'Ceramic Mug', 'price' => 25000, 'stock' => 10]);
        $orders = app(OrderService::class);
        $notifications = app(NotificationService::class);

        $order = $orders->createManual([
            'customer_name' => 'Karim Nabil',
            'phone' => '0101 234 5678',
            'email' => 'karim@example.com',
            'governorate' => 'cairo',
            'channel' => 'whatsapp',
            'payment_method' => 'cod',
        ], [['product_id' => $product->id, 'quantity' => 2]], $owner);

        $orders->updateFulfilment($order, 'Bosta', 'https://bosta.co/track/WA555');
        $orders->changeStatus($order, OrderStatus::Processing, $owner);
        $orders->changeStatus($order, OrderStatus::Shipped, $owner);
        $order = $order->fresh();

        $arUrl = $notifications->whatsappUrl($order, 'ar');
        expect($arUrl)->not->toBeNull()
            ->and($arUrl)->toStartWith('https://wa.me/201012345678?text=');

        $arText = rawurldecode(explode('?text=', $arUrl, 2)[1]);
        expect($arText)->toContain('Karim Nabil')
            ->and($arText)->toContain('#'.$order->number)
            ->and($arText)->toContain('Minivora Shop')
            ->and($arText)->toContain('تم الشحن')
            ->and($arText)->toContain('https://bosta.co/track/WA555');

        // Also test +20 format with English message.
        $order->update(['phone' => '+20 101 234 5678']);
        $enUrl = $notifications->whatsappUrl($order->fresh(), 'en');
        expect($enUrl)->toStartWith('https://wa.me/201012345678?text=');

        $enText = rawurldecode(explode('?text=', $enUrl, 2)[1]);
        expect($enText)->toContain('Hello Karim Nabil')
            ->and($enText)->toContain('#'.$order->number)
            ->and($enText)->toContain('Shipped')
            ->and($enText)->toContain('EGP');

        // 0020 prefix normalizes to 20...
        $order->update(['phone' => '00201012345678']);
        expect($notifications->whatsappUrl($order->fresh(), 'en'))->toStartWith('https://wa.me/201012345678?text=');

        // Missing phone returns null.
        $order->update(['phone' => null]);
        expect($notifications->whatsappUrl($order->fresh(), 'ar'))->toBeNull();
    });
});
