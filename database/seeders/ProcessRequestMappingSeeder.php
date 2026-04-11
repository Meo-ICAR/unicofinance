<?php

namespace Database\Seeders;

use App\Models\Process;
use App\Models\ProcessRequestMapping;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProcessRequestMappingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get processes dynamically by name
        $erasureProcess = Process::where('name', 'like', '%cancellazione%')->orWhere('name', 'like', '%Erasure%')->first();
        $accessProcess = Process::where('name', 'like', '%accesso%')->orWhere('name', 'like', '%Access%')->first();
        $optOutProcess = Process::where('name', 'like', '%opposizione%')->orWhere('name', 'like', '%Opt%')->first();

        if (!$erasureProcess || !$accessProcess || !$optOutProcess) {
            $this->command->error('Some processes not found. Run process seeders first.');
            return;
        }

        $mappings = [
            [
                'request_type' => 'cancellazione',
                'process_id' => $erasureProcess->id,
                'is_suggested' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'request_type' => 'accesso',
                'process_id' => $accessProcess->id,
                'is_suggested' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'request_type' => 'opposizione',
                'process_id' => $optOutProcess->id,
                'is_suggested' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'request_type' => 'opposizione',
                'process_id' => $erasureProcess->id,
                'is_suggested' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($mappings as $mappingData) {
            ProcessRequestMapping::updateOrCreate(
                [
                    'request_type' => $mappingData['request_type'],
                    'process_id' => $mappingData['process_id'],
                ],
                [
                    'is_suggested' => $mappingData['is_suggested'],
                    'created_at' => $mappingData['created_at'],
                    'updated_at' => $mappingData['updated_at'],
                ]
            );
        }

        $this->command->info('Process Request Mappings seeded successfully!');
    }
}
