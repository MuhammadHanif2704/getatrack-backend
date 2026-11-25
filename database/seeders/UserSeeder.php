<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Akun Admin (hanya untuk pengelolaan & pengujian API via Postman)
        User::updateOrCreate(
            ['email' => 'admin@getatrack.com'],
            [
                'name' => 'Admin Operasional GeTaTrack',
                'password' => Hash::make('password123'),
                'role' => UserRole::ADMIN,
                'courier_code' => null,
                'phone' => '081200000001',
                'vehicle_type' => null,
                'plate_number' => null,
                'is_active' => true,
            ]
        );

        // 2. Akun Kurir Utama untuk pengujian aplikasi mobile
        User::updateOrCreate(
            ['email' => 'budi.kurir@getatrack.com'],
            [
                'name' => 'Budi Santoso',
                'password' => Hash::make('password123'),
                'role' => UserRole::COURIER,
                'courier_code' => 'KUR-001',
                'phone' => '081234567890',
                'vehicle_type' => 'Motor Box',
                'plate_number' => 'B 4821 KTR',
                'is_active' => true,
            ]
        );

        // 3. Akun Kurir Kedua (untuk memvalidasi pembatasan akses antar kurir)
        User::updateOrCreate(
            ['email' => 'andi.kurir@getatrack.com'],
            [
                'name' => 'Andi Pratama',
                'password' => Hash::make('password123'),
                'role' => UserRole::COURIER,
                'courier_code' => 'KUR-002',
                'phone' => '081298765432',
                'vehicle_type' => 'Blind Van',
                'plate_number' => 'B 9104 GTR',
                'is_active' => true,
            ]
        );
    }
}