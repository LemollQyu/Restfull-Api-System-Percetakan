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
    Schema::create('password_otps', function (Blueprint $table) {
        $table->id();
        $table->string('email_or_phone');
        $table->string('otp_code');
        $table->enum('method', ['email', 'whatsapp']);
        $table->boolean('used')->default(false);
        $table->timestamp('expires_at');
        $table->timestamp('verified_at')->nullable();
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('password_otps');
}

};
