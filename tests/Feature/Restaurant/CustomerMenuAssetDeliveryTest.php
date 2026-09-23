<?php

namespace Tests\Feature\Restaurant;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerMenuAssetDeliveryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sweets_menu_asset_is_served_without_public_storage_symlink(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put(
            'images/sweets-menu/products/demo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"></svg>'
        );

        $response = $this->get(
            route(
                'customer-menu.assets.show',
                [
                    'path' =>
                        'images/sweets-menu/products/demo.svg',
                ]
            )
        );

        $response->assertOk();

        $this->assertStringContainsString(
            'image/svg',
            (string) $response->headers->get(
                'content-type'
            )
        );
    }

    #[Test]
    public function asset_route_refuses_files_outside_public_menu_prefix(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put(
            'incoming-transfer-proofs/private.pdf',
            'private'
        );

        $this->get(
            '/menu-assets/incoming-transfer-proofs/private.pdf'
        )->assertNotFound();
    }
}
