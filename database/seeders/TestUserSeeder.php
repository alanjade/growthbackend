<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\KycVerification;
use App\Models\Transaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        // ── Regular test user ─────────────────────────────────────────────
        $user = User::updateOrCreate(
            ['email' => 'ucheoma@gmail.com'],
            [
                'name'               => 'Omas Vee',
                'password'           => Hash::make('Securepass123!'),
                'email_verified_at'  => now(),
                'balance_kobo'       => 500_000_00,   // ₦500,000
                'rewards_balance_kobo' => 10_000_00,  // ₦10,000
                'is_admin'           => false,
                'is_suspended'       => false,
                'bank_name'          => 'First Bank',
                'bank_code'          => '011',
                'account_number'     => '3012345678',
                'account_name'       => 'Omas Vee',
                'recipient_code'     => 'RCP_testrecipient123',
                'bank_verified'      => true,
            ]
        );

        // KYC — approved
        KycVerification::updateOrCreate(
            ['user_id' => $user->id],
            [
                'full_name'        => 'Omas Vee',
                'date_of_birth'    => '1995-06-15',
                'phone_number'     => '08012345678',
                'address'          => '12 Lekki Phase 1',
                'city'             => 'Lagos',
                'state'            => 'Lagos',
                'country'          => 'Nigeria',
                'id_type'          => 'nin',
                'id_number'        => '12345678901',
                'id_front_path'    => 'seeder/placeholder_id_front.jpg',
                'id_back_path'     => 'seeder/placeholder_id_back.jpg',
                'selfie_path'      => 'seeder/placeholder_selfie.jpg',
                'status'           => 'approved',
                'verified_at'      => now(),
                'rejection_reason' => null,
            ]
        );

        // Seed some transaction history
        $txns = [
            ['type' => 'Deposit',    'amount_kobo' => 200_000_00, 'status' => 'completed', 'gateway' => 'paystack',  'days_ago' => 30],
            ['type' => 'Deposit',    'amount_kobo' => 300_000_00, 'status' => 'completed', 'gateway' => 'monnify',   'days_ago' => 20],
            ['type' => 'Withdrawal', 'amount_kobo' =>  50_000_00, 'status' => 'completed', 'gateway' => 'paystack',  'days_ago' => 15],
            ['type' => 'Deposit',    'amount_kobo' => 100_000_00, 'status' => 'completed', 'gateway' => 'paystack',  'days_ago' => 10],
            ['type' => 'Withdrawal', 'amount_kobo' =>  25_000_00, 'status' => 'pending',   'gateway' => 'monnify',   'days_ago' =>  2],
            ['type' => 'Deposit',    'amount_kobo' =>  75_000_00, 'status' => 'failed',    'gateway' => 'paystack',  'days_ago' =>  1],
        ];

        foreach ($txns as $t) {
            Transaction::updateOrCreate(
                [
                    'user_id'   => $user->id,
                    'reference' => 'TEST-' . strtoupper($t['type']) . '-' . $t['days_ago'],
                ],
                [
                    'type'       => $t['type'],
                    'amount_kobo'     => $t['amount_kobo'],
                    'status'     => $t['status'],
                    'gateway'    => $t['gateway'],
                    'created_at' => now()->subDays($t['days_ago']),
                    'updated_at' => now()->subDays($t['days_ago']),
                ]
            );
        }

        $this->command->info('✓ Test users seeded');
        $this->command->info('  Regular → ucheoma@gmail.com / Securepass123!');
    }
}