<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id('payment_id');
            $table->foreignId('ticket_id')->constrained('tickets', 'ticket_id')->onDelete('cascade');
            $table->string('payment_reference', 100)->unique();
            $table->decimal('amount_paid', 10, 2);
            $table->datetime('payment_date')->useCurrent();
            $table->enum('payment_method', ['cash', 'online_banking', 'gcash', 'maya', 'over_the_counter']);
            $table->enum('payment_status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->string('transaction_id', 100)->nullable()->comment('Gateway transaction ID');
            $table->string('receipt_number', 50)->nullable();
            $table->string('receipt_path', 255)->nullable();
            $table->string('paid_by', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('ticket_id');
            $table->index('payment_reference');
            $table->index('payment_status');
            $table->index('payment_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};