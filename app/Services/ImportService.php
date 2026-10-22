<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Bulk import from spreadsheets. Every row is validated first; if any row fails, nothing is saved.
 * Existing records are matched by SKU / code and updated, new ones are created.
 */
class ImportService
{
    public const MAX_ROWS = 10000;

    /** Column definitions per import type: heading => [rules, example value, help]. */
    public static function types(): array
    {
        return [
            'products' => [
                'title' => 'Products',
                'permission' => 'imports.manage',
                'help' => 'Matched by product code: existing products are updated, new codes are created. Categories, and brands under them, are created if they do not exist. Blank values keep the current value.',
                'columns' => [
                    'sku' => [['required', 'max:50'], 'BOS-0986-4521', 'Required. Unique item code.'],
                    'name' => [['required', 'max:255'], 'Oil filter - Toyota 1NZ', 'Required.'],
                    'part_number' => [['nullable', 'max:100'], '90915-YZZE1', 'OEM / manufacturer number.'],
                    'barcode' => [['nullable', 'max:100'], '', ''],
                    'location' => [['nullable', 'max:50'], 'A-03-2', 'Rack / bin.'],
                    'category' => [['nullable', 'max:255', 'required_with:brand'], '', 'Required when a brand is given.'],
                    'brand' => [['nullable', 'max:255'], 'Bosch', 'Created under the category if new.'],
                    'made_in' => [['nullable', 'max:60'], 'Germany', 'Country of origin.'],
                    'tax_rate' => [['nullable', 'numeric', 'min:0', 'max:100'], '18', 'Tax %.'],
                    'discount_percent' => [['nullable', 'numeric', 'min:0', 'max:100'], '5', 'Standard discount %.'],
                    'unit' => [['nullable', 'max:20'], 'pcs', 'Default pcs.'],
                    'cost_price' => [['nullable', 'numeric', 'min:0'], '650', ''],
                    'selling_price' => [['nullable', 'numeric', 'min:0'], '950', 'Retail price.'],
                    'wholesale_price' => [['nullable', 'numeric', 'min:0'], '850', 'Dealer price.'],
                    'min_price' => [['nullable', 'numeric', 'min:0'], '800', 'Lowest price cashiers may sell at.'],
                    'reorder_level' => [['nullable', 'integer', 'min:0'], '20', ''],
                    'description' => [['nullable', 'max:2000'], '', ''],
                    'active' => [['nullable', 'in:yes,no,y,n,1,0,true,false'], 'Yes', 'Yes / No. Default Yes.'],
                ],
            ],
            'customers' => [
                'title' => 'Customers',
                'permission' => 'customers.credit',
                'help' => 'Matched by customer code. Sales rep is matched by the user\'s email address.',
                'columns' => [
                    'code' => [['required', 'max:30'], 'C00010', 'Required. Unique.'],
                    'name' => [['required', 'max:255'], 'Perera Motor Spares', 'Required.'],
                    'contact_person' => [['nullable', 'max:255'], 'Mr. Perera', ''],
                    'phone' => [['nullable', 'max:50'], '0771234567', ''],
                    'email' => [['nullable', 'email', 'max:255'], '', ''],
                    'address' => [['nullable', 'max:255'], 'No. 12, Main Street', ''],
                    'city' => [['nullable', 'max:100'], 'Kurunegala', ''],
                    'tax_number' => [['nullable', 'max:50'], '', ''],
                    'credit_limit' => [['nullable', 'numeric', 'min:0'], '250000', '0 = no limit.'],
                    'payment_terms_days' => [['nullable', 'integer', 'min:0', 'max:365'], '30', 'Default 30.'],
                    'price_level' => [['nullable', 'in:wholesale,retail'], 'wholesale', 'wholesale or retail.'],
                    'sales_rep_email' => [['nullable', 'email'], '', 'Email of an existing user.'],
                    'active' => [['nullable', 'in:yes,no,y,n,1,0,true,false'], 'Yes', ''],
                ],
            ],
            'suppliers' => [
                'title' => 'Suppliers',
                'permission' => 'suppliers.manage',
                'help' => 'Matched by supplier code.',
                'columns' => [
                    'code' => [['required', 'max:30'], 'S010', 'Required. Unique.'],
                    'name' => [['required', 'max:255'], 'Global Parts Co.', 'Required.'],
                    'contact_person' => [['nullable', 'max:255'], '', ''],
                    'phone' => [['nullable', 'max:50'], '', ''],
                    'email' => [['nullable', 'email', 'max:255'], '', ''],
                    'address' => [['nullable', 'max:255'], '', ''],
                    'country' => [['nullable', 'max:100'], 'Japan', ''],
                    'active' => [['nullable', 'in:yes,no,y,n,1,0,true,false'], 'Yes', ''],
                ],
            ],
            'stock' => [
                'title' => 'Stock quantities',
                'permission' => 'stock.adjust',
                'help' => 'Opening stock or a stock count. Products must already exist. Each change is logged as a stock adjustment.',
                'columns' => [
                    'sku' => [['required'], 'BOS-0986-4521', 'Required. Existing product.'],
                    'warehouse' => [['required'], 'Main Store', 'Required. Warehouse name.'],
                    'quantity' => [['required', 'numeric'], '120', 'Counted quantity (or + / - change, depending on the option chosen).'],
                ],
            ],
        ];
    }

