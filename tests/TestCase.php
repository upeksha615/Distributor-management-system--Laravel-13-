<?php

namespace Tests;

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

/**
 * Tests run against the MySQL database `distributor_test` (see phpunit.xml), never the live database.
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('settings');
    }

    protected function user(string $role = 'super_admin', array $attributes = []): User
    {
        Permissions::sync();

        return User::create($attributes + [
            'name' => ucfirst(str_replace('_', ' ', $role)).' '.uniqid(),
            'email' => uniqid($role).'@test.local',
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    protected function dealer(array $attributes = []): Customer
    {
        return Customer::create($attributes + [
            'code' => 'T'.strtoupper(substr(uniqid(), -6)),
            'name' => 'Test Dealer '.uniqid(),
            'credit_limit' => 0,
            'payment_terms_days' => 30,
            'price_level' => 'wholesale',
        ]);
    }

    protected function warehouse(): Warehouse
    {
        return Warehouse::firstOrCreate(['name' => 'Test Store'], ['is_active' => true]);
    }

    /** A product with stock in the test warehouse. */
    protected function product(float $stock = 100, float $price = 1000, float $cost = 600): Product
    {
        $product = Product::create([
            'sku' => 'P'.strtoupper(substr(uniqid(), -7)),
            'name' => 'Test part '.uniqid(),
            'unit' => 'pcs',
            'cost_price' => $cost,
            'selling_price' => $price,
            'wholesale_price' => $price * 0.9,
            'reorder_level' => 0,
        ]);
        if ($stock > 0) {
            $this->actingAs($this->user());
            app(\App\Services\StockService::class)->move($product->id, $this->warehouse()->id, $stock, 'adjustment', null, 'Test opening stock');
        }

        return $product;
    }
}
