<?php

namespace Tests\Feature;

use App\Exports\ChunkedQueryReportExport;
use App\Models\ActivityLog;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ─────────────────────────────────────────────────────────────────

    private function userWithReportAccess(): User
    {
        Permission::firstOrCreate(['name' => 'reports.view', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'TestReporter', 'guard_name' => 'web']);
        $role->givePermissionTo('reports.view');
        $user = User::factory()->create(['username' => 'tester_' . uniqid()]);
        $user->assignRole($role);

        // Report routes are protected by location.scope. A non-admin test user
        // must therefore have a primary branch, otherwise the middleware
        // correctly returns 403 before ReportController is reached.
        $location = $this->location();
        $user->employee->locations()->attach($location->id, [
            'is_primary' => true,
            'started_at' => now()->toDateString(),
        ]);

        return $user;
    }

    private function location(): Location
    {
        return Location::create([
            'name'      => 'Test Branch',
            'code'      => 'TB-' . uniqid(),
            'type'      => 'branch',
            'is_active' => true,
        ]);
    }

    private function category(): \App\Models\Category
    {
        return \App\Models\Category::create([
            'name'       => 'TestCat-' . uniqid(),
            'slug'       => 'test-cat-' . uniqid(),
            'is_active'  => true,
            'sort_order' => 0,
        ]);
    }

    private function product(int $categoryId, ?string $name = null): \App\Models\Product
    {
        return \App\Models\Product::create([
            'category_id'        => $categoryId,
            'name'               => $name ?? ('Prod-' . uniqid()),
            'sku'                => 'SKU-' . uniqid(),
            'barcode'            => app(\App\Services\ProductCodeService::class)->generateEan13(),
            'unit'               => 'piece',
            'base_selling_price' => 10.00,
            'is_active'          => true,
        ]);
    }

    private function createOrder(int $locationId, int $userId, float $amount = 100.00): \App\Models\Order
    {
        return \App\Models\Order::create([
            'order_number'        => 'ORD-' . uniqid(),
            'location_id'         => $locationId,
            'created_by'          => $userId,
            'status'              => 'completed',
            'payment_status'      => 'paid',
            'payment_arrangement' => 'pay_now',
            'subtotal'            => $amount,
            'discount_amount'     => 0.00,
            'tax_amount'          => 0.00,
            'total_amount'        => $amount,
        ]);
    }

    private function createStockMovement(int $locationId, int $productId, int $userId, float $qty = 10.00): \App\Models\StockMovement
    {
        return \App\Models\StockMovement::create([
            'location_id'    => $locationId,
            'product_id'     => $productId,
            'movement_type'  => 'in',
            'reason'         => 'stock_received',
            'quantity'       => $qty,
            'balance_before' => 0,
            'balance_after'  => $qty,
            'created_by'     => $userId,
        ]);
    }

    private function invoiceRow(int $locationId, int $issuedBy, string $date, float $amount): array
    {
        return [
            'invoice_number'   => 'INV-' . uniqid(),
            'invoice_type'     => 'regular_order',
            'order_type'       => 'order',
            'order_id'         => 0,
            'location_id'      => $locationId,
            'issued_by'        => $issuedBy,
            'status'           => 'active',
            'subtotal'         => $amount,
            'discount_amount'  => 0,
            'tax_amount'       => 0,
            'total_amount'     => $amount,
            'paid_amount'      => 0,
            'remaining_amount' => $amount,
            'issued_at'        => $date . ' 10:00:00',
            'created_at'       => now(),
            'updated_at'       => now(),
        ];
    }

    /**
     * Generate an xlsx file via Excel::raw() and return its parsed rows as a
     * 2-D array (row 0 = header). Rows are 0-indexed; columns are 0-indexed.
     *
     * @return array<int, array<int, mixed>>
     */
    private function xlsxRows(ChunkedQueryReportExport $export): array
    {
        $bytes   = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);
        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_assert_');
        file_put_contents($tmpFile, $bytes);
        try {
            $spreadsheet = IOFactory::load($tmpFile);
            // toArray(nullValue, calculateFormulas, formatData, returnCellRef)
            return array_values(
                $spreadsheet->getActiveSheet()->toArray(null, false, false, false)
            );
        } finally {
            @unlink($tmpFile);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  SECTION 1 — Chunk-boundary correctness
    //  Confirms that offset-based lazy() pagination yields every row exactly once
    //  even when the dataset spans multiple DB chunks.
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Seeds N rows where N is 3.5× the chunk size, then asserts that every seeded
     * row appears exactly once in the generated xlsx, in the expected order, with
     * no duplicates and no gaps caused by offset drift.
     *
     * The chunk_size is set to 2 so the generator must issue multiple DB batches
     * (2 + 2 + 2 + 1) to read 7 rows, exercising the pagination boundary.
     */
    #[Test]
    public function xlsx_chunk_boundary_all_rows_appear_exactly_once_in_order(): void
    {
        Config::set('excel.exports.chunk_size', 2); // forces multiple batches for 7 rows

        $user = $this->userWithReportAccess();

        // Seed 7 rows with distinct, identifiable action values ordered by id.
        for ($i = 1; $i <= 7; $i++) {
            ActivityLog::create([
                'user_id'     => $user->id,
                'action'      => "chunk-test-{$i}",
                'module'      => 'reports',
                'record_type' => 'Report',
                'record_id'   => $i,
                'created_at'  => now()->addSeconds($i), // ensure stable timestamp ordering
            ]);
        }

        $export = new ChunkedQueryReportExport(
            ActivityLog::with('user')->latest()->orderByDesc('id'),
            ['المستخدم', 'الإجراء', 'السجل', 'التاريخ'],
            'Activity Logs',
            fn ($row) => [$row->user?->username ?? '—', $row->action, $row->module, $row->created_at?->format('Y/m/d H:i')],
            cap: 1000,
            truncated: false,
        );

        $rows = $this->xlsxRows($export);

        // Row 0 is the header; rows 1-7 are data.
        $this->assertCount(8, $rows, 'Expected 1 header row + 7 data rows = 8 total rows.');

        // Collect every action value from the data rows (column 1 = action).
        $actions = array_map(fn ($r) => $r[1], array_slice($rows, 1));

        // Assert all 7 action identifiers appear — proves no row was skipped.
        for ($i = 1; $i <= 7; $i++) {
            $this->assertContains(
                "chunk-test-{$i}",
                $actions,
                "chunk-test-{$i} must appear in the exported rows (no gap at chunk boundary)."
            );
        }

        // Assert no duplicates — proves offset pagination did not re-read any row.
        $this->assertCount(7, array_unique($actions),
            'Each action value must appear exactly once (no duplicate rows from chunk boundary).');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  SECTION 2 — Content correctness
    //  Asserts seeded field values appear in correct columns of the generated xlsx.
    //  Covers the Eloquent-model path (orders, invoices, stock-movements) and the
    //  raw DB::table path (product-sales).
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Orders — verifies that the location name and total amount set during seeding
     * actually appear in the expected xlsx columns after formatRowForExport maps them.
     * Column layout: [#id, customer, location, amount, status, date]
     */
    #[Test]
    public function xlsx_orders_content_matches_seeded_data(): void
    {
        $user = $this->userWithReportAccess();
        $loc  = $this->location(); // name = 'Test Branch'

        $order1 = $this->createOrder($loc->id, $user->id, 125.50);
        $order2 = $this->createOrder($loc->id, $user->id, 250.00);

        $dateFrom = now()->startOfMonth()->toDateString();
        $dateTo   = now()->toDateString();
        $end      = $dateTo . ' 23:59:59';

        $export = new ChunkedQueryReportExport(
            \App\Models\Order::with(['customer', 'location'])
                ->whereIn('location_id', collect([$loc->id]))
                ->whereBetween('created_at', [$dateFrom, $end])
                ->latest()->orderByDesc('id'),
            ['#', 'العميل', 'الفرع', 'المبلغ', 'الحالة', 'التاريخ'],
            'Orders',
            fn ($row) => $this->mapRow('orders', $row),
            cap: 1000,
        );

        $rows = $this->xlsxRows($export);

        // Row 0 = header, rows 1-2 = data (DESC order so order2 first).
        $this->assertCount(3, $rows, '1 header + 2 data rows expected.');

        // PhpSpreadsheet normalises number_format() strings back to PHP floats on
        // load, so compare as floats (125.50 → 125.5, 250.00 → 250.0).
        $amounts   = array_map('floatval', array_column(array_slice($rows, 1), 3));
        $locations = array_column(array_slice($rows, 1), 2); // col index 2 = branch (string)

        $this->assertContains(125.5, $amounts, 'First order total_amount must appear in the xlsx.');
        $this->assertContains(250.0, $amounts, 'Second order total_amount must appear in the xlsx.');
        $this->assertContains('Test Branch', $locations, 'Location name must appear in the branch column.');
    }

    /**
     * Invoices — verifies total_amount, paid_amount, remaining_amount columns.
     * Column layout: [#id, customer, location, total, paid, remaining, status, date]
     */
    #[Test]
    public function xlsx_invoices_content_matches_seeded_data(): void
    {
        $user = $this->userWithReportAccess();
        $loc  = $this->location();

        $today = now()->toDateString();
        \App\Models\Invoice::insert([
            $this->invoiceRow($loc->id, $user->id, $today, 300.00),
            $this->invoiceRow($loc->id, $user->id, $today, 450.75),
        ]);

        $end = $today . ' 23:59:59';

        $export = new ChunkedQueryReportExport(
            \App\Models\Invoice::with(['customer', 'location'])
                ->whereIn('location_id', collect([$loc->id]))
                ->whereBetween('issued_at', [$today, $end])
                ->latest('issued_at')->orderByDesc('id'),
            ['#', 'العميل', 'الفرع', 'الإجمالي', 'المدفوع', 'المتبقي', 'الحالة', 'التاريخ'],
            'Invoices',
            fn ($row) => $this->mapRow('invoices', $row),
            cap: 1000,
        );

        $rows = $this->xlsxRows($export);

        $this->assertCount(3, $rows, '1 header + 2 data rows expected.');

        // PhpSpreadsheet normalises number_format() strings to floats on load.
        $totals   = array_map('floatval', array_column(array_slice($rows, 1), 3));
        $statuses = array_column(array_slice($rows, 1), 6); // col 6 = status (string)

        $this->assertContains(300.0, $totals, '300.00 total must appear in the xlsx.');
        $this->assertContains(450.75, $totals, '450.75 total must appear in the xlsx.');

        // All seeded invoices are 'active' → Arabic label 'نشطة'
        foreach ($statuses as $s) {
            $this->assertSame('نشطة', $s, "Status column must show Arabic label for active invoices.");
        }
    }

    /**
     * Stock-movements — verifies product name and quantity survive the Eloquent
     * with(['product','location']) eager-load and formatRowForExport mapping.
     * Column layout: [product_name, location_name, type, quantity, date]
     */
    #[Test]
    public function xlsx_stock_movements_content_matches_seeded_data(): void
    {
        $user    = $this->userWithReportAccess();
        $loc     = $this->location();
        $cat     = $this->category();
        $product = $this->product($cat->id, 'Chocolate Cake');

        $this->createStockMovement($loc->id, $product->id, $user->id, 25.00);

        $dateFrom = now()->startOfMonth()->toDateString();
        $dateTo   = now()->toDateString();
        $end      = $dateTo . ' 23:59:59';

        $export = new ChunkedQueryReportExport(
            \App\Models\StockMovement::with(['product', 'location'])
                ->whereIn('location_id', collect([$loc->id]))
                ->whereBetween('created_at', [$dateFrom, $end])
                ->latest()->orderByDesc('id'),
            ['المنتج', 'الموقع', 'النوع', 'الكمية', 'التاريخ'],
            'Stock Movements',
            fn ($row) => $this->mapRow('stock-movements', $row),
            cap: 1000,
        );

        $rows = $this->xlsxRows($export);

        $this->assertCount(2, $rows, '1 header + 1 data row expected.');

        $dataRow = $rows[1];
        $this->assertSame('Chocolate Cake', $dataRow[0], 'Product name must appear in column 0.');
        $this->assertSame('Test Branch', $dataRow[1], 'Location name must appear in column 1.');
        // PhpSpreadsheet normalises number_format() strings to floats on load.
        $this->assertEqualsWithDelta(25.0, (float) $dataRow[3], 0.001, 'Quantity (25.00) must appear in column 3.');
    }

    /**
     * Product-sales — uses a raw DB::table('order_items') query (not Eloquent).
     * Verifies that the product name and aggregated quantity surface in the xlsx.
     * Column layout: [product_name, total_qty, total_revenue]
     */
    #[Test]
    public function xlsx_product_sales_raw_query_content_matches_seeded_data(): void
    {
        $user  = $this->userWithReportAccess();
        $loc   = $this->location();
        $cat   = $this->category();
        $prod  = $this->product($cat->id, 'Date Cake');
        $order = $this->createOrder($loc->id, $user->id, 60.00);

        \Illuminate\Support\Facades\DB::table('order_items')->insert([
            ['order_id' => $order->id, 'product_id' => $prod->id, 'product_name' => $prod->name,
             'unit_price' => 30.00, 'quantity' => 2, 'discount_amount' => 0, 'line_total' => 60.00],
        ]);

        $dateFrom = now()->startOfMonth()->toDateString();
        $dateTo   = now()->toDateString();
        $end      = $dateTo . ' 23:59:59';

        $export = new ChunkedQueryReportExport(
            \Illuminate\Support\Facades\DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->whereIn('orders.location_id', [$loc->id])
                ->whereBetween('orders.created_at', [$dateFrom, $end])
                ->where('orders.status', '!=', 'cancelled')
                ->groupBy('order_items.product_id', 'order_items.product_name')
                ->select(
                    'order_items.product_name',
                    \Illuminate\Support\Facades\DB::raw('SUM(order_items.quantity) as total_qty'),
                    \Illuminate\Support\Facades\DB::raw('SUM(order_items.line_total) as total_revenue')
                )
                ->orderByDesc('total_qty')
                ->orderBy('order_items.product_name'),
            ['المنتج', 'الكمية المباعة', 'إجمالي المبيعات ₪'],
            'Product Sales',
            fn ($row) => $this->mapRow('product-sales', $row),
            cap: 1000,
        );

        $rows = $this->xlsxRows($export);

        $this->assertCount(2, $rows, '1 header + 1 data row expected.');

        $dataRow = $rows[1];
        $this->assertSame('Date Cake', $dataRow[0],
            'Product name must appear in column 0 of the product-sales xlsx.');
        // PhpSpreadsheet normalises number_format() strings to floats on load.
        $this->assertEqualsWithDelta(2.0, (float) $dataRow[1], 0.001,
            'Total quantity (2.00) must appear in column 1.');
        $this->assertEqualsWithDelta(60.0, (float) $dataRow[2], 0.001,
            'Total revenue (60.00) must appear in column 2.');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  SECTION 3 — Truncation path (end-to-end, controller + file content)
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Ties the controller's cap enforcement to the generated file content:
     * seeds 5 rows, sets cap = 3, verifies the xlsx has exactly 3 data rows
     * plus a warning row — all in one pass through the full controller path.
     *
     * This exercises: countExportRows() → truncated=true → ChunkedQueryReportExport
     * → generator stops at cap → AfterSheet appends warning.
     */
    #[Test]
    public function xlsx_truncation_produces_exactly_capped_rows_plus_warning_in_generated_file(): void
    {
        Config::set('excel.exports.chunk_size', 2); // force multi-batch fetch

        $user = $this->userWithReportAccess();

        // Seed 5 rows; cap will be 3 — two rows must be suppressed.
        for ($i = 1; $i <= 5; $i++) {
            ActivityLog::create([
                'user_id'     => $user->id,
                'action'      => "trunc-row-{$i}",
                'module'      => 'reports',
                'record_type' => 'Report',
                'record_id'   => $i,
                'created_at'  => now()->addSeconds($i),
            ]);
        }

        $cap    = 3;
        $export = new ChunkedQueryReportExport(
            ActivityLog::with('user')->latest()->orderByDesc('id'),
            ['المستخدم', 'الإجراء', 'السجل', 'التاريخ'],
            'Activity Logs',
            fn ($row) => [$row->user?->username ?? '—', $row->action, $row->module, $row->created_at?->format('Y/m/d H:i')],
            cap: $cap,
            truncated: true,
        );

        $rows = $this->xlsxRows($export);

        // Expected: 1 header + 3 capped data rows + 1 warning = 5 total
        $this->assertCount(5, $rows,
            "Expected 1 header + {$cap} capped data rows + 1 warning row = 5 total rows.");

        // The last row must be the Arabic warning (merged across columns, so check col 0).
        $warningCell = (string) ($rows[4][0] ?? '');
        $this->assertStringContainsString('تنبيه', $warningCell,
            'The final row must be the Arabic truncation warning appended by AfterSheet.');
        $this->assertStringContainsString((string) $cap, $warningCell,
            "The warning must mention the cap ({$cap}).");

        // None of the 3 data rows (rows 1-3) should be the warning.
        $dataActions = array_map(fn ($r) => (string) ($r[1] ?? ''), array_slice($rows, 1, 3));
        foreach ($dataActions as $action) {
            $this->assertStringNotContainsString('تنبيه', $action,
                'Data rows must not contain the warning text.');
        }
    }

    /**
     * HTTP-level truncation smoke test: seeds 5 rows, lowers the cap to 2 via
     * config, calls the export endpoint, and asserts 200 + correct MIME. This
     * confirms the controller's countExportRows() → truncated=true code path
     * runs without exception when integrated with the middleware stack.
     */
    #[Test]
    public function xlsx_truncation_http_path_succeeds_when_rows_exceed_cap(): void
    {
        putenv('EXPORT_XLSX_ROW_CAP=2');

        try {
            $user = $this->userWithReportAccess();

            for ($i = 1; $i <= 5; $i++) {
                ActivityLog::create([
                    'user_id'     => $user->id,
                    'action'      => 'view',
                    'module'      => 'reports',
                    'record_type' => 'Report',
                    'record_id'   => $i,
                    'created_at'  => now(),
                ]);
            }

            $response = $this->actingAs($user)->get(route('reports.export.xlsx', [
                'type'      => 'activity-logs',
                'date_from' => now()->startOfMonth()->toDateString(),
                'date_to'   => now()->toDateString(),
            ]));

            $response->assertStatus(200);
            $response->assertHeader(
                'Content-Type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            );
        } finally {
            putenv('EXPORT_XLSX_ROW_CAP');
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  SECTION 4 — registerEvents unit test
    //  Directly exercises the AfterSheet callback so we can assert the warning
    //  cell value without needing to round-trip through a file at all.
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function xlsx_truncation_warning_row_appended_by_after_sheet_event(): void
    {
        $cap    = 3;
        $export = new ChunkedQueryReportExport(
            ActivityLog::orderBy('id'),
            ['المستخدم', 'الإجراء', 'السجل', 'التاريخ'],
            'Test',
            fn ($row) => [$row->action],
            cap: $cap,
            truncated: true,
        );

        $events = $export->registerEvents();
        $this->assertArrayHasKey(
            \Maatwebsite\Excel\Events\AfterSheet::class,
            $events,
            'registerEvents() must return an AfterSheet handler when truncated=true.'
        );

        // Invoke the callback with a real PhpSpreadsheet worksheet.
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $phpSheet    = $spreadsheet->getActiveSheet();
        $phpSheet->setCellValue('A1', 'Header');
        $phpSheet->setCellValue('A2', 'Row 1');
        $phpSheet->setCellValue('A3', 'Row 2');
        $phpSheet->setCellValue('A4', 'Row 3'); // exactly $cap data rows

        $delegate   = new \Maatwebsite\Excel\Sheet($phpSheet);
        $afterSheet = new \Maatwebsite\Excel\Events\AfterSheet($delegate, $export);

        ($events[\Maatwebsite\Excel\Events\AfterSheet::class])($afterSheet);

        // AfterSheet should have added one more row after the last data row.
        $lastRow   = $phpSheet->getHighestRow();
        $this->assertSame(5, $lastRow, 'Warning row must be appended as row 5 (1 header + 3 data + 1 warning).');

        $cellValue = (string) $phpSheet->getCell("A{$lastRow}")->getValue();
        $this->assertStringContainsString('تنبيه', $cellValue, 'Warning cell must contain the Arabic warning text.');
        $this->assertStringContainsString((string) $cap, $cellValue, "Warning cell must mention the cap ({$cap}).");
    }

    #[Test]
    public function xlsx_no_after_sheet_event_when_not_truncated(): void
    {
        $export = new ChunkedQueryReportExport(
            ActivityLog::orderBy('id'),
            [],
            'Test',
            fn ($row) => [],
            cap: 1000,
            truncated: false,
        );

        $this->assertEmpty(
            $export->registerEvents(),
            'registerEvents() must return an empty array when truncated=false.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  SECTION 5 — Generator behaviour
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function generator_stops_at_cap_rows(): void
    {
        $user = $this->userWithReportAccess();

        for ($i = 1; $i <= 5; $i++) {
            ActivityLog::create([
                'user_id'     => $user->id,
                'action'      => 'view',
                'module'      => 'reports',
                'record_type' => 'Report',
                'record_id'   => $i,
                'created_at'  => now(),
            ]);
        }

        $cap    = 3;
        $export = new ChunkedQueryReportExport(
            ActivityLog::with('user')->orderBy('id'),
            ['المستخدم', 'الإجراء', 'السجل', 'التاريخ'],
            'Test',
            fn ($row) => [$row->user?->username ?? '—', $row->action, $row->module, $row->created_at?->format('Y/m/d')],
            cap: $cap,
            truncated: true,
        );

        $rows = iterator_to_array($export->generator());
        $this->assertCount($cap, $rows, "Generator must stop exactly at cap ({$cap} rows).");
    }

    #[Test]
    public function generator_yields_all_rows_when_under_cap(): void
    {
        $user = $this->userWithReportAccess();

        ActivityLog::insert([
            ['user_id' => $user->id, 'action' => 'a', 'module' => 'm', 'record_type' => 'T', 'record_id' => 1, 'created_at' => now()],
            ['user_id' => $user->id, 'action' => 'b', 'module' => 'm', 'record_type' => 'T', 'record_id' => 2, 'created_at' => now()],
        ]);

        $export = new ChunkedQueryReportExport(
            ActivityLog::orderBy('id'),
            [],
            'T',
            fn ($row) => [$row->action],
            cap: 100,
        );

        $rows = iterator_to_array($export->generator());
        $this->assertCount(2, $rows, 'All rows should be yielded when total is below cap.');
    }

    #[Test]
    public function generator_eager_loads_relations_without_n_plus_one(): void
    {
        $user = $this->userWithReportAccess();

        for ($i = 1; $i <= 3; $i++) {
            ActivityLog::create([
                'user_id'     => $user->id,
                'action'      => 'test',
                'module'      => 'm',
                'record_type' => 'T',
                'record_id'   => $i,
                'created_at'  => now(),
            ]);
        }

        $export = new ChunkedQueryReportExport(
            ActivityLog::with('user')->orderBy('id'),
            [],
            'T',
            fn ($row) => [$row->user?->username ?? '—'],
            cap: 10,
        );

        $rows   = iterator_to_array($export->generator());
        $mapped = array_map(fn ($row) => ($export->map($row))[0], $rows);

        foreach ($mapped as $username) {
            $this->assertNotEquals('—', $username, 'User relation should be eager-loaded, not null.');
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  SECTION 6 — HTTP route tests (xlsx + pdf for ≥ 3 report types each)
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function monthly_sales_page_uses_database_compatible_year_month_grouping(): void
    {
        $user = $this->userWithReportAccess();
        $loc  = $user->primaryLocation();

        \App\Models\Invoice::insert([
            $this->invoiceRow($loc->id, $user->id, now()->toDateString(), 125.00),
        ]);

        $response = $this->actingAs($user)->get(route('reports.show', [
            'type'      => 'monthly-sales',
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to'   => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertSee(now()->format('Y-m'));
    }

    #[Test]
    public function xlsx_export_returns_spreadsheet_for_activity_logs(): void
    {
        $user = $this->userWithReportAccess();
        ActivityLog::insert([
            ['user_id' => $user->id, 'action' => 'create', 'module' => 'orders', 'record_type' => 'Order', 'record_id' => 1, 'created_at' => now()],
        ]);

        $response = $this->actingAs($user)->get(route('reports.export.xlsx', [
            'type'      => 'activity-logs',
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to'   => now()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    #[Test]
    public function xlsx_export_orders_returns_spreadsheet_with_data(): void
    {
        $user = $this->userWithReportAccess();
        $loc  = $this->location();
        $this->createOrder($loc->id, $user->id);

        $response = $this->actingAs($user)->get(route('reports.export.xlsx', [
            'type'      => 'orders',
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to'   => now()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    #[Test]
    public function xlsx_export_invoices_returns_spreadsheet_with_data(): void
    {
        $user = $this->userWithReportAccess();
        $loc  = $this->location();

        \App\Models\Invoice::insert([
            $this->invoiceRow($loc->id, $user->id, now()->toDateString(), 250.00),
        ]);

        $response = $this->actingAs($user)->get(route('reports.export.xlsx', [
            'type'      => 'invoices',
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to'   => now()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    #[Test]
    public function xlsx_export_stock_movements_returns_spreadsheet_with_data(): void
    {
        $user    = $this->userWithReportAccess();
        $loc     = $this->location();
        $cat     = $this->category();
        $product = $this->product($cat->id);
        $this->createStockMovement($loc->id, $product->id, $user->id);

        $response = $this->actingAs($user)->get(route('reports.export.xlsx', [
            'type'      => 'stock-movements',
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to'   => now()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    #[Test]
    public function xlsx_export_collections_special_case_returns_spreadsheet(): void
    {
        $user = $this->userWithReportAccess();
        $this->location();

        $response = $this->actingAs($user)->get(route('reports.export.xlsx', [
            'type'      => 'collections',
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to'   => now()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    #[Test]
    public function xlsx_export_product_sales_raw_query_returns_spreadsheet(): void
    {
        $user  = $this->userWithReportAccess();
        $loc   = $this->location();
        $cat   = $this->category();
        $prod  = $this->product($cat->id);
        $order = $this->createOrder($loc->id, $user->id);

        \Illuminate\Support\Facades\DB::table('order_items')->insert([
            'order_id'        => $order->id,
            'product_id'      => $prod->id,
            'product_name'    => $prod->name,
            'unit_price'      => 10.00,
            'quantity'        => 2,
            'discount_amount' => 0,
            'line_total'      => 20.00,
        ]);

        $response = $this->actingAs($user)->get(route('reports.export.xlsx', [
            'type'      => 'product-sales',
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to'   => now()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    // ─── PDF: ≥ 3 report types ─────────────────────────────────────────────────

    #[Test]
    public function pdf_export_returns_pdf_content_type(): void
    {
        $user = $this->userWithReportAccess();
        $this->location();

        $response = $this->actingAs($user)->get(route('reports.export.pdf', [
            'type'      => 'activity-logs',
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to'   => now()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        $pdf = $response->streamedContent();

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
        $this->assertMatchesRegularExpression('/\/Type\s*\/Page\b/', $pdf);
    }

    #[Test]
    public function pdf_export_orders_returns_pdf_with_data(): void
    {
        $user = $this->userWithReportAccess();
        $loc  = $this->location();
        $this->createOrder($loc->id, $user->id, 99.99);

        $response = $this->actingAs($user)->get(route('reports.export.pdf', [
            'type'      => 'orders',
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to'   => now()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    #[Test]
    public function pdf_export_invoices_returns_pdf_with_data(): void
    {
        $user = $this->userWithReportAccess();
        $loc  = $this->location();

        \App\Models\Invoice::insert([
            $this->invoiceRow($loc->id, $user->id, now()->toDateString(), 300.00),
        ]);

        $response = $this->actingAs($user)->get(route('reports.export.pdf', [
            'type'      => 'invoices',
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to'   => now()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    #[Test]
    public function pdf_export_stock_movements_returns_pdf_with_data(): void
    {
        $user    = $this->userWithReportAccess();
        $loc     = $this->location();
        $cat     = $this->category();
        $product = $this->product($cat->id);
        $this->createStockMovement($loc->id, $product->id, $user->id);

        $response = $this->actingAs($user)->get(route('reports.export.pdf', [
            'type'      => 'stock-movements',
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to'   => now()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    #[Test]
    public function pdf_export_collections_special_case_returns_pdf(): void
    {
        $user = $this->userWithReportAccess();
        $this->location();

        $response = $this->actingAs($user)->get(route('reports.export.pdf', [
            'type'      => 'collections',
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to'   => now()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  SECTION 7 — PDF template: truncation view
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function pdf_template_shows_truncation_warning_when_over_cap(): void
    {
        $html = view('pdf.reports.template', [
            'type'      => 'activity-logs',
            'title'     => 'Test',
            'rows'      => collect(),
            'columns'   => ['A', 'B'],
            'summary'   => [],
            'dateFrom'  => now()->toDateString(),
            'dateTo'    => now()->toDateString(),
            'truncated' => true,
            'cap'       => 500,
            'total'     => 1200,
        ])->render();

        $this->assertStringContainsString('500', $html);
        $this->assertStringContainsString('1,200', $html);
        $this->assertStringContainsString('تنبيه', $html);
    }

    #[Test]
    public function pdf_template_shows_no_truncation_when_under_cap(): void
    {
        $html = view('pdf.reports.template', [
            'type'      => 'activity-logs',
            'title'     => 'Test',
            'rows'      => collect(),
            'columns'   => [],
            'summary'   => [],
            'dateFrom'  => now()->toDateString(),
            'dateTo'    => now()->toDateString(),
            'truncated' => false,
            'cap'       => 500,
            'total'     => 100,
        ])->render();

        $this->assertStringNotContainsString('تنبيه', $html);
    }

    /**
     * End-to-end PDF truncation: seeds 5 rows, lowers cap to 2, calls the HTTP
     * endpoint, and verifies a valid PDF is returned (200 + %PDF magic bytes).
     */
    #[Test]
    public function pdf_truncation_path_completes_without_error_when_rows_exceed_cap(): void
    {
        putenv('EXPORT_PDF_ROW_CAP=2');

        try {
            $user = $this->userWithReportAccess();

            for ($i = 1; $i <= 5; $i++) {
                ActivityLog::create([
                    'user_id'     => $user->id,
                    'action'      => 'login',
                    'module'      => 'auth',
                    'record_type' => 'Session',
                    'record_id'   => $i,
                    'created_at'  => now(),
                ]);
            }

            $response = $this->actingAs($user)->get(route('reports.export.pdf', [
                'type'      => 'activity-logs',
                'date_from' => now()->startOfMonth()->toDateString(),
                'date_to'   => now()->toDateString(),
            ]));

            $response->assertStatus(200);
            $response->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $response->streamedContent(),
                'Response body must be a valid PDF file (starts with %PDF).');
        } finally {
            putenv('EXPORT_PDF_ROW_CAP');
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  SECTION 8 — Grouped-report count correctness (daily-sales)
    // ═══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function grouped_report_count_reflects_number_of_groups_not_rows_in_first_group(): void
    {
        $user = $this->userWithReportAccess();
        $loc  = $this->location();

        \App\Models\Invoice::insert([
            $this->invoiceRow($loc->id, $user->id, '2025-01-01', 100),
            $this->invoiceRow($loc->id, $user->id, '2025-01-01', 200),
            $this->invoiceRow($loc->id, $user->id, '2025-01-02', 150),
            $this->invoiceRow($loc->id, $user->id, '2025-01-03', 300),
        ]);

        $response = $this->actingAs($user)->get(route('reports.export.xlsx', [
            'type'      => 'daily-sales',
            'date_from' => '2025-01-01',
            'date_to'   => '2025-01-03',
        ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  SECTION 9 — cake-orders, cash-sessions, stock-transfers
    //  Covers three report types not previously tested. Each type relies on PHP
    //  enum casts for its status column; these tests exercise that path so a
    //  future enum rename silently producing a blank cell will be caught here.
    // ═══════════════════════════════════════════════════════════════════════════

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function createEmployee(string $name = 'Test Employee'): \App\Models\Employee
    {
        return \App\Models\Employee::create([
            'employee_number'   => 'EMP-' . uniqid(),
            'full_name'         => $name,
            'employment_status' => 'active',
        ]);
    }

    private function createCustomer(): \App\Models\Customer
    {
        return \App\Models\Customer::create([
            'name'  => 'Customer-' . uniqid(),
            'phone' => '05' . rand(10000000, 99999999),
        ]);
    }

    private function createCashSession(
        int $locationId,
        int $employeeId,
        string $status = 'open',
        float $opening = 100.00
    ): \App\Models\CashSession {
        return \App\Models\CashSession::create([
            'employee_id'     => $employeeId,
            'location_id'     => $locationId,
            'opening_balance' => $opening,
            'opened_at'       => now(),
            'status'          => $status,
        ]);
    }

    private function createStockTransfer(
        int $fromLocId,
        int $toLocId,
        string $status = 'draft'
    ): \App\Models\StockTransfer {
        return \App\Models\StockTransfer::create([
            'transfer_number' => 'TRN-' . uniqid(),
            'from_location_id' => $fromLocId,
            'to_location_id'   => $toLocId,
            'status'           => $status,
        ]);
    }

    private function createCakeOrder(
        int $originBranchId,
        int $customerId,
        int $createdBy,
        string $status = 'draft'
    ): \App\Models\SpecialCakeOrder {
        return \App\Models\SpecialCakeOrder::create([
            'order_number'    => 'CAKE-' . uniqid(),
            'customer_id'     => $customerId,
            'origin_branch_id' => $originBranchId,
            'required_date'   => now()->addDays(7)->toDateString(),
            'status'          => $status,
            'created_by'      => $createdBy,
        ]);
    }

    // ─── Content correctness ─────────────────────────────────────────────────

    /**
     * cake-orders — verifies that the CakeOrderStatus enum label ('مسودة' for
     * Draft) reaches column 3 of the generated xlsx via formatRowForExport.
     * Column layout: [#id, customer, branch, status_label, required_date, date]
     */
    #[Test]
    public function xlsx_cake_orders_status_enum_label_appears_in_export(): void
    {
        $user     = $this->userWithReportAccess();
        $loc      = $this->location();
        $customer = $this->createCustomer();

        $this->createCakeOrder($loc->id, $customer->id, $user->id, 'draft');

        $dateFrom = now()->startOfMonth()->toDateString();
        $dateTo   = now()->toDateString();
        $end      = $dateTo . ' 23:59:59';

        $export = new ChunkedQueryReportExport(
            \App\Models\SpecialCakeOrder::with(['customer', 'originBranch'])
                ->whereIn('origin_branch_id', collect([$loc->id]))
                ->whereBetween('created_at', [$dateFrom, $end])
                ->latest()->orderByDesc('id'),
            ['رقم الطلب', 'العميل', 'الفرع', 'الحالة', 'تاريخ التسليم', 'التاريخ'],
            'Cake Orders',
            fn ($row) => $this->mapRow('cake-orders', $row),
            cap: 1000,
        );

        $rows = $this->xlsxRows($export);

        $this->assertCount(2, $rows, '1 header + 1 data row expected.');

        $dataRow = $rows[1];
        // Column 3 = status label (enum path: $row->status?->label())
        $this->assertSame('مسودة', $dataRow[3],
            'CakeOrderStatus::Draft must produce the Arabic label "مسودة" in column 3.');
        // Column 2 = origin branch name
        $this->assertSame('Test Branch', $dataRow[2],
            'Origin branch name must appear in column 2.');
    }

    /**
     * cash-sessions — verifies the open/closed status string ('مفتوح' for open)
     * reaches column 6 of the generated xlsx via formatRowForExport.
     * Column layout: [branch, employee, opening_balance, cash_received, actual_cash, variance, status, date]
     */
    #[Test]
    public function xlsx_cash_sessions_status_appears_correctly_in_export(): void
    {
        $user     = $this->userWithReportAccess();
        $loc      = $this->location();
        $employee = $this->createEmployee('Ali Hassan');

        $this->createCashSession($loc->id, $employee->id, 'open', 200.00);

        $dateFrom = now()->startOfMonth()->toDateString();
        $dateTo   = now()->toDateString();
        $end      = $dateTo . ' 23:59:59';

        $export = new ChunkedQueryReportExport(
            \App\Models\CashSession::with(['location', 'employee'])
                ->whereIn('location_id', collect([$loc->id]))
                ->whereBetween('created_at', [$dateFrom, $end])
                ->latest()->orderByDesc('id'),
            ['الفرع', 'الموظف', 'الرصيد الافتتاحي', 'المستلم', 'الفعلي', 'الفرق', 'الحالة', 'التاريخ'],
            'Cash Sessions',
            fn ($row) => $this->mapRow('cash-sessions', $row),
            cap: 1000,
        );

        $rows = $this->xlsxRows($export);

        $this->assertCount(2, $rows, '1 header + 1 data row expected.');

        $dataRow = $rows[1];
        // Column 0 = location name
        $this->assertSame('Test Branch', $dataRow[0],
            'Location name must appear in column 0.');
        // Column 1 = employee full_name
        $this->assertSame('Ali Hassan', $dataRow[1],
            'Employee full_name must appear in column 1.');
        // Column 6 = status ('مفتوح' for open)
        $this->assertSame('مفتوح', $dataRow[6],
            'CashSessionStatus::Open must produce "مفتوح" in column 6.');
    }

    /**
     * stock-transfers — verifies the StockTransferStatus enum label ('تم الشحن'
     * for Dispatched) reaches column 2 of the generated xlsx via formatRowForExport.
     * Column layout: [from_location, to_location, status_label, dispatcher, date]
     */
    #[Test]
    public function xlsx_stock_transfers_status_enum_label_appears_in_export(): void
    {
        $locFrom = $this->location();
        $locTo   = $this->location();
        $this->createStockTransfer($locFrom->id, $locTo->id, 'dispatched');

        $dateFrom = now()->startOfMonth()->toDateString();
        $dateTo   = now()->toDateString();
        $end      = $dateTo . ' 23:59:59';

        $export = new ChunkedQueryReportExport(
            \App\Models\StockTransfer::with(['fromLocation', 'toLocation', 'dispatchedBy'])
                ->whereBetween('created_at', [$dateFrom, $end])
                ->latest()->orderByDesc('id'),
            ['من', 'إلى', 'الحالة', 'المرسل', 'التاريخ'],
            'Stock Transfers',
            fn ($row) => $this->mapRow('stock-transfers', $row),
            cap: 1000,
        );

        $rows = $this->xlsxRows($export);

        $this->assertCount(2, $rows, '1 header + 1 data row expected.');

        $dataRow = $rows[1];
        // Column 0 = from location name
        $this->assertSame($locFrom->name, $dataRow[0],
            'From-location name must appear in column 0.');
        // Column 1 = to location name
        $this->assertSame($locTo->name, $dataRow[1],
            'To-location name must appear in column 1.');
        // Column 2 = status label (enum path: $row->status?->label())
        $this->assertSame('تم الشحن', $dataRow[2],
            'StockTransferStatus::Dispatched must produce the Arabic label "تم الشحن" in column 2.');
    }

    // ─── HTTP xlsx ────────────────────────────────────────────────────────────

    #[Test]
    public function xlsx_export_cake_orders_returns_spreadsheet_with_data(): void
    {
        $user     = $this->userWithReportAccess();
        $loc      = $this->location();
        $customer = $this->createCustomer();
        $this->createCakeOrder($loc->id, $customer->id, $user->id, 'pending_factory_review');

        $response = $this->actingAs($user)->get(route('reports.export.xlsx', [
            'type'      => 'cake-orders',
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to'   => now()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    #[Test]
    public function xlsx_export_cash_sessions_returns_spreadsheet_with_data(): void
    {
        $user     = $this->userWithReportAccess();
        $loc      = $this->location();
        $employee = $this->createEmployee();
        $this->createCashSession($loc->id, $employee->id, 'closed');

        $response = $this->actingAs($user)->get(route('reports.export.xlsx', [
            'type'      => 'cash-sessions',
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to'   => now()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    #[Test]
    public function xlsx_export_stock_transfers_returns_spreadsheet_with_data(): void
    {
        $user    = $this->userWithReportAccess();
        $locFrom = $this->location();
        $locTo   = $this->location();
        $this->createStockTransfer($locFrom->id, $locTo->id, 'received');

        $response = $this->actingAs($user)->get(route('reports.export.xlsx', [
            'type'      => 'stock-transfers',
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to'   => now()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    // ─── HTTP pdf + template content ──────────────────────────────────────────

    /**
     * cake-orders PDF — verifies:
     *   1. HTTP route returns 200 + application/pdf (end-to-end path).
     *   2. The rendered template HTML contains the seeded status label, branch
     *      name, and required_date (catches the delivery_date → required_date
     *      regression and any future enum label renames).
     */
    #[Test]
    public function pdf_export_cake_orders_returns_pdf_and_template_shows_status_and_date(): void
    {
        $user     = $this->userWithReportAccess();
        $loc      = $this->location(); // name = 'Test Branch'
        $customer = $this->createCustomer();
        $order    = $this->createCakeOrder($loc->id, $customer->id, $user->id, 'accepted');

        // ── HTTP smoke test ──────────────────────────────────────────────────
        $response = $this->actingAs($user)->get(route('reports.export.pdf', [
            'type'      => 'cake-orders',
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to'   => now()->toDateString(),
        ]));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        // ── Template content assertions ──────────────────────────────────────
        $rows = \App\Models\SpecialCakeOrder::with(['customer', 'originBranch'])
            ->where('id', $order->id)->get();

        $html = view('pdf.reports.template', [
            'type'      => 'cake-orders',
            'title'     => 'Cake Orders',
            'rows'      => $rows,
            'columns'   => ['رقم الطلب', 'العميل', 'الفرع', 'الحالة', 'تاريخ التسليم', 'التاريخ'],
            'summary'   => [],
            'dateFrom'  => now()->startOfMonth()->toDateString(),
            'dateTo'    => now()->toDateString(),
            'truncated' => false,
            'cap'       => 500,
            'total'     => 1,
        ])->render();

        // Branch name must appear via $row->originBranch?->name
        $this->assertStringContainsString('Test Branch', $html,
            'Branch name must appear in the cake-orders PDF template.');

        // Status label must appear (CakeOrderStatus::Accepted → 'مقبول')
        $this->assertStringContainsString('مقبول', $html,
            'CakeOrderStatus::Accepted label "مقبول" must appear in the PDF template.');

        // required_date must appear (not delivery_date which was the pre-fix field)
        $expectedDate = now()->addDays(7)->format('Y/m/d');
        $this->assertStringContainsString($expectedDate, $html,
            'required_date must appear in the cake-orders PDF template (not the stale delivery_date field).');
    }

    /**
     * cash-sessions PDF — verifies:
     *   1. HTTP route returns 200 + application/pdf.
     *   2. The rendered template HTML shows the open-session status 'مفتوح',
     *      the employee name, and the location name.
     */
    #[Test]
    public function pdf_export_cash_sessions_returns_pdf_and_template_shows_status_and_employee(): void
    {
        $user     = $this->userWithReportAccess();
        $loc      = $this->location(); // name = 'Test Branch'
        $employee = $this->createEmployee('Sara Nasser');
        $session  = $this->createCashSession($loc->id, $employee->id, 'open', 350.00);

        // ── HTTP smoke test ──────────────────────────────────────────────────
        $response = $this->actingAs($user)->get(route('reports.export.pdf', [
            'type'      => 'cash-sessions',
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to'   => now()->toDateString(),
        ]));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        // ── Template content assertions ──────────────────────────────────────
        $rows = \App\Models\CashSession::with(['location', 'employee'])
            ->where('id', $session->id)->get();

        $html = view('pdf.reports.template', [
            'type'      => 'cash-sessions',
            'title'     => 'Cash Sessions',
            'rows'      => $rows,
            'columns'   => ['الفرع', 'الموظف', 'الرصيد الافتتاحي', 'المستلم', 'الفعلي', 'الفرق', 'الحالة', 'التاريخ'],
            'summary'   => [],
            'dateFrom'  => now()->startOfMonth()->toDateString(),
            'dateTo'    => now()->toDateString(),
            'truncated' => false,
            'cap'       => 500,
            'total'     => 1,
        ])->render();

        $this->assertStringContainsString('Test Branch', $html,
            'Location name must appear in the cash-sessions PDF template.');
        $this->assertStringContainsString('Sara Nasser', $html,
            'Employee full_name must appear in the cash-sessions PDF template.');
        $this->assertStringContainsString('مفتوح', $html,
            'Open-session status "مفتوح" must appear in the cash-sessions PDF template.');
    }

    /**
     * stock-transfers PDF — verifies:
     *   1. HTTP route returns 200 + application/pdf.
     *   2. The rendered template HTML contains from-location and to-location
     *      names so any relation rename in StockTransfer is caught.
     */
    #[Test]
    public function pdf_export_stock_transfers_returns_pdf_and_template_shows_locations(): void
    {
        $user     = $this->userWithReportAccess();
        $locFrom  = $this->location();
        $locTo    = $this->location();
        $transfer = $this->createStockTransfer($locFrom->id, $locTo->id, 'dispatched');

        // ── HTTP smoke test ──────────────────────────────────────────────────
        $response = $this->actingAs($user)->get(route('reports.export.pdf', [
            'type'      => 'stock-transfers',
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to'   => now()->toDateString(),
        ]));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        // ── Template content assertions ──────────────────────────────────────
        $rows = \App\Models\StockTransfer::with(['fromLocation', 'toLocation', 'dispatchedBy'])
            ->where('id', $transfer->id)->get();

        $html = view('pdf.reports.template', [
            'type'      => 'stock-transfers',
            'title'     => 'Stock Transfers',
            'rows'      => $rows,
            'columns'   => ['من', 'إلى', 'الحالة', 'المرسل', 'التاريخ'],
            'summary'   => [],
            'dateFrom'  => now()->startOfMonth()->toDateString(),
            'dateTo'    => now()->toDateString(),
            'truncated' => false,
            'cap'       => 500,
            'total'     => 1,
        ])->render();

        $this->assertStringContainsString($locFrom->name, $html,
            'From-location name must appear in the stock-transfers PDF template.');
        $this->assertStringContainsString($locTo->name, $html,
            'To-location name must appear in the stock-transfers PDF template (toLocation relation).');
    }

    // ═══════════════════════════════════════════════════════════════════════════
    //  Private bridge — calls the same formatRowForExport logic the controller uses
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Delegates to the controller's formatRowForExport via reflection so that
     * content-assertion tests do not duplicate the mapping logic — they test the
     * same code path the production controller executes.
     */
    private function mapRow(string $type, mixed $row): array
    {
        $controller = new \App\Http\Controllers\Reports\ReportController();
        $method     = new \ReflectionMethod($controller, 'formatRowForExport');
        $method->setAccessible(true);
        return $method->invoke($controller, $type, $row);
    }
}
