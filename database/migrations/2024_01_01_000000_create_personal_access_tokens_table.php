<?php

use Phare\Database\Migration;
use Phare\Database\Schema\Blueprint;

return new class() extends Migration
{
    public function up(): void
    {
        $this->schema->create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['tokenable_type', 'tokenable_id']);
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('personal_access_tokens');
    }
};
