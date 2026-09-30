<?php

namespace Tests\Feature\Restaurant;

use App\Models\SystemSetting;
use App\Support\PublicImageUrl;
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

    #[Test]
    public function uploaded_branding_and_product_images_work_without_a_storage_symlink(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('branding/logo.png', 'logo');
        Storage::disk('public')->put('products/cake.png', 'cake');
        SystemSetting::set('brand_logo', 'storage/branding/logo.png');

        $logoUrl = route('customer-menu.assets.show', ['path' => 'branding/logo.png'], false);
        $productUrl = route('customer-menu.assets.show', ['path' => 'products/cake.png'], false);

        $this->assertSame($logoUrl, SystemSetting::assetUrl('brand_logo'));
        $this->assertSame($productUrl, PublicImageUrl::url('products/cake.png'));
        $this->assertSame($productUrl, PublicImageUrl::url('public/storage/products/cake.png'));
        $this->get(route('login'))->assertOk()->assertSee($logoUrl, false);
        $this->assertSame('logo', $this->get($logoUrl)->assertOk()->streamedContent());
        $this->assertSame('cake', $this->get($productUrl)->assertOk()->streamedContent());
    }

    #[Test]
    public function private_images_and_missing_uploads_are_not_published(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('incoming-transfer-proofs/private.png', 'private');

        $this->assertNull(PublicImageUrl::url('storage/incoming-transfer-proofs/private.png'));
        $this->assertNull(PublicImageUrl::url('storage/branding/missing.png'));
        $this->assertFalse(PublicImageUrl::allowedDiskPath('branding/../private.png'));
        $this->get('/menu-assets/incoming-transfer-proofs/private.png')->assertNotFound();
    }
}
