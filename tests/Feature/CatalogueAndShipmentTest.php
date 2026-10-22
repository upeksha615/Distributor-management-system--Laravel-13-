<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\StockLevel;
use App\Services\SalesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogueAndShipmentTest extends TestCase
{
    use RefreshDatabase;

    private function stockOf(Product $p): float
    {
        return (float) StockLevel::where('product_id', $p->id)->sum('quantity');
    }

    public function test_category_brand_product_hierarchy(): void
    {
        $manager = $this->user('inventory_manager');
        $catA = Category::create(['name' => 'Category A']);
        $catB = Category::create(['name' => 'Category B']);

        $this->actingAs($manager)->post('/lookups/brands', ['category_id' => $catA->id, 'name' => 'Acme'])->assertSessionHas('success');
        $this->actingAs($manager)->post('/lookups/brands', ['category_id' => $catA->id, 'name' => 'Acme'])->assertSessionHasErrors('name');
        $this->actingAs($manager)->post('/lookups/brands', ['category_id' => $catB->id, 'name' => 'Acme'])->assertSessionHas('success'); // same name, other category
        $this->actingAs($manager)->post('/lookups/brands', ['name' => 'NoCat'])->assertSessionHasErrors('category_id');

        $brand = Brand::where('category_id', $catA->id)->first();
        $this->actingAs($manager)->post('/products', [
            'sku' => 'HX-100', 'name' => 'Clutch plate', 'brand_id' => $brand->id, 'category_id' => $catB->id, 'unit' => 'pcs',
            'cost_price' => 800, 'selling_price' => 1200, 'reorder_level' => 5, 'made_in' => 'Thailand', 'tax_rate' => 18, 'discount_percent' => 5, 'is_active' => 1,
        ])->assertRedirect();
        $product = Product::where('sku', 'HX-100')->first();
        $this->assertSame($catA->id, $product->category_id, 'category follows the brand');
        $this->assertSame('Thailand', $product->made_in);
        $this->assertEquals(18, $product->tax_rate);

        // Moving the brand moves its products.
        $this->actingAs($manager)->put("/lookups/brands/{$brand->id}", ['category_id' => $catB->id, 'name' => 'Acme Moved'])->assertSessionHas('success');
        $this->assertSame($catB->id, $product->fresh()->category_id);
    }

    public function test_stock_colours(): void
    {
        $this->assertSame('out', Product::stockLevel(0));
        $this->assertSame('out', Product::stockLevel(-2));
        $this->assertSame('low', Product::stockLevel(1));
        $this->assertSame('low', Product::stockLevel(10));
        $this->assertSame('ok', Product::stockLevel(11));
        config(['distributor.stock_low_threshold' => 25]);
        $this->assertSame('low', Product::stockLevel(20));

        $none = $this->product(0);
        $low = $this->product(4);
        $plenty = $this->product(50);
        $rep = $this->user('sales_rep');
        $this->actingAs($rep)->get('/products?stock=low')->assertSee($low->sku)->assertDontSee($plenty->sku)->assertDontSee($none->sku);
        $this->actingAs($rep)->get('/products?stock=out')->assertSee($none->sku)->assertDontSee($low->sku);
        $this->actingAs($rep)->get('/products')->assertSee('bg-red-100', false)->assertSee('bg-yellow-100', false)->assertSee('bg-green-100', false);
        $this->actingAs($rep)->getJson('/products/lookup?q='.$low->sku)->assertJsonPath('0.stock_level', 'low');
    }

    public function test_shipment_upload_is_pending_and_received_into_stock(): void
    {
        $a = $this->product(5);
        $b = $this->product(0);
        $store = $this->user('inventory_manager');
        $csv = UploadedFile::fake()->createWithContent('ship.csv', "product_code,quantity,unit_cost\n{$a->sku},40,700\n{$b->sku},12,\n{$a->sku},10,\n");

        $this->actingAs($store)->post('/shipments', ['warehouse_id' => $this->warehouse()->id, 'reference' => 'CONT-7788',
            'expected_date' => today()->addDays(20)->toDateString(), 'file' => $csv])->assertRedirect();

        $shipment = Shipment::first();
        $this->assertSame('pending', $shipment->status);
        $this->assertEquals(50, $shipment->items()->where('product_id', $a->id)->value('quantity'), 'duplicate codes are added together');
        $this->assertEquals(5, $this->stockOf($a), 'upload must not change stock');
        $this->assertEquals(0, $this->stockOf($b));

        // Staff with stock.view_qty see the numbers; reps see only that a shipment is coming.
        $rep = $this->user('sales_rep');
        $this->actingAs($store)->get('/products?stock=incoming')->assertSee($a->sku)->assertSee('⛴ 50', false);
        $this->actingAs($store)->getJson('/products/lookup?q='.$b->sku)->assertJsonPath('0.incoming', 12);
        $this->actingAs($rep)->get('/products?stock=incoming')->assertSee($a->sku)->assertSee('⛴ Coming', false)->assertDontSee('⛴ 50', false);
        $this->actingAs($rep)->getJson('/products/lookup?q='.$b->sku)->assertJsonPath('0.incoming', true)->assertJsonPath('0.stock', null);
        $this->actingAs($rep)->get("/shipments/{$shipment->id}")->assertOk();
        $this->actingAs($rep)->post('/shipments', [])->assertForbidden();

        // Partial receipt.
        $itemA = $shipment->items()->where('product_id', $a->id)->first();
        $itemB = $shipment->items()->where('product_id', $b->id)->first();
        $this->actingAs($store)->post("/shipments/{$shipment->id}/receive", ['quantities' => [$itemA->id => 30, $itemB->id => 0]])->assertSessionHas('success');
        $this->assertEquals(35, $this->stockOf($a));
        $this->assertSame('partial', $shipment->fresh()->status);
        $this->assertEquals(700, $a->fresh()->cost_price);
        $this->actingAs($store)->getJson('/products/lookup?q='.$a->sku)->assertJsonPath('0.incoming', 20);

        // Cannot receive more than expected.
        $this->actingAs($store)->post("/shipments/{$shipment->id}/receive", ['quantities' => [$itemA->id => 25]])->assertSessionHasErrors();

        $this->actingAs($store)->post("/shipments/{$shipment->id}/receive", ['quantities' => [$itemA->id => 20, $itemB->id => 12]]);
        $this->assertSame('received', $shipment->fresh()->status);
        $this->assertEquals(55, $this->stockOf($a));
        $this->assertEquals(12, $this->stockOf($b));
        $this->actingAs($store)->getJson('/products/lookup?q='.$a->sku)->assertJsonPath('0.incoming', 0);
        $this->actingAs($rep)->getJson('/products/lookup?q='.$a->sku)->assertJsonPath('0.incoming', false);
        $this->assertTrue(\App\Models\StockMovement::where('type', 'shipment')->where('product_id', $a->id)->count() === 2);
    }

    public function test_reps_see_availability_colours_but_never_quantities(): void
    {
        $p = $this->product(37);
        $rep = $this->user('sales_rep');
        $manager = $this->user('general_manager');

        $this->actingAs($rep)->get('/products')->assertSee('In stock')->assertDontSee('>37<', false)->assertDontSee(' 37', false);
        $this->actingAs($manager)->get('/products')->assertSee('37');
        $this->actingAs($rep)->get("/products/{$p->id}")->assertOk()->assertDontSee('Stock movements')->assertDontSee('37 pcs');
        $this->actingAs($manager)->get("/products/{$p->id}")->assertSee('37 pcs')->assertSee('Stock movements');
        $this->actingAs($rep)->getJson('/products/lookup?q='.$p->sku)->assertJsonPath('0.stock', null)->assertJsonPath('0.stock_level', 'ok');
        $this->actingAs($rep)->get('/stock')->assertForbidden();
        $this->actingAs($rep)->get('/stock/movements')->assertForbidden();
    }

    public function test_shipment_file_with_unknown_codes_saves_nothing(): void
    {
        $a = $this->product(0);
        $csv = UploadedFile::fake()->createWithContent('bad.csv', "product_code,quantity\n{$a->sku},5\nNOPE-1,3\n{$a->sku},abc\n");
        $this->actingAs($this->user('inventory_manager'))->post('/shipments', ['warehouse_id' => $this->warehouse()->id, 'file' => $csv])
            ->assertSessionHas('shipment_errors', fn ($e) => isset($e[3]) && str_contains($e[3], 'NOPE-1') && isset($e[4]));
        $this->assertSame(0, Shipment::count());
    }

    public function test_tax_is_charged_per_product_after_bill_discount(): void
    {
        $taxed = $this->product(10, 1000);
        $taxed->update(['tax_rate' => 18]);
        $exempt = $this->product(10, 500);
        $this->actingAs($this->user('general_manager'));

        $invoice = app(SalesService::class)->createInvoice([
            'customer_id' => $this->dealer()->id, 'warehouse_id' => $this->warehouse()->id, 'invoice_date' => today()->toDateString(), 'discount' => 150,
            'items' => [
                ['product_id' => $taxed->id, 'quantity' => 1, 'unit_price' => 1000, 'tax_percent' => 18],
                ['product_id' => $exempt->id, 'quantity' => 1, 'unit_price' => 500, 'tax_percent' => 0],
            ],
        ]);
        // Subtotal 1500, discount 150 => ratio 0.9; tax = 1000 * 0.9 * 18% = 162.
        $this->assertEquals(1500, $invoice->subtotal);
        $this->assertEquals(162, $invoice->tax);
        $this->assertEquals(1512, $invoice->total);
        $this->assertEquals(162, $invoice->items()->where('product_id', $taxed->id)->value('tax_amount'));

        // Returning the taxed item credits 1000 * 0.9 * 1.18 = 1062.
        $item = $invoice->items()->where('product_id', $taxed->id)->first();
        $note = app(SalesService::class)->createCreditNote($invoice, [$item->id => 1], ['credit_date' => today()->toDateString(), 'reason' => 'Damaged']);
        $this->assertEquals(1062, $note->total);
    }

    public function test_product_picture_upload_and_access(): void
    {
        Storage::fake('local');
        $p = $this->product(0);
        $manager = $this->user('inventory_manager');
        $this->actingAs($manager)->put("/products/{$p->id}", array_merge($p->only(['sku', 'name', 'unit', 'cost_price', 'selling_price', 'reorder_level']), [
            'image' => UploadedFile::fake()->create('part.png', 50, 'image/png'), 'is_active' => 1,
        ]))->assertRedirect();
        $path = $p->fresh()->image_path;
        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);

        $this->actingAs($this->user('sales_rep'))->get("/products/{$p->id}/image")->assertOk();
        $this->post('/logout');
        $this->get("/products/{$p->id}/image")->assertRedirect('/login');

        $this->actingAs($manager)->put("/products/{$p->id}", array_merge($p->only(['sku', 'name', 'unit', 'cost_price', 'selling_price', 'reorder_level']), [
            'image' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream'),
        ]))->assertSessionHasErrors('image');
    }
}
