<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // --- USUÁRIO 1 ---
        $user1 = User::factory()->create([
            'name' => 'Carlos Admin',
            'email' => 'carlos@gmail.com',
            'password' => Hash::make('123456'), // Forma mais moderna que bcrypt()
        ]);
        
        $token1 = $user1->createToken('MediaServiceToken')->plainTextToken;
        $this->command->info("Token do Carlos: $token1");


        // --- USUÁRIO 2 ---
        $user2 = User::factory()->create([
            'name' => 'Joabe Dev',
            'email' => 'joabe@gmail.com',
            'password' => Hash::make('123456'),
        ]);

        $token2 = $user2->createToken('MediaServiceToken')->plainTextToken;
        $this->command->info("Token do Joabe: $token2");
    }
}
