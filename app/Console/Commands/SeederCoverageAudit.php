<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Throwable;

class SeederCoverageAudit extends Command
{
    protected $signature = 'system:seeder-audit
        {--save : Save JSON and Markdown reports under storage/app/audits}
        {--details : Print every table instead of problems and summaries only}
        {--top=25 : Maximum rows shown in each console section}';

    protected $description = 'Read-only full-system data coverage audit used to design a complete demo DatabaseSeeder.';

    /** @var array<string, array{minimum:int, module:string, priority:string}> */
    private array $expectations = [
        'locations' => ['minimum' => 2, 'module' => 'core', 'priority' => 'critical'],
        'employees' => ['minimum' => 8, 'module' => 'employees', 'priority' => 'critical'],
        'employee_locations' => ['minimum' => 8, 'module' => 'employees', 'priority' => 'critical'],
        'users' => ['minimum' => 8, 'module' => 'users', 'priority' => 'critical'],
        'roles' => ['minimum' => 8, 'module' => 'permissions', 'priority' => 'critical'],
        'permissions' => ['minimum' => 25, 'module' => 'permissions', 'priority' => 'critical'],
        'model_has_roles' => ['minimum' => 8, 'module' => 'permissions', 'priority' => 'critical'],
        'role_has_permissions' => ['minimum' => 25, 'module' => 'permissions', 'priority' => 'critical'],
        'modules' => ['minimum' => 10, 'module' => 'architecture', 'priority' => 'critical'],
        'business_profiles' => ['minimum' => 1, 'module' => 'architecture', 'priority' => 'critical'],
        'business_profile_modules' => ['minimum' => 5, 'module' => 'architecture', 'priority' => 'high'],
        'system_settings' => ['minimum' => 15, 'module' => 'settings', 'priority' => 'high'],
        'categories' => ['minimum' => 5, 'module' => 'catalog', 'priority' => 'critical'],
        'products' => ['minimum' => 15, 'module' => 'catalog', 'priority' => 'critical'],
        'location_products' => ['minimum' => 15, 'module' => 'catalog', 'priority' => 'critical'],
        'inventories' => ['minimum' => 15, 'module' => 'inventory', 'priority' => 'critical'],
        'stock_movements' => ['minimum' => 5, 'module' => 'inventory', 'priority' => 'high'],
        'inventory_batches' => ['minimum' => 3, 'module' => 'expiry', 'priority' => 'high'],
        'stock_counts' => ['minimum' => 1, 'module' => 'inventory', 'priority' => 'medium'],
        'stock_count_items' => ['minimum' => 3, 'module' => 'inventory', 'priority' => 'medium'],
        'stock_requests' => ['minimum' => 2, 'module' => 'inventory', 'priority' => 'high'],
        'stock_request_items' => ['minimum' => 3, 'module' => 'inventory', 'priority' => 'high'],
        'stock_transfers' => ['minimum' => 1, 'module' => 'inventory', 'priority' => 'medium'],
        'customers' => ['minimum' => 8, 'module' => 'sales', 'priority' => 'critical'],
        'orders' => ['minimum' => 12, 'module' => 'sales', 'priority' => 'critical'],
        'order_items' => ['minimum' => 20, 'module' => 'sales', 'priority' => 'critical'],
        'invoices' => ['minimum' => 8, 'module' => 'finance', 'priority' => 'critical'],
        'invoice_items' => ['minimum' => 12, 'module' => 'finance', 'priority' => 'high'],
        'payments' => ['minimum' => 6, 'module' => 'finance', 'priority' => 'critical'],
        'payment_methods' => ['minimum' => 3, 'module' => 'finance', 'priority' => 'critical'],
        'financial_periods' => ['minimum' => 2, 'module' => 'finance', 'priority' => 'high'],
        'cash_sessions' => ['minimum' => 2, 'module' => 'finance', 'priority' => 'high'],
        'suppliers' => ['minimum' => 3, 'module' => 'procurement', 'priority' => 'high'],
        'supplier_products' => ['minimum' => 5, 'module' => 'procurement', 'priority' => 'high'],
        'purchase_orders' => ['minimum' => 2, 'module' => 'procurement', 'priority' => 'high'],
        'purchase_order_items' => ['minimum' => 4, 'module' => 'procurement', 'priority' => 'high'],
        'goods_receipts' => ['minimum' => 1, 'module' => 'procurement', 'priority' => 'medium'],
        'supplier_invoices' => ['minimum' => 1, 'module' => 'procurement', 'priority' => 'medium'],
        'sales_channels' => ['minimum' => 3, 'module' => 'sales_channels', 'priority' => 'medium'],
        'restaurant_areas' => ['minimum' => 1, 'module' => 'restaurant', 'priority' => 'high'],
        'restaurant_tables' => ['minimum' => 6, 'module' => 'restaurant', 'priority' => 'high'],
        'restaurant_table_sessions' => ['minimum' => 2, 'module' => 'restaurant', 'priority' => 'medium'],
        'kitchen_stations' => ['minimum' => 1, 'module' => 'kitchen', 'priority' => 'critical'],
        'kitchen_tickets' => ['minimum' => 3, 'module' => 'kitchen', 'priority' => 'high'],
        'kitchen_ticket_items' => ['minimum' => 5, 'module' => 'kitchen', 'priority' => 'high'],
        'recipes' => ['minimum' => 2, 'module' => 'production', 'priority' => 'critical'],
        'recipe_items' => ['minimum' => 5, 'module' => 'production', 'priority' => 'critical'],
        'production_orders' => ['minimum' => 2, 'module' => 'production', 'priority' => 'critical'],
        'production_order_items' => ['minimum' => 4, 'module' => 'production', 'priority' => 'high'],
        'work_shifts' => ['minimum' => 2, 'module' => 'attendance', 'priority' => 'critical'],
        'employee_shift_assignments' => ['minimum' => 5, 'module' => 'attendance', 'priority' => 'critical'],
        'attendance_records' => ['minimum' => 40, 'module' => 'attendance', 'priority' => 'critical'],
        'leave_types' => ['minimum' => 3, 'module' => 'attendance', 'priority' => 'medium'],
        'employee_leave_requests' => ['minimum' => 2, 'module' => 'attendance', 'priority' => 'medium'],
        'employee_compensation_profiles' => ['minimum' => 5, 'module' => 'payroll', 'priority' => 'critical'],
        'employee_payroll_adjustments' => ['minimum' => 8, 'module' => 'payroll', 'priority' => 'critical'],
        'employee_advances' => ['minimum' => 2, 'module' => 'payroll', 'priority' => 'medium'],
        'payroll_periods' => ['minimum' => 2, 'module' => 'payroll', 'priority' => 'critical'],
        'payroll_items' => ['minimum' => 8, 'module' => 'payroll', 'priority' => 'critical'],
        'payroll_item_components' => ['minimum' => 16, 'module' => 'payroll', 'priority' => 'critical'],
        'payroll_payments' => ['minimum' => 3, 'module' => 'payroll', 'priority' => 'high'],
        'employee_ledger_entries' => ['minimum' => 12, 'module' => 'payroll', 'priority' => 'critical'],
        'report_schedules' => ['minimum' => 2, 'module' => 'reports', 'priority' => 'medium'],
        'notifications' => ['minimum' => 8, 'module' => 'notifications', 'priority' => 'high'],
        'activity_logs' => ['minimum' => 8, 'module' => 'audit', 'priority' => 'medium'],
    ];

