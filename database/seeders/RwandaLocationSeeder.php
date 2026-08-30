<?php

namespace Database\Seeders;

use App\Models\Cell;
use App\Models\District;
use App\Models\Province;
use App\Models\Sector;
use App\Models\Village;
use Illuminate\Database\Seeder;
use RuntimeException;

class RwandaLocationSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path(
            'data/rwanda-administrative.json'
        );

        if (!is_file($path)) {
            throw new RuntimeException(
                'Rwanda location dataset not found: ' .
                $path
            );
        }

        $json = file_get_contents($path);

        if ($json === false) {
            throw new RuntimeException(
                'Unable to read Rwanda location dataset.'
            );
        }

        $dataset = json_decode(
            $json,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $provinces =
            $dataset['provinces'] ?? [];

        if (!is_array($provinces)) {
            throw new RuntimeException(
                'Invalid Rwanda dataset: provinces array missing.'
            );
        }

        foreach (
            $provinces as $provinceData
        ) {
            $province =
                Province::updateOrCreate(
                    [
                        'name' =>
                            trim(
                                (string) $provinceData[
                                    'name'
                                ]
                            ),
                    ],
                    [
                        'is_active' =>
                            true,
                    ]
                );

            foreach (
                $provinceData[
                    'districts'
                ] ?? []
                as $districtData
            ) {
                $district =
                    District::updateOrCreate(
                        [
                            'province_id' =>
                                $province->id,

                            'name' =>
                                trim(
                                    (string) $districtData[
                                        'name'
                                    ]
                                ),
                        ],
                        [
                            'is_active' =>
                                true,
                        ]
                    );

                foreach (
                    $districtData[
                        'sectors'
                    ] ?? []
                    as $sectorData
                ) {
                    $sector =
                        Sector::updateOrCreate(
                            [
                                'district_id' =>
                                    $district->id,

                                'name' =>
                                    trim(
                                        (string) $sectorData[
                                            'name'
                                        ]
                                    ),
                            ],
                            [
                                'is_active' =>
                                    true,
                            ]
                        );

                    foreach (
                        $sectorData[
                            'cells'
                        ] ?? []
                        as $cellData
                    ) {
                        $cell =
                            Cell::updateOrCreate(
                                [
                                    'sector_id' =>
                                        $sector->id,

                                    'name' =>
                                        trim(
                                            (string) $cellData[
                                                'name'
                                            ]
                                        ),
                                ],
                                [
                                    'is_active' =>
                                        true,
                                ]
                            );

                        foreach (
                            $cellData[
                                'villages'
                            ] ?? []
                            as $villageData
                        ) {
                            Village::updateOrCreate(
                                [
                                    'cell_id' =>
                                        $cell->id,

                                    'name' =>
                                        trim(
                                            (string) $villageData[
                                                'name'
                                            ]
                                        ),
                                ],
                                [
                                    'is_active' =>
                                        true,
                                ]
                            );
                        }
                    }
                }
            }
        }

        $this->command?->info(
            sprintf(
                'Rwanda locations seeded: %d provinces, %d districts, %d sectors, %d cells, %d villages.',
                Province::count(),
                District::count(),
                Sector::count(),
                Cell::count(),
                Village::count()
            )
        );
    }
}
