<?php

namespace App\Modules\Catalog\Services;

use App\Core\AuditLog\ActivityLogger;
use App\Core\Money\Piasters;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Create, update, publish and remove products; store their photos. The dashboard and (later) the AI
 * team both go through here. FR-CAT-01
 */
class ProductService
{
    public const MAX_IMAGES = 10;

    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly ProductNoteParser $parser,
    ) {}

    /**
     * Start a draft from photos, a short note, or nothing ("fill it in myself").
     *
     * @param  list<UploadedFile>  $photos
     */
    public function startDraft(array $photos = [], ?string $note = null): Product
    {
        $fields = ['source' => 'manual'];

        if ($note !== null && trim($note) !== '') {
            $parsed = $this->parser->parse($note);
            $fields = [
                'name' => $parsed['name'],
                'price' => $parsed['price'] ?? 0,
                'stock' => $parsed['stock'] ?? 0,
                'source' => 'note',
            ];
        } elseif ($photos !== []) {
            $fields['source'] = 'photos';
        }

        return DB::transaction(function () use ($fields, $photos) {
            $product = Product::query()->create($fields);
            if ($photos !== []) {
                $product->images = $this->storePhotos($product, $photos);
                $product->save();
            }
            $this->activity->log('product.created', $product, ['source' => $product->source]);

            return $product;
        });
    }

    /**
     * @param  array<string, mixed>  $fields  validated, money already in piasters
     * @param  list<string>  $keepImages  existing paths to keep, in display order
     * @param  list<UploadedFile>  $newPhotos
     */
    public function update(Product $product, array $fields, array $keepImages, array $newPhotos = []): Product
    {
        $current = $product->images ?? [];
        $kept = array_values(array_intersect($keepImages, $current));
        $removed = array_diff($current, $kept);

        if (count($kept) + count($newPhotos) > self::MAX_IMAGES) {
            throw ValidationException::withMessages(['photos' => __('validation.max.array', ['attribute' => 'photos', 'max' => self::MAX_IMAGES])]);
        }

        $priceChanged = array_key_exists('price', $fields) && $fields['price'] !== $product->price;
        $oldPrice = $product->price;

        $product->fill($fields);
        $product->images = [...$kept, ...$this->storePhotos($product, $newPhotos)];
        $product->save();

        Storage::disk('public')->delete(array_values($removed));

        if ($priceChanged && $product->isLive()) {
            $this->activity->log('product.price_changed', $product, ['from' => $oldPrice, 'to' => $product->price]);
        }

        return $product;
    }

    public function publish(Product $product): Product
    {
        if (trim($product->name) === '' || $product->price <= 0) {
            throw ValidationException::withMessages(['publish' => __('products.publish_needs')]);
        }

        $product->status = ProductStatus::Live;
        $product->published_at ??= Carbon::now();
        $product->save();
        $this->activity->log('product.published', $product);

        return $product;
    }

    public function unpublish(Product $product): Product
    {
        $product->status = ProductStatus::Draft;
        $product->save();
        $this->activity->log('product.unpublished', $product);

        return $product;
    }

    public function delete(Product $product): void
    {
        Storage::disk('public')->delete($product->images ?? []);
        $this->activity->log('product.deleted', $product, ['name' => $product->name]);
        $product->delete();
    }

    /**
     * Import drafts from a CSV with a header row: name, price (pounds), stock, and optional
     * name_ar, category, description. Returns how many rows became drafts.
     */
    public function importCsv(UploadedFile $file): int
    {
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return 0;
        }

        $header = fgetcsv($handle) ?: [];
        $header = array_map(fn ($h) => strtolower(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
        if (! in_array('name', $header, true)) {
            fclose($handle);
            throw ValidationException::withMessages(['csv' => __('products.csv_needs_name')]);
        }

        $count = 0;
        DB::transaction(function () use ($handle, $header, &$count) {
            while (($row = fgetcsv($handle)) !== false && $count < 1000) {
                $data = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), null));
                $name = trim((string) ($data['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $price = 0;
                try {
                    $price = isset($data['price']) && trim((string) $data['price']) !== ''
                        ? Piasters::fromPounds((string) $data['price'])->amount : 0;
                } catch (InvalidArgumentException) {
                    $price = 0;
                }
                Product::query()->create([
                    'name' => mb_substr($name, 0, 255),
                    'name_ar' => $this->nullable($data['name_ar'] ?? null),
                    'category' => $this->nullable($data['category'] ?? null, 80),
                    'description' => $this->nullable($data['description'] ?? null, 5000),
                    'price' => max(0, $price),
                    'stock' => max(0, (int) ($data['stock'] ?? 0)),
                    'source' => 'csv',
                ]);
                $count++;
            }
        });
        fclose($handle);

        $this->activity->log('product.imported', null, ['count' => $count]);

        return $count;
    }

    /**
     * @param  list<UploadedFile>  $photos
     * @return list<string>
     */
    private function storePhotos(Product $product, array $photos): array
    {
        return array_values(array_map(
            fn (UploadedFile $photo) => $photo->store("stores/{$product->store_id}/products/{$product->id}", 'public'),
            array_slice($photos, 0, self::MAX_IMAGES),
        ));
    }

    private function nullable(mixed $value, int $max = 255): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
