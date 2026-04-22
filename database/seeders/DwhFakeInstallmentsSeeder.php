<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class DwhFakeInstallmentsSeeder extends Seeder
{
    public function run(): void
    {
        $faker = \Faker\Factory::create();

        DB::transaction(function () use ($faker) {
            $contracts = DB::table('marts.fact_commercial_contract')
                ->select('commercial_contract_id','tenant_id','amount','start_date','end_date','payment_interval')
                ->get();

            $batch = [];
            $id = 100000;
            $methods = ['bank_transfer','cash','sadad','cheque','card'];

            foreach ($contracts as $c) {
                $start = Carbon::parse($c->start_date);
                $end   = Carbon::parse($c->end_date);
                $months = max(1, $start->diffInMonths($end));
                $perInst = round(((float) $c->amount) / $months, 2);

                for ($m = 0; $m < $months; $m++) {
                    $due  = (clone $start)->addMonths($m);
                    if ($due->year < 2024 || $due->year > 2030) continue;

                    $isPaid       = $due->lt(Carbon::now()) ? $faker->boolean(70) : false;
                    $isPrepayment = (!$isPaid) ? false : $faker->boolean(10);
                    $paidAt       = $isPaid ? (clone $due)->subDays($faker->numberBetween(0, 7)) : null;

                    $batch[] = [
                        'installment_id'        => ++$id,
                        'commercial_contract_id'=> $c->commercial_contract_id,
                        'payment_ref'           => sprintf('INV-%06d', $id),
                        'transaction_id'        => $isPaid ? 'TRX-' . $faker->numerify('########') : null,
                        'transaction_date'      => $paidAt?->toDateString(),
                        'lessor_id'             => 1,
                        'tenant_id'             => $c->tenant_id,
                        'period_start'          => $due->toDateString(),
                        'period_end'            => (clone $due)->addMonth()->subDay()->toDateString(),
                        'payment_due_date'      => $due->toDateString(),
                        'original_payment_date' => $due->toDateString(),
                        'payment_date'          => $paidAt?->toDateString(),
                        'amount'                => $perInst,
                        'amount_prepayment'     => 0,
                        'is_paid'               => $isPaid,
                        'is_prepayment'         => $isPrepayment,
                        'payment_type'          => $isPaid ? $faker->randomElement($methods) : null,
                        'payment_interval'      => $c->payment_interval ?? 'monthly',
                        'created_at'            => $start->format('Y-m-d H:i:sP'),
                        'source_updated_at'     => ($paidAt ?? $due)->format('Y-m-d H:i:sP'),
                    ];

                    if (count($batch) >= 300) {
                        DB::table('marts.fact_installment')->insertOrIgnore($batch);
                        $batch = [];
                    }
                }
            }
            DB::table('marts.fact_installment')->insertOrIgnore($batch);
        });

        $this->command->info('Installments seeded.');
    }
}
