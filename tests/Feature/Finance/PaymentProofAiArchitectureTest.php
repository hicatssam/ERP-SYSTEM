<?php

namespace Tests\Feature\Finance;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PaymentProofAiArchitectureTest extends TestCase
{
    public function test_payment_proof_ai_routes_are_registered_and_protected(): void
    {
        foreach ([
            'payments.proof-review.index',
            'payments.proof-analysis.show',
            'payments.proof-analysis.store',
        ] as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Missing route: {$name}");
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth', $middleware);
            $this->assertContains('location.scope', $middleware);
            $this->assertContains('password.changed', $middleware);
            $this->assertContains('can:payments.verify', $middleware);
        }
    }

    public function test_payment_proof_ai_is_disabled_by_default_in_example_environment(): void
    {
        $env = file_get_contents(base_path('.env.example'));

        $this->assertIsString($env);
        $this->assertStringContainsString('PAYMENT_PROOF_AI_ENABLED=false', $env);
        $this->assertStringContainsString('PAYMENT_PROOF_AI_AUTO_ANALYZE=false', $env);
    }

    public function test_ai_service_keeps_human_verification_authoritative(): void
    {
        $service = file_get_contents(app_path('Services/Payments/PaymentProofAnalysisService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Finance/PaymentProofAnalysisController.php'));
        $view = file_get_contents(resource_path('views/finance/payments/proof-analysis.blade.php'));

        $this->assertIsString($service);
        $this->assertIsString($controller);
        $this->assertIsString($view);
        $this->assertStringContainsString('لا تقل إن الإشعار "مزور" بشكل قطعي', $service);
        $this->assertStringContainsString('لا تعتمد أو ترفض الدفعة تلقائيًا', $controller);
        $this->assertStringContainsString('لا يثبت وحده أن الحوالة صحيحة أو مزورة', $view);
    }

    public function test_payment_model_exposes_ai_analysis_history_and_only_auto_analyzes_pending_payments(): void
    {
        $payment = file_get_contents(app_path('Models/Payment.php'));

        $this->assertIsString($payment);
        $this->assertStringContainsString('function proofAnalyses()', $payment);
        $this->assertStringContainsString('function latestProofAnalysis()', $payment);
        $this->assertStringContainsString('&& $payment->isPendingVerification()', $payment);
    }

    public function test_ai_service_checks_duplicate_file_reference_amount_and_recipient(): void
    {
        $service = file_get_contents(app_path('Services/Payments/PaymentProofAnalysisService.php'));

        $this->assertIsString($service);
        $this->assertStringContainsString('duplicate_image_count', $service);
        $this->assertStringContainsString('duplicate_reference_count', $service);
        $this->assertStringContainsString('المبلغ المستخرج من الإثبات لا يطابق مبلغ الدفعة المسجل', $service);
        $this->assertStringContainsString('اسم المستفيد الظاهر في الإثبات لا يطابق اسم صاحب حساب الفرع', $service);
    }

    public function test_review_view_shows_extracted_financial_identity_fields(): void
    {
        $view = file_get_contents(resource_path('views/finance/payments/proof-analysis.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('اسم المحوّل', $view);
        $this->assertStringContainsString('حساب المحوّل', $view);
        $this->assertStringContainsString('رقم العملية', $view);
        $this->assertStringContainsString('مؤشرات الاشتباه والمطابقة', $view);
    }

    public function test_review_queue_exposes_risk_and_sender_filters(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Finance/PaymentProofReviewController.php'));
        $view = file_get_contents(resource_path('views/finance/payments/proof-review.blade.php'));

        $this->assertIsString($controller);
        $this->assertIsString($view);
        $this->assertStringContainsString("whereNotNull('payment_proof')", $controller);
        $this->assertStringContainsString("latestProofAnalysis", $controller);
        $this->assertStringContainsString('اسم المحوّل', $view);
        $this->assertStringContainsString('مستوى المخاطرة', $view);
        $this->assertStringContainsString('payments.proof-analysis.show', $view);
    }
}
