<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'employee_face_profiles',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('employee_id')
                    ->constrained('employees')
                    ->cascadeOnDelete();

                $table->string('provider', 40)
                    ->default('faceio');

                /*
                 * SHA-256 is used for deterministic lookup. The provider ID
                 * itself is stored separately only in encrypted form so the
                 * backend can request provider-side deletion/re-enrollment.
                 * It is never exposed in model serialization.
                 */
                $table->char(
                    'provider_face_id_hash',
                    64
                );

                /* Encrypted via Eloquent's encrypted cast. */
                $table->text(
                    'provider_face_id'
                )->nullable();

                $table->string('status', 30)
                    ->default('pending_verification');

                $table->foreignId('enrolled_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp('enrolled_at')
                    ->nullable();

                $table->timestamp('activated_at')
                    ->nullable();

                $table->timestamp('last_verified_at')
                    ->nullable();

                $table->timestamp('revoked_at')
                    ->nullable();

                $table->json('metadata')->nullable();

                $table->timestamps();

                $table->unique(
                    ['employee_id', 'provider'],
                    'employee_face_profiles_employee_provider_unique'
                );

                $table->unique(
                    ['provider', 'provider_face_id_hash'],
                    'employee_face_profiles_face_hash_unique'
                );

                $table->index(
                    ['provider', 'status'],
                    'employee_face_profiles_provider_status_idx'
                );
            }
        );

        Schema::create(
            'face_verification_events',
            function (Blueprint $table): void {
                $table->id();

                $table->string('provider', 40)
                    ->default('faceio');

                $table->string('event_name', 30);

                $table->char(
                    'provider_face_id_hash',
                    64
                );

                $table->string('app_id', 190)
                    ->nullable();

                $table->string('client_ip', 64)
                    ->nullable();

                $table->char('fingerprint', 64)
                    ->unique();

                $table->timestamp('occurred_at')
                    ->nullable();

                $table->timestamp('received_at');

                $table->timestamp('consumed_at')
                    ->nullable();

                $table->json('payload')
                    ->nullable();

                $table->json('metadata')
                    ->nullable();

                $table->timestamps();

                $table->index(
                    [
                        'provider',
                        'event_name',
                        'provider_face_id_hash',
                        'received_at',
                    ],
                    'face_events_lookup_idx'
                );
            }
        );

        Schema::table(
            'attendance_records',
            function (Blueprint $table): void {
                $table->string(
                    'verification_method',
                    40
                )->nullable()->after('source');

                $table->string(
                    'verification_provider',
                    40
                )->nullable()
                    ->after('verification_method');

                $table->string(
                    'verification_reference',
                    120
                )->nullable()
                    ->after('verification_provider');

                $table->foreignId(
                    'verification_location_id'
                )->nullable()
                    ->after('verification_reference')
                    ->constrained('locations')
                    ->nullOnDelete();

                $table->json(
                    'verification_metadata'
                )->nullable()
                    ->after('verification_location_id');
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'attendance_records',
            function (Blueprint $table): void {
                $table->dropConstrainedForeignId(
                    'verification_location_id'
                );

                $table->dropColumn([
                    'verification_method',
                    'verification_provider',
                    'verification_reference',
                    'verification_metadata',
                ]);
            }
        );

        Schema::dropIfExists(
            'face_verification_events'
        );

        Schema::dropIfExists(
            'employee_face_profiles'
        );
    }
};