    /** @var string[] */
    private array $technicalTables = [
        'migrations', 'cache', 'cache_locks', 'jobs', 'job_batches',
        'failed_jobs', 'sessions', 'password_reset_tokens', 'personal_access_tokens',
    ];

    /** @var string[] */
    private array $statusColumns = [
        'status', 'type', 'kind', 'direction', 'state', 'payment_status',
        'payment_arrangement', 'employment_status', 'restaurant_service_type',
    ];

    public function handle(): int
    {
        $startedAt = microtime(true);
        $this->newLine();
        $this->info('Dahab ERP — Full Seeder Coverage Audit (read only)');

        try {
            $tables = $this->tableNames();
        } catch (Throwable $e) {
            $this->error('Could not inspect database tables: '.$e->getMessage());
            return self::FAILURE;
        }

        $tableReport = $this->auditTables($tables);
        $foreignKeys = $this->auditForeignKeys($tables);
        $permissions = $this->auditPermissions();
        $scenarios = $this->auditScenarios();
        $issues = $this->buildIssues($tableReport, $foreignKeys, $permissions, $scenarios);

        $report = [
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'environment' => app()->environment(),
                'database_connection' => DB::getDefaultConnection(),
                'database_name' => DB::connection()->getDatabaseName(),
                'laravel_version' => app()->version(),
                'duration_seconds' => round(microtime(true) - $startedAt, 3),
                'read_only' => true,
            ],
            'summary' => $this->summary($tableReport, $foreignKeys, $permissions, $scenarios, $issues),
            'issues' => $issues,
            'tables' => $tableReport,
            'foreign_keys' => $foreignKeys,
            'permissions' => $permissions,
            'scenarios' => $scenarios,
        ];

