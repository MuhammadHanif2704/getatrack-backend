<?php

namespace Database\Seeders;

use App\Enums\ManifestStatus;
use App\Enums\ServiceType;
use App\Enums\ShipmentStatus;
use App\Models\Manifest;
use App\Models\ManifestDetail;
use App\Models\ProofOfDelivery;
use App\Models\Shipment;
use App\Models\TrackingLog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class LogisticsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@getatrack.com')->firstOrFail();
        $courier1 = User::where('email', 'budi.kurir@getatrack.com')->firstOrFail();
        $courier2 = User::where('email', 'andi.kurir@getatrack.com')->firstOrFail();

        // Buat file dummy PNG valid di storage agar POD seed bisa diakses via URL
        $samplePng = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );
        $samplePhotoPath = 'pod/photos/sample-pod-photo.png';
        $sampleSignaturePath = 'pod/signatures/sample-pod-signature.png';

        Storage::disk('public')->put($samplePhotoPath, $samplePng);
        Storage::disk('public')->put($sampleSignaturePath, $samplePng);

        // =====================================================================
        // DATA SHIPMENT UNTUK KURIR 1 (Budi Santoso - KUR-001)
        // Mencakup seluruh 5 tahap lifecycle untuk pengujian langsung di HP
        // =====================================================================

        // 1. Delivered (Sudah selesai & memiliki POD)
        $s1 = Shipment::updateOrCreate(
            ['awb_number' => 'GTX-20261117-001'],
            [
                'courier_id' => $courier1->id,
                'sender_name' => 'PT Nusantara Elektronik',
                'sender_phone' => '0215550101',
                'recipient_name' => 'Rina Kartika',
                'recipient_phone' => '081311112222',
                'origin_address' => 'Kawasan Industri Jababeka Tahap II, Cikarang, Bekasi',
                'destination_address' => 'Jl. Ahmad Yani No. 45, Marga Jaya, Bekasi Selatan',
                'service_type' => ServiceType::EXPRESS,
                'weight_kg' => 2.40,
                'status' => ShipmentStatus::DELIVERED,
            ]
        );
        $this->seedFullLogsForDelivered($s1, $admin, $courier1, Carbon::now()->subDays(2));
        ProofOfDelivery::updateOrCreate(
            ['shipment_id' => $s1->id],
            [
                'courier_id' => $courier1->id,
                'recipient_name' => 'Rina Kartika',
                'recipient_relation' => 'Yang Bersangkutan',
                'photo_path' => $samplePhotoPath,
                'signature_path' => $sampleSignaturePath,
                'latitude' => -6.2415860,
                'longitude' => 106.9924160,
                'notes' => 'Paket diterima langsung dalam kondisi segel utuh.',
                'delivered_at' => Carbon::now()->subDays(2)->addHours(6),
            ]
        );

        // 2. Delivered ke-2 (Untuk statistik grafik dashboard)
        $s2 = Shipment::updateOrCreate(
            ['awb_number' => 'GTX-20261117-002'],
            [
                'courier_id' => $courier1->id,
                'sender_name' => 'Toko Buku Gramedia Grand Metropolitan',
                'sender_phone' => '0218889012',
                'recipient_name' => 'Dimas Saputra',
                'recipient_phone' => '085733334444',
                'origin_address' => 'Jl. KH. Noer Ali, Pekayon Jaya, Bekasi Selatan',
                'destination_address' => 'Perumahan Harapan Indah Blok EF No. 12, Medan Satria, Bekasi',
                'service_type' => ServiceType::REGULAR,
                'weight_kg' => 1.15,
                'status' => ShipmentStatus::DELIVERED,
            ]
        );
        $this->seedFullLogsForDelivered($s2, $admin, $courier1, Carbon::now()->subDay());
        ProofOfDelivery::updateOrCreate(
            ['shipment_id' => $s2->id],
            [
                'courier_id' => $courier1->id,
                'recipient_name' => 'Siti Aminah (Ibu Penerima)',
                'recipient_relation' => 'Keluarga',
                'photo_path' => $samplePhotoPath,
                'signature_path' => $sampleSignaturePath,
                'latitude' => -6.1823100,
                'longitude' => 106.9748200,
                'notes' => 'Dititipkan kepada ibu penerima di teras rumah.',
                'delivered_at' => Carbon::now()->subDay()->addHours(5),
            ]
        );

        // 3. Received at Destination (Siap langsung diuji fitur POD di HP)
        $s3 = Shipment::updateOrCreate(
            ['awb_number' => 'GTX-20261117-003'],
            [
                'courier_id' => $courier1->id,
                'sender_name' => 'CV Mitra Logistik Mandiri',
                'sender_phone' => '0214440987',
                'recipient_name' => 'Hendri Wijaya',
                'recipient_phone' => '081255556666',
                'origin_address' => 'Jl. Raya Narogong KM 11, Bantar Gebang, Bekasi',
                'destination_address' => 'Ruko Galaxy City Blok RGA No. 28, Jakasetia, Bekasi Selatan',
                'service_type' => ServiceType::SAME_DAY,
                'weight_kg' => 3.50,
                'status' => ShipmentStatus::RECEIVED_AT_DESTINATION,
            ]
        );
        $this->seedLogsUpTo($s3, $admin, $courier1, ShipmentStatus::RECEIVED_AT_DESTINATION);

        // 4. Received at Destination ke-2 (Siap diuji POD ke-2)
        $s4 = Shipment::updateOrCreate(
            ['awb_number' => 'GTX-20261117-004'],
            [
                'courier_id' => $courier1->id,
                'sender_name' => 'Apotek Sehat Sentosa',
                'sender_phone' => '0217771234',
                'recipient_name' => 'Dr. Maya Anggraini',
                'recipient_phone' => '081877778888',
                'origin_address' => 'Jl. Cut Mutia No. 88, Sepanjang Jaya, Bekasi Timur',
                'destination_address' => 'Klinik Pratama Medika, Jl. Ir. H. Juanda No. 112, Bekasi Timur',
                'service_type' => ServiceType::EXPRESS,
                'weight_kg' => 0.85,
                'status' => ShipmentStatus::RECEIVED_AT_DESTINATION,
            ]
        );
        $this->seedLogsUpTo($s4, $admin, $courier1, ShipmentStatus::RECEIVED_AT_DESTINATION);

        // 5. Manifested (Siap diuji update ke Received at Destination)
        $s5 = Shipment::updateOrCreate(
            ['awb_number' => 'GTX-20261117-005'],
            [
                'courier_id' => $courier1->id,
                'sender_name' => 'Distributor Kopi Nusantara',
                'sender_phone' => '0216663210',
                'recipient_name' => 'Kafe Senja Kopi',
                'recipient_phone' => '082199990000',
                'origin_address' => 'Hub Sortir Utama Jakarta Timur, Cakung',
                'destination_address' => 'Jl. Boulevard Summarecon Bekasi No. 19, Marga Mulya, Bekasi Utara',
                'service_type' => ServiceType::REGULAR,
                'weight_kg' => 5.00,
                'status' => ShipmentStatus::MANIFESTED,
            ]
        );
        $this->seedLogsUpTo($s5, $admin, $courier1, ShipmentStatus::MANIFESTED);

        // 6. Picked Up (Siap diuji scan manifest / update ke Manifested)
        $s6 = Shipment::updateOrCreate(
            ['awb_number' => 'GTX-20261117-006'],
            [
                'courier_id' => $courier1->id,
                'sender_name' => 'UD Berkah Textile',
                'sender_phone' => '0213338765',
                'recipient_name' => 'Boutique Azzahra',
                'recipient_phone' => '085612123434',
                'origin_address' => 'Jl. Sultan Agung No. 24, Medan Satria, Bekasi',
                'destination_address' => 'Pasar Proyek Trade Center Lt. 1 Blok B-14, Bekasi Timur',
                'service_type' => ServiceType::REGULAR,
                'weight_kg' => 4.20,
                'status' => ShipmentStatus::PICKED_UP,
            ]
        );
        $this->seedLogsUpTo($s6, $admin, $courier1, ShipmentStatus::PICKED_UP);

        // 7. AWB Created (Siap diuji scan pertama kali -> Picked Up)
        $s7 = Shipment::updateOrCreate(
            ['awb_number' => 'GTX-20261117-007'],
            [
                'courier_id' => $courier1->id,
                'sender_name' => 'PT Prima Sparepart Otomotif',
                'sender_phone' => '0219994567',
                'recipient_name' => 'Bengkel Maju Jaya Motor',
                'recipient_phone' => '081345456767',
                'origin_address' => 'Kawasan Industri MM2100 Blok G-5, Cikarang Barat',
                'destination_address' => 'Jl. Raya Tambun No. 73, Tambun Selatan, Kabupaten Bekasi',
                'service_type' => ServiceType::EXPRESS,
                'weight_kg' => 6.75,
                'status' => ShipmentStatus::AWB_CREATED,
            ]
        );
        $this->seedLogsUpTo($s7, $admin, $courier1, ShipmentStatus::AWB_CREATED);

        // 8. AWB Created ke-2 (Pending)
        $s8 = Shipment::updateOrCreate(
            ['awb_number' => 'GTX-20261117-008'],
            [
                'courier_id' => $courier1->id,
                'sender_name' => 'Studio Desain Kreatif',
                'sender_phone' => '081210102020',
                'recipient_name' => 'Fajar Nugroho',
                'recipient_phone' => '087830304040',
                'origin_address' => 'Jl. Kemang Pratama Raya No. 10, Rawalumbu, Bekasi',
                'destination_address' => 'Grand Wisata Cluster Celebration Blok AA2 No. 9, Tambun',
                'service_type' => ServiceType::SAME_DAY,
                'weight_kg' => 1.50,
                'status' => ShipmentStatus::AWB_CREATED,
            ]
        );
        $this->seedLogsUpTo($s8, $admin, $courier1, ShipmentStatus::AWB_CREATED);

        // =====================================================================
        // MANIFEST UNTUK KURIR 1
        // =====================================================================
        $manifest1 = Manifest::updateOrCreate(
            ['manifest_number' => 'MNF-20261117-01'],
            [
                'courier_id' => $courier1->id,
                'origin_hub' => 'Hub Sortir Bekasi Barat',
                'destination_hub' => 'Drop Point Bekasi Selatan & Timur',
                'manifest_date' => Carbon::today()->toDateString(),
                'status' => ManifestStatus::IN_TRANSIT,
                'notes' => 'Manifest pengiriman reguler & express sesi pagi.',
            ]
        );

        ManifestDetail::updateOrCreate(
            ['manifest_id' => $manifest1->id, 'shipment_id' => $s3->id],
            ['scanned_at' => Carbon::now()->subHours(3)]
        );
        ManifestDetail::updateOrCreate(
            ['manifest_id' => $manifest1->id, 'shipment_id' => $s4->id],
            ['scanned_at' => Carbon::now()->subHours(3)]
        );
        ManifestDetail::updateOrCreate(
            ['manifest_id' => $manifest1->id, 'shipment_id' => $s5->id],
            ['scanned_at' => null]
        );

        $manifest2 = Manifest::updateOrCreate(
            ['manifest_number' => 'MNF-20261117-02'],
            [
                'courier_id' => $courier1->id,
                'origin_hub' => 'Hub Cikarang Industrial',
                'destination_hub' => 'Drop Point Tambun & Bekasi Kota',
                'manifest_date' => Carbon::today()->toDateString(),
                'status' => ManifestStatus::OPEN,
                'notes' => 'Manifest sesi siang menunggu verifikasi scan AWB.',
            ]
        );

        ManifestDetail::updateOrCreate(
            ['manifest_id' => $manifest2->id, 'shipment_id' => $s6->id],
            ['scanned_at' => null]
        );

        // =====================================================================
        // DATA SHIPMENT MILIK KURIR 2 (Andi Pratama - KUR-002)
        // Digunakan untuk memverifikasi bahwa Kurir 1 tidak bisa mengakses data ini
        // =====================================================================
        $otherShipment = Shipment::updateOrCreate(
            ['awb_number' => 'GTX-20261117-901'],
            [
                'courier_id' => $courier2->id,
                'sender_name' => 'PT Global Mandiri',
                'sender_phone' => '0215000111',
                'recipient_name' => 'Dewi Lestari',
                'recipient_phone' => '081288889999',
                'origin_address' => 'Hub Jakarta Selatan, TB Simatupang',
                'destination_address' => 'Jl. Margonda Raya No. 210, Depok',
                'service_type' => ServiceType::EXPRESS,
                'weight_kg' => 2.00,
                'status' => ShipmentStatus::PICKED_UP,
            ]
        );
        $this->seedLogsUpTo($otherShipment, $admin, $courier2, ShipmentStatus::PICKED_UP);
    }

    private function seedFullLogsForDelivered(Shipment $shipment, User $admin, User $courier, Carbon $baseTime): void
    {
        if ($shipment->trackingLogs()->count() > 0) {
            return;
        }

        $steps = [
            [
                'status' => ShipmentStatus::AWB_CREATED,
                'updated_by' => $admin->id,
                'description' => 'Nomor resi AWB dibuat dan ditugaskan kepada kurir.',
                'location' => 'Sistem Pusat GeTaTrack',
                'time' => $baseTime->copy(),
            ],
            [
                'status' => ShipmentStatus::PICKED_UP,
                'updated_by' => $courier->id,
                'description' => 'Paket telah dijemput dari alamat pengirim.',
                'location' => 'Titik Jemput Pengirim',
                'time' => $baseTime->copy()->addHour(),
            ],
            [
                'status' => ShipmentStatus::MANIFESTED,
                'updated_by' => $courier->id,
                'description' => 'Paket telah masuk dalam manifest pengiriman.',
                'location' => 'Hub Sortir Bekasi Barat',
                'time' => $baseTime->copy()->addHours(2),
            ],
            [
                'status' => ShipmentStatus::RECEIVED_AT_DESTINATION,
                'updated_by' => $courier->id,
                'description' => 'Paket tiba di area tujuan dan sedang dalam pengantaran.',
                'location' => 'Drop Point Bekasi',
                'time' => $baseTime->copy()->addHours(4),
            ],
            [
                'status' => ShipmentStatus::DELIVERED,
                'updated_by' => $courier->id,
                'description' => 'Paket telah diterima oleh penerima beserta bukti POD.',
                'location' => 'Alamat Tujuan Penerima',
                'time' => $baseTime->copy()->addHours(6),
            ],
        ];

        foreach ($steps as $step) {
            $log = new TrackingLog([
                'shipment_id' => $shipment->id,
                'updated_by' => $step['updated_by'],
                'status' => $step['status'],
                'description' => $step['description'],
                'location' => $step['location'],
                'latitude' => -6.2382699,
                'longitude' => 106.9755726,
            ]);
            $log->created_at = $step['time'];
            $log->updated_at = $step['time'];
            $log->save();
        }
    }

    private function seedLogsUpTo(Shipment $shipment, User $admin, User $courier, ShipmentStatus $targetStatus): void
    {
        if ($shipment->trackingLogs()->count() > 0) {
            return;
        }

        $allSteps = [
            [
                'status' => ShipmentStatus::AWB_CREATED,
                'updated_by' => $admin->id,
                'description' => 'Nomor resi AWB dibuat dan ditugaskan kepada kurir.',
                'location' => 'Sistem Pusat GeTaTrack',
                'offset_hours' => 5,
            ],
            [
                'status' => ShipmentStatus::PICKED_UP,
                'updated_by' => $courier->id,
                'description' => 'Paket telah dijemput dari alamat pengirim.',
                'location' => 'Titik Jemput Pengirim',
                'offset_hours' => 4,
            ],
            [
                'status' => ShipmentStatus::MANIFESTED,
                'updated_by' => $courier->id,
                'description' => 'Paket telah terverifikasi di dalam manifest keberangkatan.',
                'location' => 'Hub Sortir Bekasi Barat',
                'offset_hours' => 3,
            ],
            [
                'status' => ShipmentStatus::RECEIVED_AT_DESTINATION,
                'updated_by' => $courier->id,
                'description' => 'Paket tiba di hub tujuan dan siap diantar ke penerima.',
                'location' => 'Drop Point Tujuan Bekasi',
                'offset_hours' => 1,
            ],
        ];

        foreach ($allSteps as $step) {
            $time = Carbon::now()->subHours($step['offset_hours']);
            $log = new TrackingLog([
                'shipment_id' => $shipment->id,
                'updated_by' => $step['updated_by'],
                'status' => $step['status'],
                'description' => $step['description'],
                'location' => $step['location'],
                'latitude' => -6.2382699,
                'longitude' => 106.9755726,
            ]);
            $log->created_at = $time;
            $log->updated_at = $time;
            $log->save();

            if ($step['status'] === $targetStatus) {
                break;
            }
        }
    }
}