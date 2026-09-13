<?php

namespace Tests\Feature\Finance;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PaymentProofAiArchitectureTest extends TestCase
{
    public function test_payment_proof_ai_route_is_registered_and_protected(): void
    {
        $route = Route::getRoutes()->getByName('payments.proof-analysis.store');

        $this->assertNotNull($route);
        $middleware = $route->gatherMiddleware();
        $this->assertContains('auth', $middleware);
        $this->assertContains('location.scope', $middleware);
        $this->assertContains('password.changed', $middleware);
        $this->assertContains('can:payments.verify', $middleware);
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

        $this->assertIsString($service);
        $this->assertIsString($controller);
        $this->assertStringContainsString('لا تقل إن الإشعار "مزور" بشكل قطعي', $service);
        $this->assertStringContainsString('لا تعتمد أو ترفض الدفعة تلقائيًا', $controller);
    }

    public function test_payment_model_exposes_ai_analysis_history(): void
    {
        $payment = file_get_contents(app_path('Models/Payment.php'));

        $this->assertIsString($payment);
        $this->assertStringContainsString('function proofAnalyses()', $payment);
        $this->assertStringContainsString('function latestProofAnalysis()', $payment);
    }
}