        $this->printReport($report);

        if ($this->option('save')) {
            $paths = $this->saveReports($report);
            $this->newLine();
            $this->info('Reports saved:');
            $this->line('JSON: '.$paths['json']);
            $this->line('Markdown: '.$paths['markdown']);
        }

        return collect($issues)->contains(fn (array $issue) => $issue['priority'] === 'critical')
            ? self::FAILURE
            : self::SUCCESS;
    }

    /** @return string[] */
    private function tableNames(): array
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return collect(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))
                ->pluck('name')->sort()->values()->all();
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return collect(DB::select('SHOW TABLES'))
                ->map(fn (object $row) => (string) array_values((array) $row)[0])
                ->sort()->values()->all();
        }

        return collect(Schema::getTables())->pluck('name')->sort()->values()->all();
    }

    /** @param string[] $tables @return array<string, array<string, mixed>> */
    private function auditTables(array $tables): array
    {
        $report = [];

        foreach ($tables as $table) {
            try {
                $columns = Schema::getColumns($table);
                $columnNames = collect($columns)->pluck('name')->all();
                $count = DB::table($table)->count();
                $expectation = $this->expectations[$table] ?? null;
                $minimum = $expectation['minimum'] ?? 0;
                $nulls = [];

                if ($count > 0) {
                    foreach ($columns as $column) {
                        $name = $column['name'];
                        if (($column['nullable'] ?? false) && $this->worthCheckingNulls($name)) {
                            $nullCount = DB::table($table)->whereNull($name)->count();
                            if ($nullCount > 0) {
                                $nulls[$name] = [
                                    'count' => $nullCount,
                                    'percent' => round(($nullCount / $count) * 100, 2),
                                ];
                            }
                        }
                    }
                }

                $distinct = [];
                foreach (array_intersect($this->statusColumns, $columnNames) as $column) {
                    $distinct[$column] = DB::table($table)
                        ->select($column, DB::raw('COUNT(*) as aggregate'))
                        ->groupBy($column)->orderByDesc('aggregate')->limit(30)
                        ->get()->map(fn ($row) => [
                            'value' => $row->{$column},
                            'count' => (int) $row->aggregate,
                        ])->all();
                }

                $dateRange = [];
                foreach (['created_at', 'issued_at', 'order_date', 'work_date', 'entry_date', 'paid_at'] as $dateColumn) {
                    if (in_array($dateColumn, $columnNames, true) && $count > 0) {
                        $range = DB::table($table)->selectRaw("MIN(`{$dateColumn}`) as min_value, MAX(`{$dateColumn}`) as max_value")->first();
                        $dateRange[$dateColumn] = ['min' => $range?->min_value, 'max' => $range?->max_value];
                        break;
                    }
                }

                $report[$table] = [
                    'exists' => true,
                    'module' => $expectation['module'] ?? 'unclassified',
                    'expected_minimum' => $minimum,
                    'count' => $count,
                    'coverage_percent' => $minimum > 0 ? min(100, round(($count / $minimum) * 100, 2)) : null,
                    'status' => $minimum > 0 && $count < $minimum ? ($count === 0 ? 'empty' : 'insufficient') : 'ok',
                    'columns_count' => count($columns),
                    'columns' => $columnNames,
                    'nullable_data' => $nulls,
                    'distinct_values' => $distinct,
                    'date_range' => $dateRange,
                ];
            } catch (Throwable $e) {
                $report[$table] = ['exists' => true, 'status' => 'error', 'error' => $e->getMessage()];
            }
        }

        foreach ($this->expectations as $table => $expectation) {
            if (! isset($report[$table])) {
                $report[$table] = [
                    'exists' => false,
                    'module' => $expectation['module'],
                    'expected_minimum' => $expectation['minimum'],
                    'count' => 0,
                    'coverage_percent' => 0,
                    'status' => 'missing_table',
                    'columns_count' => 0,
                    'columns' => [],
                    'nullable_data' => [],
                    'distinct_values' => [],
                    'date_range' => [],
                ];
            }
        }

        ksort($report);
        return $report;
    }

    private function worthCheckingNulls(string $column): bool
    {
        return ! in_array($column, [
            'deleted_at', 'remember_token', 'email_verified_at', 'updated_at',
            'closed_at', 'cancelled_at', 'rejected_at', 'completed_at',
        ], true);
    }

    /** @param string[] $tables @return array<int, array<string, mixed>> */
    private function auditForeignKeys(array $tables): array
    {
        $results = [];

        foreach ($tables as $table) {
            try {
                foreach (Schema::getForeignKeys($table) as $foreignKey) {
                    $foreignColumns = Arr::wrap($foreignKey['columns'] ?? []);
                    $referencedColumns = Arr::wrap($foreignKey['foreign_columns'] ?? []);
                    $referencedTable = $foreignKey['foreign_table'] ?? null;

                    if (count($foreignColumns) !== 1 || count($referencedColumns) !== 1 || ! $referencedTable) {
                        continue;
                    }

                    $column = $foreignColumns[0];
                    $referencedColumn = $referencedColumns[0];
                    $orphans = DB::table($table.' as child')
                        ->leftJoin($referencedTable.' as parent', 'child.'.$column, '=', 'parent.'.$referencedColumn)
                        ->whereNotNull('child.'.$column)
                        ->whereNull('parent.'.$referencedColumn)
                        ->count();

                    $results[] = [
                        'table' => $table,
                        'column' => $column,
                        'references' => $referencedTable.'.'.$referencedColumn,
                        'orphans' => $orphans,
                        'status' => $orphans > 0 ? 'broken' : 'ok',
                    ];
                }
            } catch (Throwable $e) {
                $results[] = [
                    'table' => $table, 'column' => null, 'references' => null,
                    'orphans' => null, 'status' => 'error', 'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /** @return array<string, mixed> */
    private function auditPermissions(): array
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return ['status' => 'missing_tables'];
        }

        $routePermissions = collect(app('router')->getRoutes()->getRoutes())
            ->flatMap(fn ($route) => $route->gatherMiddleware())
            ->filter(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'can:'))
            ->map(fn (string $middleware) => trim(explode(',', substr($middleware, 4))[0]))
            ->filter()->unique()->sort()->values();

        $databasePermissions = Permission::query()->where('guard_name', 'web')->pluck('name')->sort()->values();
        $missingRoutePermissions = $routePermissions->diff($databasePermissions)->values()->all();
        $unusedPermissions = $databasePermissions->diff($routePermissions)->values()->all();

        $roles = Role::query()->where('guard_name', 'web')->withCount(['permissions', 'users'])
            ->orderBy('name')->get()->map(fn (Role $role) => [
                'name' => $role->name,
                'permissions' => $role->permissions_count,
                'users' => $role->users_count,
                'status' => $role->permissions_count === 0 ? 'no_permissions' : ($role->users_count === 0 ? 'no_users' : 'ok'),
            ])->all();

        $usersWithoutRoles = Schema::hasTable('users')
            ? DB::table('users as u')->leftJoin('model_has_roles as mhr', function ($join): void {
                $join->on('mhr.model_id', '=', 'u.id')->where('mhr.model_type', '=', 'App\\Models\\User');
            })->whereNull('mhr.role_id')->count()
            : null;

        $admin = collect($roles)->firstWhere('name', 'Admin');

        return [
            'route_permissions_count' => $routePermissions->count(),
            'database_permissions_count' => $databasePermissions->count(),
            'missing_route_permissions' => $missingRoutePermissions,
            'unused_permissions' => $unusedPermissions,
            'roles' => $roles,
            'users_without_roles' => $usersWithoutRoles,
            'admin_has_all_permissions' => $admin
                ? (int) $admin['permissions'] === $databasePermissions->count()
                : false,
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function auditScenarios(): array
    {
        $checks = [
            'active_locations' => ['table' => 'locations', 'where' => ['is_active' => 1], 'minimum' => 2, 'module' => 'core'],
            'active_users' => ['table' => 'users', 'where' => ['is_active' => 1], 'minimum' => 5, 'module' => 'users'],
            'active_products' => ['table' => 'products', 'where' => ['is_active' => 1], 'minimum' => 10, 'module' => 'catalog'],
            'approved_recipes' => ['table' => 'recipes', 'where' => ['status' => 'approved'], 'minimum' => 1, 'module' => 'production'],
            'active_recipes' => ['table' => 'recipes', 'where' => ['is_active' => 1], 'minimum' => 1, 'module' => 'production'],
            'active_kitchen_stations' => ['table' => 'kitchen_stations', 'where' => ['is_active' => 1], 'minimum' => 1, 'module' => 'kitchen'],
            'default_kitchen_stations' => ['table' => 'kitchen_stations', 'where' => ['is_default' => 1], 'minimum' => 1, 'module' => 'kitchen'],
            'open_financial_periods' => ['table' => 'financial_periods', 'where' => ['status' => 'open'], 'minimum' => 1, 'module' => 'finance'],
            'approved_payroll_periods' => ['table' => 'payroll_periods', 'where' => ['status' => 'approved'], 'minimum' => 1, 'module' => 'payroll'],
            'calculated_payroll_periods' => ['table' => 'payroll_periods', 'where' => ['status' => 'calculated'], 'minimum' => 1, 'module' => 'payroll'],
            'posted_payroll_payments' => ['table' => 'payroll_payments', 'where' => ['status' => 'posted'], 'minimum' => 1, 'module' => 'payroll'],
            'unread_notifications' => ['table' => 'notifications', 'where_null' => 'read_at', 'minimum' => 2, 'module' => 'notifications'],
        ];

        $results = [];
        foreach ($checks as $name => $check) {
            if (! Schema::hasTable($check['table'])) {
                $results[$name] = [...$check, 'count' => 0, 'status' => 'missing_table'];
                continue;
            }

            try {
                $query = DB::table($check['table']);
                foreach ($check['where'] ?? [] as $column => $value) {
                    if (Schema::hasColumn($check['table'], $column)) $query->where($column, $value);
                    else throw new \RuntimeException("Missing column {$column}");
                }
                if (isset($check['where_null'])) {
                    if (Schema::hasColumn($check['table'], $check['where_null'])) $query->whereNull($check['where_null']);
                    else throw new \RuntimeException("Missing column {$check['where_null']}");
                }
                $count = $query->count();
                $results[$name] = [...$check, 'count' => $count, 'status' => $count >= $check['minimum'] ? 'ok' : 'insufficient'];
            } catch (Throwable $e) {
                $results[$name] = [...$check, 'count' => 0, 'status' => 'error', 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /** @return array<int, array<string, mixed>> */
    private function buildIssues(array $tables, array $foreignKeys, array $permissions, array $scenarios): array
    {
        $issues = [];

        foreach ($tables as $table => $data) {
            if (! in_array($data['status'] ?? 'ok', ['missing_table', 'empty', 'insufficient', 'error'], true)) continue;
            $expectation = $this->expectations[$table] ?? ['priority' => 'low', 'module' => 'unclassified', 'minimum' => 0];
            $issues[] = [
                'priority' => $expectation['priority'], 'module' => $expectation['module'],
                'type' => 'table_coverage', 'target' => $table,
                'message' => "{$table}: ".($data['status'] ?? 'error')." ({$data['count']}/{$expectation['minimum']})",
                'recommended_seed_count' => max(0, $expectation['minimum'] - (int) ($data['count'] ?? 0)),
            ];
        }

        foreach ($foreignKeys as $foreignKey) {
            if (($foreignKey['orphans'] ?? 0) > 0) {
                $issues[] = [
                    'priority' => 'critical', 'module' => 'integrity', 'type' => 'orphan_rows',
                    'target' => $foreignKey['table'].'.'.$foreignKey['column'],
                    'message' => $foreignKey['orphans'].' orphan rows reference '.$foreignKey['references'],
                    'recommended_seed_count' => 0,
                ];
            }
        }

        foreach ($permissions['missing_route_permissions'] ?? [] as $permission) {
            $issues[] = [
                'priority' => 'critical', 'module' => 'permissions', 'type' => 'missing_permission',
                'target' => $permission, 'message' => 'Route permission is absent from permissions table.',
                'recommended_seed_count' => 1,
            ];
        }
        foreach ($permissions['roles'] ?? [] as $role) {
            if ($role['status'] === 'no_permissions') {
                $issues[] = [
                    'priority' => 'critical', 'module' => 'permissions', 'type' => 'empty_role',
                    'target' => $role['name'], 'message' => 'Role has no permissions.', 'recommended_seed_count' => 0,
                ];
            }
        }
        if (($permissions['users_without_roles'] ?? 0) > 0) {
            $issues[] = [
                'priority' => 'critical', 'module' => 'permissions', 'type' => 'users_without_roles',
                'target' => 'users', 'message' => $permissions['users_without_roles'].' users have no role.',
                'recommended_seed_count' => 0,
            ];
        }

        foreach ($scenarios as $name => $scenario) {
            if (($scenario['status'] ?? 'ok') === 'ok') continue;
            $issues[] = [
                'priority' => in_array($scenario['module'], ['payroll', 'production', 'finance'], true) ? 'critical' : 'high',
                'module' => $scenario['module'], 'type' => 'scenario_coverage', 'target' => $name,
                'message' => "{$name}: {$scenario['count']}/{$scenario['minimum']}",
                'recommended_seed_count' => max(0, $scenario['minimum'] - (int) $scenario['count']),
            ];
        }

        $rank = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
        usort($issues, fn ($a, $b) => [$rank[$a['priority']] ?? 9, $a['module'], $a['target']] <=> [$rank[$b['priority']] ?? 9, $b['module'], $b['target']]);
        return $issues;
    }

    private function summary(array $tables, array $foreignKeys, array $permissions, array $scenarios, array $issues): array
    {
        return [
            'tables_total' => count($tables),
            'tables_ok' => collect($tables)->where('status', 'ok')->count(),
            'tables_missing' => collect($tables)->where('status', 'missing_table')->count(),
            'tables_empty' => collect($tables)->where('status', 'empty')->count(),
            'tables_insufficient' => collect($tables)->where('status', 'insufficient')->count(),
            'foreign_keys_checked' => count($foreignKeys),
            'orphan_relationships' => collect($foreignKeys)->where('status', 'broken')->count(),
            'route_permissions_missing' => count($permissions['missing_route_permissions'] ?? []),
            'users_without_roles' => $permissions['users_without_roles'] ?? null,
            'scenarios_ok' => collect($scenarios)->where('status', 'ok')->count(),
            'scenarios_failed' => collect($scenarios)->where('status', '!=', 'ok')->count(),
            'issues_critical' => collect($issues)->where('priority', 'critical')->count(),
            'issues_high' => collect($issues)->where('priority', 'high')->count(),
            'issues_medium' => collect($issues)->where('priority', 'medium')->count(),
        ];
    }

    private function printReport(array $report): void
    {
        $summary = $report['summary'];
        $this->table(['Metric', 'Value'], collect($summary)->map(fn ($value, $key) => [$key, $value])->values()->all());

        $limit = max(1, (int) $this->option('top'));
        $issues = collect($report['issues'])->take($limit);
        $this->newLine();
        $this->warn('Highest-priority Seeder gaps:');
        $this->table(
            ['Priority', 'Module', 'Type', 'Target', 'Current problem', 'Seed'],
            $issues->map(fn ($issue) => [
                strtoupper($issue['priority']), $issue['module'], $issue['type'],
                $issue['target'], Str::limit($issue['message'], 70), $issue['recommended_seed_count'],
            ])->all()
        );

        if ($this->option('details')) {
            $this->newLine();
            $this->info('All table coverage:');
            $this->table(
                ['Table', 'Module', 'Count', 'Minimum', 'Coverage', 'Status'],
                collect($report['tables'])->map(fn ($data, $table) => [
                    $table, $data['module'] ?? '-', $data['count'] ?? '-', $data['expected_minimum'] ?? '-',
                    isset($data['coverage_percent']) ? $data['coverage_percent'].'%' : '-', $data['status'] ?? 'error',
                ])->values()->all()
            );
        }
    }

    /** @return array{json:string, markdown:string} */
    private function saveReports(array $report): array
    {
        $directory = storage_path('app/audits');
        File::ensureDirectoryExists($directory);
        $stamp = now()->format('Ymd_His');
        $json = $directory.'/seeder_coverage_'.$stamp.'.json';
        $markdown = $directory.'/seeder_coverage_'.$stamp.'.md';

        File::put($json, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        File::put($markdown, $this->markdown($report));
        return ['json' => $json, 'markdown' => $markdown];
    }

    private function markdown(array $report): string
    {
        $lines = [
            '# Dahab ERP — Seeder Coverage Audit', '',
            '- Generated: `'.$report['meta']['generated_at'].'`',
            '- Database: `'.$report['meta']['database_name'].'`',
            '- Read only: `true`', '',
            '## Summary', '', '| Metric | Value |', '|---|---:|',
        ];
        foreach ($report['summary'] as $key => $value) $lines[] = '| `'.$key.'` | '.$value.' |';

        $lines = [...$lines, '', '## Seeder build plan', '', '| Priority | Module | Target | Problem | Rows to seed |', '|---|---|---|---|---:|'];
        foreach ($report['issues'] as $issue) {
            $lines[] = '| '.strtoupper($issue['priority']).' | '.$issue['module'].' | `'.$issue['target'].'` | '.str_replace('|', '\\|', $issue['message']).' | '.$issue['recommended_seed_count'].' |';
        }

        $lines = [...$lines, '', '## Table coverage', '', '| Table | Module | Count | Minimum | Status |', '|---|---|---:|---:|---|'];
        foreach ($report['tables'] as $table => $data) {
            $lines[] = '| `'.$table.'` | '.($data['module'] ?? '-').' | '.($data['count'] ?? 0).' | '.($data['expected_minimum'] ?? 0).' | '.($data['status'] ?? 'error').' |';
        }

        $lines = [...$lines, '', '## Permission integrity', '',
            '- Missing route permissions: `'.count($report['permissions']['missing_route_permissions'] ?? []).'`',
            '- Users without roles: `'.($report['permissions']['users_without_roles'] ?? 'n/a').'`',
            '- Admin has all permissions: `'.(($report['permissions']['admin_has_all_permissions'] ?? false) ? 'yes' : 'no').'`', '',
        ];

        return implode(PHP_EOL, $lines).PHP_EOL;
    }
}
