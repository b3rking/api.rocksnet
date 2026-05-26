<?php

use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentTypeEnum;
use App\Models\Invoice;
use App\Models\StockHistory;
use App\Models\User;
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
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->float('amount');
            $table->foreignIdFor('currency_id');
            $table->foreignIdFor(User::class, 'saved_by');
            $table->foreignIdFor(User::class, 'agent_id')->nullable();
            $table->string('description')->nullable();
            $table->foreignIdFor(StockHistory::class)->nullable();
            $table->enum('payment_type', PaymentTypeEnum::cases());
            $table->foreignIdFor(Invoice::class)->nullable();
            $table->enum('payment_method', PaymentMethodEnum::cases());
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