    public function __construct(private StockService $stock) {}

    /**
     * @param  array<int, array<string, string>>  $rows  keyed by spreadsheet row number
     * @return array{errors: array<int, string>, created: int, updated: int, unchanged: int}
     */
    public function import(string $type, array $rows, array $options = []): array
    {
        $definition = self::types()[$type];
        $result = ['errors' => [], 'created' => 0, 'updated' => 0, 'unchanged' => 0];

        if (! $rows) {
            $result['errors'][0] = 'The file has no data rows.';

            return $result;
        }
        if (count($rows) > self::MAX_ROWS) {
            $result['errors'][0] = 'Too many rows ('.count($rows).'). The limit is '.self::MAX_ROWS.' per file.';

            return $result;
        }

        $first = reset($rows);
        $missing = collect($definition['columns'])->filter(fn ($c) => in_array('required', $c[0]))->keys()->diff(array_keys($first));
        if ($missing->isNotEmpty()) {
            $result['errors'][1] = 'Missing column(s): '.$missing->join(', ').'. Download the template to see the expected headings.';

            return $result;
        }

        // 1. Validate every row and check for duplicates within the file.
        $rules = collect($definition['columns'])->map(fn ($c) => $c[0])->all();
        $keyColumn = array_key_first($definition['columns']);
        $seen = [];

        foreach ($rows as $line => $row) {
            $row = array_merge(array_fill_keys(array_keys($rules), ''), array_intersect_key($row, $rules));
            $row = array_map(fn ($v) => $v === '' ? null : $v, $row);
            if (isset($row['active'])) {
                $row['active'] = strtolower($row['active']);
            }
            $rows[$line] = $row;

            $validator = Validator::make($row, $rules);
            if ($validator->fails()) {
                $result['errors'][$line] = implode(' ', $validator->errors()->all());
                continue;
            }

            $dupKey = $type === 'stock' ? strtolower($row['sku'].'|'.$row['warehouse']) : strtolower($row[$keyColumn]);
            if (isset($seen[$dupKey])) {
                $result['errors'][$line] = "Duplicate of row {$seen[$dupKey]}.";
            }
            $seen[$dupKey] = $line;
        }

        if ($type === 'stock') {
            $this->checkStockReferences($rows, $result);
        }
        if ($type === 'customers') {
            $this->checkSalesReps($rows, $result);
        }
        if ($result['errors']) {
            return $result;
        }

        // 2. Everything valid: save in one transaction.
        DB::transaction(function () use ($type, $rows, $options, &$result) {
            foreach ($rows as $row) {
                $outcome = match ($type) {
                    'products' => $this->saveProduct($row),
                    'customers' => $this->saveCustomer($row),
                    'suppliers' => $this->saveSupplier($row),
                    'stock' => $this->saveStock($row, $options),
                };
                $result[$outcome]++;
            }
        });

        return $result;
    }

