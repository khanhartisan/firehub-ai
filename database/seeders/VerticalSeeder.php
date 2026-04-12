<?php

namespace Database\Seeders;

use App\Models\Vertical;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class VerticalSeeder extends Seeder
{
    use WithoutModelEvents;

    /** @var array<string, true> */
    private array $usedNames = [];

    public function run(): void
    {
        $path = resource_path('json/verticals.json');

        /** @var array{taxonomy_name?: string, pillars: list<array{id: int, name: string, categories: list<array{name: string, leaf_nodes: list<string>}>}>} $data */
        $data = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        foreach ($data['pillars'] as $pillar) {
            $pillarVertical = Vertical::query()->updateOrCreate(
                ['name' => $pillar['name']],
                ['parent_id' => null],
            );

            foreach ($pillar['categories'] as $category) {
                $categoryVertical = Vertical::query()->updateOrCreate(
                    ['name' => $category['name']],
                    ['parent_id' => $pillarVertical->id],
                );

                foreach ($category['leaf_nodes'] as $leaf) {
                    $name = $this->uniqueLeafName($leaf, $category['name']);

                    Vertical::query()->updateOrCreate(
                        ['name' => $name],
                        ['parent_id' => $categoryVertical->id],
                    );
                }
            }
        }
    }

    private function uniqueLeafName(string $leaf, string $categoryName): string
    {
        if (! isset($this->usedNames[$leaf])) {
            $this->usedNames[$leaf] = true;

            return $leaf;
        }

        $name = "{$leaf} ({$categoryName})";
        $this->usedNames[$name] = true;

        return $name;
    }
}
