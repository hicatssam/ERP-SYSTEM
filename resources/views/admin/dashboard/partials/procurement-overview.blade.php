@php($procurementRoutesAvailable = \Illuminate\Support\Facades\Route::has('procurement.dashboard'))

@if ($procurementRoutesAvailable)
    <div class="dashboard-row" style="margin-top:1rem">

        @canany(['suppliers.view', 'supplier_invoices.view', 'supplier_payments.view'])
            <div class="card">
                <div class="card-header">
                    <div>
                        <span class="card-title">الموردون والحسابات</span>
                        <div style="font-size:.75rem;color:var(--text-muted);margin-top:.2rem">
                            متابعة الموردين والفواتير والدفعات
                        </div>
                    </div>
                </div>

                <div class="card-body" style="display:flex;flex-direction:column;gap:.625rem">
                    @can('suppliers.view')
                        <a href="{{ route('suppliers.index') }}"
                            style="display:flex;align-items:center;gap:.75rem;padding:.75rem;border:1px solid var(--border-light);border-radius:var(--radius);text-decoration:none;color:var(--text)">
                            <span style="width:38px;height:38px;border-radius:10px;background:var(--gold-ultra);color:var(--gold-deep);display:flex;align-items:center;justify-content:center">
                                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                                    <circle cx="9" cy="7" r="4" />
                                    <path d="M19 8v6M22 11h-6" />
                                </svg>
                            </span>
                            <span style="flex:1">
                                <strong style="display:block;font-size:.85rem">الموردون</strong>
                                <small style="color:var(--text-muted)">بيانات الموردين وحساباتهم</small>
                            </span>
                        </a>
                    @endcan

                    @can('supplier_invoices.view')
                        <a href="{{ route('supplier-invoices.index') }}"
                            style="display:flex;align-items:center;gap:.75rem;padding:.75rem;border:1px solid var(--border-light);border-radius:var(--radius);text-decoration:none;color:var(--text)">
                            <span style="width:38px;height:38px;border-radius:10px;background:var(--gold-ultra);color:var(--gold-deep);display:flex;align-items:center;justify-content:center">
                                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M7 3h10a2 2 0 0 1 2 2v16l-3-2-4 2-4-2-3 2V5a2 2 0 0 1 2-2z" />
                                    <line x1="9" y1="8" x2="15" y2="8" />
                                    <line x1="9" y1="12" x2="15" y2="12" />
                                </svg>
                            </span>
                            <span style="flex:1">
                                <strong style="display:block;font-size:.85rem">فواتير الموردين</strong>
                                <small style="color:var(--text-muted)">الذمم والمبالغ المستحقة</small>
                            </span>
                        </a>
                    @endcan

                    @can('supplier_payments.view')
                        <a href="{{ route('supplier-payments.index') }}"
                            style="display:flex;align-items:center;gap:.75rem;padding:.75rem;border:1px solid var(--border-light);border-radius:var(--radius);text-decoration:none;color:var(--text)">
                            <span style="width:38px;height:38px;border-radius:10px;background:var(--gold-ultra);color:var(--gold-deep);display:flex;align-items:center;justify-content:center">
                                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="2" y="5" width="20" height="14" rx="2" />
                                    <path d="M2 10h20" />
                                    <path d="M7 15h3" />
                                </svg>
                            </span>
                            <span style="flex:1">
                                <strong style="display:block;font-size:.85rem">دفعات الموردين</strong>
                                <small style="color:var(--text-muted)">تسجيل ومتابعة المدفوعات</small>
                            </span>
                        </a>
                    @endcan
                </div>
            </div>
        @endcanany

        @canany(['purchase_orders.view', 'goods_receipts.view', 'purchase_returns.view', 'dashboard.procurement'])
            <div class="card">
                <div class="card-header">
                    <div>
                        <span class="card-title">الشراء والتوريد</span>
                        <div style="font-size:.75rem;color:var(--text-muted);margin-top:.2rem">
                            دورة الشراء من الأمر حتى الاستلام
                        </div>
                    </div>

                    @can('dashboard.procurement')
                        <a href="{{ route('procurement.dashboard') }}" class="card-action">
                            لوحة المشتريات
                        </a>
                    @endcan
                </div>

                <div class="card-body" style="display:flex;flex-direction:column;gap:.625rem">
                    @can('purchase_orders.view')
                        <a href="{{ route('purchase-orders.index') }}"
                            style="display:flex;align-items:center;gap:.75rem;padding:.75rem;border:1px solid var(--border-light);border-radius:var(--radius);text-decoration:none;color:var(--text)">
                            <span style="width:38px;height:38px;border-radius:10px;background:var(--gold-ultra);color:var(--gold-deep);display:flex;align-items:center;justify-content:center">
                                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 2h9l4 4v16H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2z" />
                                    <path d="M14 2v5h5" />
                                    <line x1="8" y1="12" x2="16" y2="12" />
                                    <line x1="8" y1="16" x2="14" y2="16" />
                                </svg>
                            </span>
                            <span style="flex:1">
                                <strong style="display:block;font-size:.85rem">أوامر الشراء</strong>
                                <small style="color:var(--text-muted)">إنشاء ومراجعة طلبات التوريد</small>
                            </span>
                        </a>
                    @endcan

                    @can('goods_receipts.view')
                        <a href="{{ route('goods-receipts.index') }}"
                            style="display:flex;align-items:center;gap:.75rem;padding:.75rem;border:1px solid var(--border-light);border-radius:var(--radius);text-decoration:none;color:var(--text)">
                            <span style="width:38px;height:38px;border-radius:10px;background:var(--gold-ultra);color:var(--gold-deep);display:flex;align-items:center;justify-content:center">
                                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 7l9-4 9 4-9 4-9-4z" />
                                    <path d="M3 7v10l9 4 9-4V7" />
                                    <path d="M12 11v10" />
                                </svg>
                            </span>
                            <span style="flex:1">
                                <strong style="display:block;font-size:.85rem">استلام البضاعة</strong>
                                <small style="color:var(--text-muted)">إضافة الكميات إلى المخزون</small>
                            </span>
                        </a>
                    @endcan

                    @can('purchase_returns.view')
                        <a href="{{ route('purchase-returns.index') }}"
                            style="display:flex;align-items:center;gap:.75rem;padding:.75rem;border:1px solid var(--border-light);border-radius:var(--radius);text-decoration:none;color:var(--text)">
                            <span style="width:38px;height:38px;border-radius:10px;background:var(--gold-ultra);color:var(--gold-deep);display:flex;align-items:center;justify-content:center">
                                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="9 14 4 9 9 4" />
                                    <path d="M20 20v-3a8 8 0 0 0-8-8H4" />
                                </svg>
                            </span>
                            <span style="flex:1">
                                <strong style="display:block;font-size:.85rem">مرتجعات الموردين</strong>
                                <small style="color:var(--text-muted)">إرجاع الأصناف للمورد</small>
                            </span>
                        </a>
                    @endcan
                </div>
            </div>
        @endcanany
    </div>
@endif