    private function saveProduct(array $row): string
    {
        $product = Product::firstOrNew(['sku' => $row['sku']]);
        $product->fill(array_filter([
            'name' => $row['name'],
            'part_number' => $row['part_number'],
            'barcode' => $row['barcode'],
            'location' => $row['location'],
            'wholesale_price' => $row['wholesale_price'],
            'min_price' => $row['min_price'],
            'category_id' => $categoryId = $row['category'] ? Category::firstOrCreate(['name' => $row['category']])->id : null,
            'brand_id' => $row['brand'] ? Brand::firstOrCreate(['name' => $row['brand'], 'category_id' => $categoryId])->id : null,
            'made_in' => $row['made_in'],
            'tax_rate' => $row['tax_rate'],
            'discount_percent' => $row['discount_percent'],
            'unit' => $row['unit'],
            'cost_price' => $row['cost_price'],
            'selling_price' => $row['selling_price'],
            'reorder_level' => $row['reorder_level'],
            'description' => $row['description'],
            'is_active' => $this->bool($row['active']),
        ], fn ($v) => $v !== null));

        if (! $product->exists) {
            $product->unit ??= 'pcs';
        }

        return $this->persist($product);
    }

    private function saveCustomer(array $row): string
    {
        $customer = Customer::firstOrNew(['code' => $row['code']]);
        $customer->fill(array_filter([
            'name' => $row['name'],
            'contact_person' => $row['contact_person'],
            'phone' => $row['phone'],
            'email' => $row['email'],
            'address' => $row['address'],
            'city' => $row['city'],
            'tax_number' => $row['tax_number'],
            'credit_limit' => $row['credit_limit'],
            'payment_terms_days' => $row['payment_terms_days'],
            'price_level' => $row['price_level'] ? strtolower($row['price_level']) : null,
            'sales_rep_id' => $row['sales_rep_email'] ? User::where('email', $row['sales_rep_email'])->value('id') : null,
            'is_active' => $this->bool($row['active']),
        ], fn ($v) => $v !== null));

        return $this->persist($customer);
    }

    private function saveSupplier(array $row): string
    {
        $supplier = Supplier::firstOrNew(['code' => $row['code']]);
        $supplier->fill(array_filter([
            'name' => $row['name'],
            'contact_person' => $row['contact_person'],
            'phone' => $row['phone'],
            'email' => $row['email'],
            'address' => $row['address'],
            'country' => $row['country'],
            'is_active' => $this->bool($row['active']),
        ], fn ($v) => $v !== null));

        return $this->persist($supplier);
    }

    private function saveStock(array $row, array $options): string
    {
        $productId = Product::where('sku', $row['sku'])->value('id');
        $warehouseId = Warehouse::where('name', $row['warehouse'])->value('id');
        $change = (float) $row['quantity'];

        if (($options['mode'] ?? 'set') === 'set') {
            $current = (float) StockLevel::where('product_id', $productId)->where('warehouse_id', $warehouseId)->value('quantity');
            $change = round($change - $current, 2);
        }

        if ($change == 0) {
            return 'unchanged';
        }

        $this->stock->move($productId, $warehouseId, $change, 'adjustment', null, $options['reason'] ?? 'Stock import', true);

        return 'updated';
    }

    private function persist($model): string
    {
        if (! $model->exists) {
            $model->save();

            return 'created';
        }
        if (! $model->isDirty()) {
            return 'unchanged';
        }
        $model->save();

        return 'updated';
    }

    private function checkStockReferences(array $rows, array &$result): void
    {
        $skus = Product::whereIn('sku', array_column($rows, 'sku'))->pluck('sku')->map(fn ($s) => strtolower($s))->flip();
        $warehouses = Warehouse::pluck('name')->map(fn ($n) => strtolower($n))->flip();

        foreach ($rows as $line => $row) {
            if (isset($result['errors'][$line])) {
                continue;
            }
            $problems = [];
            if (! isset($skus[strtolower($row['sku'])])) {
                $problems[] = "Unknown SKU \"{$row['sku']}\".";
            }
            if (! isset($warehouses[strtolower($row['warehouse'])])) {
                $problems[] = "Unknown warehouse \"{$row['warehouse']}\".";
            }
            if ($problems) {
                $result['errors'][$line] = implode(' ', $problems);
            }
        }
    }

    private function checkSalesReps(array $rows, array &$result): void
    {
        $emails = User::pluck('email')->map(fn ($e) => strtolower($e))->flip();

        foreach ($rows as $line => $row) {
            if (! isset($result['errors'][$line]) && $row['sales_rep_email'] && ! isset($emails[strtolower($row['sales_rep_email'])])) {
                $result['errors'][$line] = "No user with email \"{$row['sales_rep_email']}\".";
            }
        }
    }

    private function bool(?string $value): ?bool
    {
        return $value === null ? null : in_array($value, ['yes', 'y', '1', 'true'], true);
    }
}
