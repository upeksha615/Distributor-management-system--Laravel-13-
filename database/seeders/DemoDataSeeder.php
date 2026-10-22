<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PaymentService;
use App\Services\PurchaseService;
use App\Services\SalesService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Sample data for trying the system out: php artisan db:seed --class=DemoDataSeeder
 * Transactions go through the real services so stock and balances are consistent.
 */
class DemoDataSeeder extends Seeder
{
    public function run(PurchaseService $purchases, SalesService $sales, PaymentService $payments): void
    {
        $this->call(DatabaseSeeder::class);
        Auth::setUser(User::where('role', 'super_admin')->first());

        $users = [
            ['Nimal Perera', 'manager@example.com', 'general_manager'],
            ['Kasun Silva', 'sales@example.com', 'sales_rep'],
            ['Ruwan Fernando', 'store@example.com', 'inventory_manager'],
            ['Sachini Wijesinghe', 'office@example.com', 'office_admin'],
            ['Pradeep Gunawardena', 'accounts@example.com', 'accountant'],
            ['Chamari Dissanayake', 'finance@example.com', 'finance_manager'],
            ['Nuwan Bandara', 'purchasing@example.com', 'purchasing_officer'],
            ['Saman Kumara', 'driver@example.com', 'delivery_officer'],
            ['Lahiru Rathnayake', 'warehouse@example.com', 'warehouse_staff'],
            ['Audit Viewer', 'auditor@example.com', 'auditor'],
        ];
        foreach ($users as [$name, $email, $role]) {
            User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => 'password', 'role' => $role]);
        }
        $rep = User::where('role', 'sales_rep')->first();

        $main = Warehouse::first();
        $branch = Warehouse::firstOrCreate(['name' => 'Kandy Branch'], ['location' => 'Kandy']);

        $categories = collect(['Engine Parts', 'Brake System', 'Suspension', 'Filters', 'Electrical', 'Lubricants'])
            ->mapWithKeys(fn ($n) => [$n => Category::firstOrCreate(['name' => $n])->id]);
        $brands = collect(['Bosch', 'Denso', 'NGK', 'Mobil', 'KYB', 'Brembo'])
            ->mapWithKeys(fn ($n) => [$n => Brand::firstOrCreate(['name' => $n])->id]);

        $products = [
            ['BOS-0986-4521', 'Oil filter - Toyota 1NZ', 'Filters', 'Bosch', 650, 950, 20],
            ['BOS-1457-4337', 'Air filter - Honda Fit GP5', 'Filters', 'Bosch', 1100, 1650, 15],
            ['DEN-1A23-0067', 'Spark plug iridium IK20', 'Electrical', 'Denso', 1450, 2200, 40],
            ['NGK-BKR6E-11', 'Spark plug BKR6E-11', 'Electrical', 'NGK', 520, 850, 50],
            ['MOB-5W30-4L', 'Mobil Super 5W-30 4L', 'Lubricants', 'Mobil', 7800, 9900, 24],
            ['KYB-334-118', 'Shock absorber front - Vitz', 'Suspension', 'KYB', 9200, 13500, 6],
            ['BRE-P83-024', 'Brake pad set front - Axio', 'Brake System', 'Brembo', 5400, 7900, 10],
            ['DEN-ALT-101', 'Alternator 12V 80A rebuilt', 'Electrical', 'Denso', 24500, 34000, 2],
            ['BOS-BELT-6PK', 'Drive belt 6PK1220', 'Engine Parts', 'Bosch', 1800, 2750, 8],
            ['BRE-DISC-09A', 'Brake disc front - Prius', 'Brake System', 'Brembo', 8700, 12500, 4],
        ];
        foreach ($products as [$sku, $name, $cat, $brand, $cost, $price, $reorder]) {
            Product::firstOrCreate(['sku' => $sku], [
                'name' => $name, 'category_id' => $categories[$cat], 'brand_id' => $brands[$brand],
                'unit' => str_contains($name, '4L') ? 'can' : 'pcs', 'cost_price' => $cost, 'selling_price' => $price, 'reorder_level' => $reorder,
            ]);
        }

        // Spare-parts data: OEM numbers, barcodes, bins, dealer prices, cross references and vehicles.
        $partsData = [
            'BOS-0986-4521' => ['90915-YZZE1', '4047024123456', 'A-01-1', 850, 800, [['W 68/3', 'alternate', 'Mann'], ['C-110', 'alternate', 'Sakura']], [['Toyota', 'Axio NZE141', 2006, 2012, '1NZ-FE'], ['Toyota', 'Vitz NCP91', 2005, 2010, '1NZ-FE'], ['Toyota', 'Premio NZT240', 2001, 2007, '1NZ-FE']]],
            'BOS-1457-4337' => ['17220-RB0-000', '4047024223457', 'A-01-2', 1450, 1350, [], [['Honda', 'Fit GP5', 2013, 2020, 'LEB'], ['Honda', 'Vezel RU3', 2013, 2021, 'LEB']]],
            'DEN-1A23-0067' => ['90919-01253', '4987654321001', 'B-02-1', 1950, 1800, [['IK20TT', 'alternate', 'Denso']], [['Toyota', 'Aqua NHP10', 2011, 2021, '1NZ-FXE'], ['Toyota', 'Prius ZVW30', 2009, 2015, '2ZR-FXE']]],
            'NGK-BKR6E-11' => ['22401-50Y05', '4548916234510', 'B-02-2', 720, 650, [['K20PR-U11', 'alternate', 'Denso']], [['Nissan', 'Sunny B15', 1998, 2005, 'QG15DE'], ['Suzuki', 'Alto', 2009, 2014, 'K10B']]],
            'MOB-5W30-4L' => [null, '5420000123459', 'D-01-1', 9200, 9000, [], []],
            'KYB-334-118' => ['48510-59265', '4909785118009', 'C-03-1', 12200, 11500, [['334118', 'supplier', 'KYB']], [['Toyota', 'Vitz SCP90', 2005, 2010, '2SZ-FE']]],
            'BRE-P83-024' => ['04465-12592', '8020584083024', 'C-01-4', 6900, 6500, [['D2222', 'alternate', 'Akebono'], ['AN-690WK', 'alternate', 'Akebono']], [['Toyota', 'Axio NZE161', 2012, 2018, '1NZ-FE'], ['Toyota', 'Fielder NZE161', 2012, 2018, '1NZ-FE']]],
            'DEN-ALT-101' => ['27060-21180', null, 'E-02-1', 31000, 29500, [], [['Toyota', 'Corolla NZE121', 2001, 2007, '1NZ-FE']]],
            'BOS-BELT-6PK' => ['90916-02500', '4047024654321', 'A-04-3', 2400, 2200, [['6PK1220', 'alternate', 'Gates']], [['Toyota', 'Premio NZT260', 2007, 2016, '1NZ-FE']]],
            'BRE-DISC-09A' => ['43512-47040', '8020584009001', 'C-02-2', 11200, 10500, [], [['Toyota', 'Prius ZVW30', 2009, 2015, '2ZR-FXE']]],
        ];
        foreach ($partsData as $sku => [$oem, $barcode, $bin, $wholesale, $min, $refs, $vehicles]) {
            $p = Product::where('sku', $sku)->first();
            if (! $p || $p->partNumbers()->exists() || $p->fitments()->exists()) {
                continue;
            }
            $p->update(['part_number' => $oem, 'barcode' => $barcode, 'location' => $bin, 'wholesale_price' => $wholesale, 'min_price' => $min]);
            foreach ($refs as [$number, $type, $refBrand]) {
                $p->partNumbers()->create(['number' => $number, 'type' => $type, 'brand' => $refBrand]);
            }
            foreach ($vehicles as [$make, $model, $from, $to, $engine]) {
                $p->fitments()->create(['make' => $make, 'model' => $model, 'year_from' => $from, 'year_to' => $to, 'engine' => $engine]);
            }
        }
        $supplier = Supplier::firstOrCreate(['code' => 'S001'], ['name' => 'Asia Auto Parts Trading', 'country' => 'UAE', 'phone' => '+971 4 555 0101']);
        Supplier::firstOrCreate(['code' => 'S002'], ['name' => 'Lanka Lubricants (Pvt) Ltd', 'country' => 'Sri Lanka', 'phone' => '011 255 0202']);

        // Country of origin for the demo parts, and one shipment on the way (shows in the reps' Ship column).
        foreach (['BOS-0986-4521' => 'Germany', 'BOS-1457-4337' => 'Germany', 'DEN-1A23-0067' => 'Japan', 'NGK-BKR6E-11' => 'Japan', 'MOB-5W30-4L' => 'Singapore',
            'KYB-334-118' => 'Japan', 'BRE-P83-024' => 'Italy', 'DEN-ALT-101' => 'Japan', 'BOS-BELT-6PK' => 'India', 'BRE-DISC-09A' => 'Italy'] as $sku => $origin) {
            Product::where('sku', $sku)->whereNull('made_in')->update(['made_in' => $origin]);
        }
        if (! \App\Models\Shipment::exists()) {
            app(\App\Services\ShipmentService::class)->create(
                ['reference' => 'DEMO-CONTAINER-01', 'warehouse_id' => Warehouse::first()->id, 'expected_date' => today()->addDays(18)->toDateString(),
                    'supplier_id' => Supplier::first()?->id, 'notes' => 'Demo shipment'],
                Product::whereIn('sku', ['DEN-ALT-101', 'KYB-334-118', 'BRE-DISC-09A'])->get()->map(fn ($p) => ['product_id' => $p->id, 'quantity' => 24, 'unit_cost' => null])->all(),
            );
        }

        $customers = [
            ['C00001', 'Sunil Motor Stores', 'Colombo 10', 500000, 30],
            ['C00002', 'Kandy Auto Spares', 'Kandy', 300000, 45],
            ['C00003', 'Gamage Service Centre', 'Galle', 150000, 30],
            ['C00004', 'Jaffna Motors', 'Jaffna', 0, 14],
        ];
        foreach ($customers as [$code, $name, $city, $limit, $terms]) {
            Customer::firstOrCreate(['code' => $code], [
                'name' => $name, 'city' => $city, 'credit_limit' => $limit, 'payment_terms_days' => $terms,
                'phone' => '07'.random_int(10000000, 99999999), 'sales_rep_id' => $rep->id,
            ]);
        }

        if (\App\Models\PurchaseOrder::exists()) {
            return; // transactions already seeded
        }

        // Purchase every product into the main store and receive it in full.
        $po = $purchases->save([
            'supplier_id' => $supplier->id, 'warehouse_id' => $main->id, 'order_date' => today()->subDays(75),
            'items' => Product::all()->map(fn ($p) => ['product_id' => $p->id, 'quantity' => max(60, $p->reorder_level * 5), 'unit_cost' => $p->cost_price])->all(),
        ]);
        $po->update(['status' => 'ordered']);
        $purchases->receive($po, $po->items->mapWithKeys(fn ($i) => [$i->id => $i->quantity])->all(), [
            'received_date' => today()->subDays(70), 'supplier_invoice_no' => 'AAP-88231',
        ]);

        // A second PO left open for receiving.
        $po2 = $purchases->save([
            'supplier_id' => $supplier->id, 'warehouse_id' => $branch->id, 'order_date' => today()->subDays(3), 'expected_date' => today()->addDays(10),
            'items' => Product::limit(3)->get()->map(fn ($p) => ['product_id' => $p->id, 'quantity' => 30, 'unit_cost' => $p->cost_price])->all(),
        ]);
        $po2->update(['status' => 'ordered']);

        // Invoices spread over the last few months so the aged debtors report has data.
        $all = Product::all();
        $invoiceAges = [65, 50, 40, 35, 20, 12, 5, 1];
        foreach ($invoiceAges as $n => $daysAgo) {
            $customer = Customer::find(($n % 4) + 1);
            $lines = $all->random(3)->map(fn ($p) => ['product_id' => $p->id, 'quantity' => random_int(2, 6), 'unit_price' => $p->selling_price, 'discount_percent' => $n % 2 ? 5 : 0])->all();
            $sales->createInvoice([
                'customer_id' => $customer->id, 'warehouse_id' => $main->id,
                'invoice_date' => today()->subDays($daysAgo)->toDateString(), 'items' => $lines, 'tax_percent' => 0,
            ], null, true);
        }

        // Some payments, including a returned cheque.
        $payments->record(Customer::find(1), ['payment_date' => today()->subDays(30), 'method' => 'cash', 'amount' => 20000]);
        $chq = $payments->record(Customer::find(2), ['payment_date' => today()->subDays(20), 'method' => 'cheque', 'amount' => 15000,
            'cheque_number' => '004512', 'cheque_bank' => 'Commercial Bank', 'cheque_date' => today()->subDays(18)]);
        $payments->returnCheque($chq, today()->subDays(15)->toDateString(), 'Insufficient funds');
        $payments->record(Customer::find(3), ['payment_date' => today()->subDays(2), 'method' => 'bank_transfer', 'amount' => 10000, 'reference' => 'HNB-77812']);

        // A pending sales order and a return.
        $sales->saveOrder([
            'customer_id' => 4, 'warehouse_id' => $main->id, 'order_date' => today()->toDateString(), 'tax_percent' => 0,
            'items' => $all->random(2)->map(fn ($p) => ['product_id' => $p->id, 'quantity' => 4, 'unit_price' => $p->selling_price])->all(),
        ]);
        $invoice = \App\Models\Invoice::latest('id')->first();
        $item = $invoice->items->first();
        $sales->createCreditNote($invoice, [$item->id => 1], ['credit_date' => today()->toDateString(), 'reason' => 'Damaged in transit', 'restock' => false]);

    }
}
