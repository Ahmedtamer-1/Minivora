<?php

use App\Core\Tenancy\BelongsToStore;
use App\Core\Tenancy\CurrentStore;
use App\Modules\Accounts\Models\User;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Every model that uses BelongsToStore, found by scanning app/. A new store-owned model is covered
 * automatically; a table with store_id but no model using the trait fails the second test.
 *
 * @return list<class-string<Model>>
 */
function storeOwnedModels(): array
{
    $models = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], Str::after($file->getPathname(), app_path().'/'));
        if (class_exists($class) && is_subclass_of($class, Model::class) && in_array(BelongsToStore::class, class_uses_recursive($class), true)) {
            $models[] = $class;
        }
    }

    sort($models);

    return $models;
}

test('NFR-SEC-05 no store-owned table can be read across stores', function () {
    $a = Store::factory()->withMember(User::factory()->create())->create();
    $b = Store::factory()->withMember(User::factory()->create())->create();
    $current = app(CurrentStore::class);

    $models = storeOwnedModels();
    expect($models)->not->toBeEmpty();

    foreach ($models as $class) {
        /** @var Model $model */
        $model = new $class;
        $table = $model->getTable();

        // Make sure each store has at least one row, using the raw table so the test does not
        // depend on each model's own creation rules.
        foreach ([$a, $b] as $store) {
            if (! DB::table($table)->where('store_id', $store->id)->exists()) {
                DB::table($table)->insert(isolationRow($table, $store->id));
            }
        }

        $current->runAs($a, function () use ($class, $a, $table) {
            $storeIds = $class::query()->pluck('store_id')->unique()->values()->all();
            expect($storeIds)->toBe([$a->id], "{$class} ({$table}) leaks rows from another store");
        });

        $current->set(null);
        expect($class::query()->count())->toBe(0, "{$class} returns rows with no store selected");
    }
});

test('NFR-SEC-05 every table with a store_id has a model that is scoped', function () {
    $scopedTables = array_map(fn ($class) => (new $class)->getTable(), storeOwnedModels());

    // Tables whose access goes through an explicitly store-filtered query instead of the trait.
    $exempt = ['activity_logs'];

    foreach (Schema::getTableListing() as $table) {
        $table = Str::afterLast($table, '.');
        if (Schema::hasColumn($table, 'store_id') && ! in_array($table, $exempt, true)) {
            expect($scopedTables)->toContain($table);
        }
    }
});

/** Minimal valid row for a store-owned table, built from its required columns. */
function isolationRow(string $table, int $storeId): array
{
    $row = ['store_id' => $storeId];

    // Required foreign keys point at a real row of the same store (created if there is none yet).
    foreach (Schema::getForeignKeys($table) as $fk) {
        $column = $fk['columns'][0];
        $parent = $fk['foreign_table'];
        if ($column === 'store_id' || $parent === $table || in_array($parent, ['users', 'stores'], true)) {
            continue;
        }
        $query = DB::table($parent);
        if (Schema::hasColumn($parent, 'store_id')) {
            $query->where('store_id', $storeId);
        }
        $row[$column] = $query->value('id') ?? DB::table($parent)->insertGetId(isolationRow($parent, $storeId));
    }

    foreach (Schema::getColumns($table) as $column) {
        $name = $column['name'];
        if ($name === 'id' || array_key_exists($name, $row) || $column['nullable'] || $column['default'] !== null) {
            continue;
        }
        $type = strtolower($column['type_name']);
        $row[$name] = match (true) {
            $name === 'user_id' => User::factory()->create()->id,
            $name === 'role' => 'staff',
            str_contains($type, 'int') => 1,
            str_contains($type, 'date') || str_contains($type, 'time') => now(),
            str_contains($type, 'bool') => false,
            default => Str::random(12),
        };
    }

    return $row + ['created_at' => now(), 'updated_at' => now()];
}
