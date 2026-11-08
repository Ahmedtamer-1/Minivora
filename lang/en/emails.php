<?php

return [
    'invitation' => [
        'subject' => 'You are invited to :store on Minivora',
        'body' => 'You have been invited to help run :store as :role.',
        'action' => 'Accept invitation',
        'expiry' => 'This link expires in 7 days.',
    ],
    'order_paid' => [
        'subject' => 'Order #:number from :store is confirmed',
        'body' => 'We received your payment for order #:number. :store is preparing it now.',
    ],
    'order_shipped' => [
        'subject' => 'Order #:number from :store is on its way',
        'body' => 'Your order #:number has left :store and is with the courier.',
        'action' => 'Track your order',
    ],
    'order_cancelled' => [
        'subject' => 'Order #:number from :store was cancelled',
        'body' => 'Your order #:number from :store was cancelled. Reply to this email if that is a mistake.',
    ],
];
