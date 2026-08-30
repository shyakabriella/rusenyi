<?php

namespace Tests\Feature\API\Location;

use App\Models\Cell;
use App\Models\CollectionPoint;
use App\Models\District;
use App\Models\Province;
use App\Models\Sector;
use App\Models\User;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_location_hierarchy_can_be_created(): void
    {
        $province = Province::create([
            'name' => 'Western Province',
            'is_active' => true,
        ]);

        $district = District::create([
            'province_id' => $province->id,
            'name' => 'Nyamasheke',
            'is_active' => true,
        ]);

        $sector = Sector::create([
            'district_id' => $district->id,
            'name' => 'Gihombo',
            'is_active' => true,
        ]);

        $cell = Cell::create([
            'sector_id' => $sector->id,
            'name' => 'Rusenyi',
            'is_active' => true,
        ]);

        $village = Village::create([
            'cell_id' => $cell->id,
            'name' => 'Test Village',
            'is_active' => true,
        ]);

        $collectionPoint = CollectionPoint::create([
            'village_id' => $village->id,
            'name' => 'Rusenyi Collection Point',
            'code' => 'CP-RUS-001',
            'latitude' => -2.1234567,
            'longitude' => 29.1234567,
            'description' => 'Test coffee collection point.',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('provinces', [
            'id' => $province->id,
            'name' => 'Western Province',
        ]);

        $this->assertDatabaseHas('districts', [
            'id' => $district->id,
            'province_id' => $province->id,
            'name' => 'Nyamasheke',
        ]);

        $this->assertDatabaseHas('sectors', [
            'id' => $sector->id,
            'district_id' => $district->id,
            'name' => 'Gihombo',
        ]);

        $this->assertDatabaseHas('cells', [
            'id' => $cell->id,
            'sector_id' => $sector->id,
            'name' => 'Rusenyi',
        ]);

        $this->assertDatabaseHas('villages', [
            'id' => $village->id,
            'cell_id' => $cell->id,
            'name' => 'Test Village',
        ]);

        $this->assertDatabaseHas('collection_points', [
            'id' => $collectionPoint->id,
            'village_id' => $village->id,
            'code' => 'CP-RUS-001',
        ]);
    }

    public function test_location_relationships_work_correctly(): void
    {
        $province = Province::create([
            'name' => 'Western Province',
        ]);

        $district = District::create([
            'province_id' => $province->id,
            'name' => 'Nyamasheke',
        ]);

        $sector = Sector::create([
            'district_id' => $district->id,
            'name' => 'Gihombo',
        ]);

        $cell = Cell::create([
            'sector_id' => $sector->id,
            'name' => 'Rusenyi',
        ]);

        $village = Village::create([
            'cell_id' => $cell->id,
            'name' => 'Test Village',
        ]);

        $collectionPoint = CollectionPoint::create([
            'village_id' => $village->id,
            'name' => 'Gihombo Collection Point',
            'code' => 'CP-GIH-001',
        ]);

        $this->assertTrue(
            $province->districts->contains($district)
        );

        $this->assertTrue(
            $district->sectors->contains($sector)
        );

        $this->assertTrue(
            $sector->cells->contains($cell)
        );

        $this->assertTrue(
            $cell->villages->contains($village)
        );

        $this->assertTrue(
            $village->collectionPoints->contains($collectionPoint)
        );

        $this->assertSame(
            $province->id,
            $district->province->id
        );

        $this->assertSame(
            $district->id,
            $sector->district->id
        );

        $this->assertSame(
            $sector->id,
            $cell->sector->id
        );

        $this->assertSame(
            $cell->id,
            $village->cell->id
        );

        $this->assertSame(
            $village->id,
            $collectionPoint->village->id
        );
    }

    public function test_location_active_flags_are_boolean(): void
    {
        $province = Province::create([
            'name' => 'Western Province',
            'is_active' => true,
        ]);

        $district = District::create([
            'province_id' => $province->id,
            'name' => 'Nyamasheke',
            'is_active' => false,
        ]);

        $this->assertIsBool(
            $province->is_active
        );

        $this->assertTrue(
            $province->is_active
        );

        $this->assertIsBool(
            $district->is_active
        );

        $this->assertFalse(
            $district->is_active
        );
    }

    public function test_agent_can_be_assigned_to_multiple_collection_points(): void
    {
        $province = Province::create([
            'name' => 'Western Province',
        ]);

        $district = District::create([
            'province_id' => $province->id,
            'name' => 'Nyamasheke',
        ]);

        $sector = Sector::create([
            'district_id' => $district->id,
            'name' => 'Gihombo',
        ]);

        $cell = Cell::create([
            'sector_id' => $sector->id,
            'name' => 'Rusenyi',
        ]);

        $village = Village::create([
            'cell_id' => $cell->id,
            'name' => 'Village One',
        ]);

        $pointOne = CollectionPoint::create([
            'village_id' => $village->id,
            'name' => 'Collection Point One',
            'code' => 'CP-001',
        ]);

        $pointTwo = CollectionPoint::create([
            'village_id' => $village->id,
            'name' => 'Collection Point Two',
            'code' => 'CP-002',
        ]);

        $agent = User::factory()->create([
            'role' => 'agent',
            'status' => 'active',
            'is_active' => true,
        ]);

        $agent->collectionPoints()->attach(
            $pointOne->id,
            [
                'is_active' => true,
                'assigned_at' => now(),
            ]
        );

        $agent->collectionPoints()->attach(
            $pointTwo->id,
            [
                'is_active' => true,
                'assigned_at' => now(),
            ]
        );

        $agent->refresh();

        $this->assertCount(
            2,
            $agent->collectionPoints
        );

        $this->assertTrue(
            $agent->collectionPoints->contains($pointOne)
        );

        $this->assertTrue(
            $agent->collectionPoints->contains($pointTwo)
        );

        $this->assertDatabaseHas(
            'agent_collection_points',
            [
                'user_id' => $agent->id,
                'collection_point_id' => $pointOne->id,
                'is_active' => true,
            ]
        );
    }

    public function test_collection_point_can_find_assigned_agents(): void
    {
        $province = Province::create([
            'name' => 'Western Province',
        ]);

        $district = District::create([
            'province_id' => $province->id,
            'name' => 'Nyamasheke',
        ]);

        $sector = Sector::create([
            'district_id' => $district->id,
            'name' => 'Gihombo',
        ]);

        $cell = Cell::create([
            'sector_id' => $sector->id,
            'name' => 'Rusenyi',
        ]);

        $village = Village::create([
            'cell_id' => $cell->id,
            'name' => 'Village One',
        ]);

        $point = CollectionPoint::create([
            'village_id' => $village->id,
            'name' => 'Main Collection Point',
            'code' => 'CP-MAIN-001',
        ]);

        $agent = User::factory()->create([
            'role' => 'agent',
            'status' => 'active',
            'is_active' => true,
        ]);

        $point->agents()->attach(
            $agent->id,
            [
                'is_active' => true,
                'assigned_at' => now(),
            ]
        );

        $point->refresh();

        $this->assertCount(
            1,
            $point->agents
        );

        $this->assertSame(
            $agent->id,
            $point->agents->first()->id
        );
    }
}
