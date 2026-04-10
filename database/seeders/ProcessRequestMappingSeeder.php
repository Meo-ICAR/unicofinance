<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProcessRequestMappingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mappings = [
            [
                'request_type' => 'cancellazione',
                'process_id' => 5,
                'is_suggested' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'request_type' => 'accesso',
                'process_id' => 13,
                'is_suggested' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'request_type' => 'opposizione',
                'process_id' => 5,
                'is_suggested' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'request_type' => 'opposizione',
                'process_id' => 11,
                'is_suggested' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('process_request_mappings')->insert($mappings);

        $this->command->info('Process Request Mappings seeded successfully!');
    }
}
