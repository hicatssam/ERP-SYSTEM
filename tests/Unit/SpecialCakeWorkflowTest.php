<?php

namespace Tests\Unit;

use App\Enums\CakeOrderStatus;
use App\Models\SpecialCakeOrder;
use App\Services\SpecialCakes\SpecialCakeStatusTransitionService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpecialCakeWorkflowTest extends TestCase
{
    #[Test]
    public function visible_workflow_contains_four_stages_plus_cancelled(): void
    {
        $this->assertSame(
            [
                'pending',
                'in_progress',
                'ready',
                'completed',
                'cancelled',
            ],
            array_map(
                fn (CakeOrderStatus $status) =>
                    $status->value,
                CakeOrderStatus::workflowCases()
            )
        );
    }

    #[Test]
    public function legacy_statuses_map_to_the_simplified_workflow(): void
    {
        $this->assertSame(
            'pending',
            CakeOrderStatus::PendingFactoryReview
                ->workflowValue()
        );

        $this->assertSame(
            'in_progress',
            CakeOrderStatus::Decorating
                ->workflowValue()
        );

        $this->assertSame(
            'ready',
            CakeOrderStatus::ReceivedByBranch
                ->workflowValue()
        );

        $this->assertSame(
            'cancelled',
            CakeOrderStatus::Rejected
                ->workflowValue()
        );

        $this->assertSame(
            'ready',
            SpecialCakeOrder::normalizeLegacyWorkflowStatus(
                'ready_for_pickup'
            )
        );

        $this->assertSame(
            'completed',
            SpecialCakeOrder::normalizeLegacyWorkflowStatus(
                'delivered'
            )
        );
    }

    #[Test]
    public function new_orders_follow_only_the_four_stage_sequence(): void
    {
        $this->assertSame(
            [
                'pending',
                'cancelled',
            ],
            SpecialCakeOrder::allowedTransitions()[
                'draft'
            ]
        );

        $this->assertSame(
            [
                'in_progress',
                'cancelled',
            ],
            SpecialCakeOrder::allowedTransitions()[
                'pending'
            ]
        );

        $this->assertSame(
            [
                'ready',
                'cancelled',
            ],
            SpecialCakeOrder::allowedTransitions()[
                'in_progress'
            ]
        );

        $this->assertSame(
            [
                'completed',
                'cancelled',
            ],
            SpecialCakeOrder::allowedTransitions()[
                'ready'
            ]
        );

        $this->assertArrayNotHasKey(
            'completed',
            SpecialCakeOrder::allowedTransitions()
        );
    }

    #[Test]
    public function legacy_orders_can_continue_into_the_new_workflow(): void
    {
        $service =
            new SpecialCakeStatusTransitionService();

        $this->assertSame(
            [
                'in_progress',
                'cancelled',
            ],
            $service->allowedTransitions(
                'pending_factory_review'
            )
        );

        $this->assertSame(
            [
                'ready',
                'cancelled',
            ],
            $service->allowedTransitions(
                'quality_check'
            )
        );

        $this->assertSame(
            'cake_orders.accept',
            $service->requiredPermission(
                'pending_factory_review',
                'in_progress'
            )
        );

        $this->assertSame(
            'cake_orders.quality_check',
            $service->requiredPermission(
                'decorating',
                'ready'
            )
        );
    }
}
