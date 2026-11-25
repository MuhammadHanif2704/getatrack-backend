<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Contact;
use App\Models\ContactCategory;
use App\Models\Country;
use App\Models\ServiceArea;
use App\Models\ServiceType;
use App\Models\ShipmentType;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $idCountry = Country::updateOrCreate(
            ['code' => 'ID'],
            ['name' => 'Indonesia', 'is_active' => true]
        );

        Country::updateOrCreate(
            ['code' => 'SG'],
            ['name' => 'Singapura', 'is_active' => true]
        );

        $bekasi = City::updateOrCreate(
            ['code' => 'BKS'],
            ['country_id' => $idCountry->id, 'name' => 'Kota & Kab. Bekasi', 'province' => 'Jawa Barat', 'is_active' => true]
        );

        $jaktim = City::updateOrCreate(
            ['code' => 'JKT-TM'],
            ['country_id' => $idCountry->id, 'name' => 'Jakarta Timur', 'province' => 'DKI Jakarta', 'is_active' => true]
        );

        $depok = City::updateOrCreate(
            ['code' => 'DPK'],
            ['country_id' => $idCountry->id, 'name' => 'Kota Depok', 'province' => 'Jawa Barat', 'is_active' => true]
        );

        $bandung = City::updateOrCreate(
            ['code' => 'BDG'],
            ['country_id' => $idCountry->id, 'name' => 'Kota Bandung', 'province' => 'Jawa Barat', 'is_active' => true]
        );

        $corp = ContactCategory::updateOrCreate(
            ['code' => 'CORP'],
            ['name' => 'Korporat / B2B', 'description' => 'Perusahaan kontrak dengan pengiriman rutin harian', 'is_active' => true]
        );

        $retail = ContactCategory::updateOrCreate(
            ['code' => 'RTL'],
            ['name' => 'Retail & UMKM', 'description' => 'Toko fisik, grosir, dan usaha kecil menengah', 'is_active' => true]
        );

        $mkt = ContactCategory::updateOrCreate(
            ['code' => 'MKT'],
            ['name' => 'Marketplace Seller', 'description' => 'Penjual e-commerce dengan pickup harian', 'is_active' => true]
        );

        ContactCategory::updateOrCreate(
            ['code' => 'IND'],
            ['name' => 'Individu / Reguler', 'description' => 'Pelanggan perorangan non-kontrak', 'is_active' => true]
        );

        Contact::updateOrCreate(
            ['code' => 'CNT-001'],
            [
                'contact_category_id' => $corp->id,
                'city_id' => $bekasi->id,
                'name' => 'PT Global Mandiri',
                'company_name' => 'PT Global Mandiri Logistics',
                'phone' => '0215000111',
                'email' => 'ops@globalmandiri.co.id',
                'address' => 'Kawasan Industri Jababeka II Blok CC No. 14, Cikarang, Bekasi',
                'postal_code' => '17530',
                'is_active' => true,
            ]
        );

        Contact::updateOrCreate(
            ['code' => 'CNT-002'],
            [
                'contact_category_id' => $retail->id,
                'city_id' => $bekasi->id,
                'name' => 'Boutique Azzahra',
                'company_name' => 'Azzahra Fashion',
                'phone' => '081210102020',
                'email' => 'azzahra.store@gmail.com',
                'address' => 'Pasar Proyek Trade Center Lt. 1 Blok B No. 12, Bekasi Timur',
                'postal_code' => '17113',
                'is_active' => true,
            ]
        );

        Contact::updateOrCreate(
            ['code' => 'CNT-003'],
            [
                'contact_category_id' => $mkt->id,
                'city_id' => $depok->id,
                'name' => 'Dewi Lestari',
                'company_name' => 'Lestari Pet & Home',
                'phone' => '081388990011',
                'email' => 'dewi.lestari@gmail.com',
                'address' => 'Jl. Margonda Raya No. 210, Kemiri Muka, Depok',
                'postal_code' => '16423',
                'is_active' => true,
            ]
        );

        ServiceType::updateOrCreate(
            ['code' => 'REG'],
            ['name' => 'Regular', 'sla_days' => '2 - 3 Hari', 'base_rate' => 10000, 'description' => 'Pengiriman standar ekonomis antar kota', 'is_active' => true]
        );

        ServiceType::updateOrCreate(
            ['code' => 'EXP'],
            ['name' => 'Express', 'sla_days' => '1 Hari (Besok Sampai)', 'base_rate' => 18000, 'description' => 'Prioritas pengiriman cepat keesokan hari', 'is_active' => true]
        );

        ServiceType::updateOrCreate(
            ['code' => 'SDS'],
            ['name' => 'Same Day', 'sla_days' => 'Hari yang Sama (6-8 Jam)', 'base_rate' => 25000, 'description' => 'Pengiriman instan dalam area Jabodetabek', 'is_active' => true]
        );

        ServiceType::updateOrCreate(
            ['code' => 'CRG'],
            ['name' => 'Kargo', 'sla_days' => '3 - 5 Hari', 'base_rate' => 35000, 'description' => 'Paket dimensi besar atau berat di atas 10 kg', 'is_active' => true]
        );

        ShipmentType::updateOrCreate(
            ['code' => 'PKG'],
            ['name' => 'Paket Reguler', 'handling_note' => 'Penanganan standar, susun rapi di armada', 'is_active' => true]
        );

        ShipmentType::updateOrCreate(
            ['code' => 'DOC'],
            ['name' => 'Dokumen', 'handling_note' => 'Simpan di kompartemen kering, jangan terlipat', 'is_active' => true]
        );

        ShipmentType::updateOrCreate(
            ['code' => 'ELC'],
            ['name' => 'Elektronik', 'handling_note' => 'Hindari benturan keras dan kelembapan', 'is_active' => true]
        );

        ShipmentType::updateOrCreate(
            ['code' => 'FRG'],
            ['name' => 'Fragile / Pecah Belah', 'handling_note' => 'Wajib posisi atas, dilarang ditindih muatan berat', 'is_active' => true]
        );

        ServiceArea::updateOrCreate(
            ['code' => 'AREA-BKS-01'],
            [
                'city_id' => $bekasi->id,
                'name' => 'Zona Cikarang & Tambun',
                'hub_name' => 'Hub Cikarang Industrial',
                'coverage_districts' => 'Cikarang Barat, Cikarang Utara, Tambun Selatan, Cibitung',
                'is_active' => true,
            ]
        );

        ServiceArea::updateOrCreate(
            ['code' => 'AREA-BKS-02'],
            [
                'city_id' => $bekasi->id,
                'name' => 'Zona Bekasi Kota & Galaxy',
                'hub_name' => 'Hub Sortir Bekasi Barat',
                'coverage_districts' => 'Bekasi Barat, Bekasi Selatan, Bekasi Timur, Jatiasih',
                'is_active' => true,
            ]
        );

        ServiceArea::updateOrCreate(
            ['code' => 'AREA-JKT-01'],
            [
                'city_id' => $jaktim->id,
                'name' => 'Zona Cakung & Duren Sawit',
                'hub_name' => 'Hub Transit Jakarta Timur',
                'coverage_districts' => 'Cakung, Duren Sawit, Pulo Gadung, Jatinegara',
                'is_active' => true,
            ]
        );
    }
}