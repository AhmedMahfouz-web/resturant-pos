<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $payments = ['Cash', 'Visa'];
        foreach ($payments as $payment) {
            Payment::create(['name' => $payment]);
        }
    }
}
