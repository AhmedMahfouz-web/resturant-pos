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
        if (!Schema::hasTable('supplier_communications')) {
            Schema::create('supplier_communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');

            $table->enum('communication_type', [
                'inquiry',
                'order',
                'complaint',
                'feedback',
                'negotiation',
                'general'
            ]);

            $table->string('subject');
            $table->text('message');
            $table->timestamp('communication_date');

            $table->enum('method', [
                'email',
                'phone',
                'sms',
                'in_person',
                'online_chat'
            ]);

            $table->foreignId('initiated_by')->constrained('users');

            // Response tracking
            $table->boolean('response_received')->default(false);
            $table->timestamp('response_date')->nullable();
            $table->decimal('response_time_hours', 8, 1)->nullable();
            $table->decimal('satisfaction_rating', 2, 1)->nullable(); // 1-5 scale

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['supplier_id', 'communication_type']);
            $table->index(['supplier_id', 'communication_date']);
            $table->index(['response_received', 'communication_date']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_communications');
    }
};
