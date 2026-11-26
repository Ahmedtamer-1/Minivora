<?php

use Illuminate\Support\Facades\Storage;

test('uploaded images load even when the host could not create the public/storage link', function () {
    Storage::fake('public');
    Storage::disk('public')->put('products/bag-640w.webp', 'image-bytes');

    $this->get('/storage/products/bag-640w.webp')
        ->assertOk()
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');

    $this->get('/storage/products/missing.webp')->assertNotFound();
    $this->get('/storage/../.env')->assertNotFound();
    $this->get('/storage/products/..%2F..%2F.env')->assertNotFound();
});
