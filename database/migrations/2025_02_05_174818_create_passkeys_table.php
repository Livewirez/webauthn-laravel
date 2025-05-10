<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(config('webauthn.passkeys_table'), function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->string('name', 255)->nullable();
            $table->string('device_name', 255)->nullable();
            $table->string('public_key_credential_id', 255)->unique();
            $table->json('data');
            $table->string('trust_path_type')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->bigInteger('usage_count')->default(0);

            $table->morphs('passkey_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('webauthn.passkeys_table'));
    }
};
