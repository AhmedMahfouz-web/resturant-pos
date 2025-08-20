<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('material_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_code')->unique();
            $table->unsignedBigInteger('material_id');
            $table->decimal('quantity_received', 10, 3);
            $table->string('unit'); // The unit in which material was received
            $table->decimal('unit_cost', 10, 2); // Cost per unit
            $table->decimal('total_cost', 10, 2); // Total cost of this receipt
            $table->enum('source_type', ['company_purchase', 'company_transfer', 'external_supplier']);
            $table->string('supplier_name')->nullable();
            $table->string('invoice_number')->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('received_by');
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index(['material_id', 'received_at']);
            $table->index('receipt_code');
        });
    }

    public function down()
    {
        Schema::dropIfExists('material_receipts');
    }
};
