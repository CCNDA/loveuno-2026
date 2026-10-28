<?php

namespace Database\Factories;

use App\Models\Diary;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Diary>
 */
class DiaryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => '測試日記 ' . fake()->numberBetween(1, 9999),
            'body' => '測試內容',
            'visibility' => 'public',
            'published_on' => now()->toDateString(),
        ];
    }

    public function private(): static
    {
        return $this->state(['visibility' => 'private']);
    }
}
