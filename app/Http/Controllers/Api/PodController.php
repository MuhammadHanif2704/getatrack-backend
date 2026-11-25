<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProofOfDelivery;
use App\Models\Shipment;
use App\Models\TrackingLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class PodController extends Controller
{
    public function store(Request $request, $shipment)
    {
        return $this->savePod($request, $shipment);
    }

    public function submit(Request $request, $shipment)
    {
        return $this->savePod($request, $shipment);
    }

    private function savePod(Request $request, $shipment)
    {
        $shipmentModel = $shipment instanceof Shipment
            ? $shipment
            : Shipment::findOrFail($shipment);

        $request->validate([
            'recipient_name'     => 'required|string|max:255',
            'recipient_relation' => 'nullable|string|max:100',
            'notes'              => 'nullable|string|max:1000',
            'latitude'           => 'nullable|numeric',
            'longitude'          => 'nullable|numeric',
            'photos'             => 'nullable|array',
            'photos.*'           => 'file|max:8192',
            'photo'              => 'nullable|file|max:8192',
            'signature'          => 'nullable|file|max:5120',
            'signature_base64'   => 'nullable|string',
        ], [
            'recipient_name.required' => 'Nama penerima wajib diisi.',
        ]);

        if (!$request->hasFile('photos') && !$request->hasFile('photo')) {
            return response()->json([
                'success' => false,
                'message' => 'Foto bukti pengiriman wajib dilampirkan.',
            ], 422);
        }

        if (!$request->filled('signature_base64') && !$request->hasFile('signature')) {
            return response()->json([
                'success' => false,
                'message' => 'Tanda tangan penerima wajib dilampirkan.',
            ], 422);
        }

        return DB::transaction(function () use ($request, $shipmentModel) {
            $savedPhotos = [];

            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $file) {
                    $savedPhotos[] = $file->store('pods/photos', 'public');
                }
            } elseif ($request->hasFile('photo')) {
                $savedPhotos[] = $request->file('photo')->store('pods/photos', 'public');
            }

            $signatureRelativePath = null;
            if ($request->filled('signature_base64')) {
                $base64Str = $request->input('signature_base64');
                if (str_contains($base64Str, ',')) {
                    $base64Str = explode(',', $base64Str, 2)[1];
                }
                $decodedBytes = base64_decode($base64Str, true);
                if ($decodedBytes === false) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Format tanda tangan tidak valid.',
                    ], 422);
                }
                $signatureRelativePath = 'pods/signatures/' . uniqid('sig_', true) . '.png';
                Storage::disk('public')->put($signatureRelativePath, $decodedBytes);
            } elseif ($request->hasFile('signature')) {
                $signatureRelativePath = $request->file('signature')->store('pods/signatures', 'public');
            }

            $podData = [
                'shipment_id'        => $shipmentModel->id,
                'recipient_name'     => $request->recipient_name,
                'recipient_relation' => $request->recipient_relation,
                'notes'              => $request->notes,
                'delivered_at'       => now(),
            ];

            if (Schema::hasColumn('proof_of_deliveries', 'courier_id')) {
                $podData['courier_id'] = $request->user()->id;
            } elseif (Schema::hasColumn('proof_of_deliveries', 'user_id')) {
                $podData['user_id'] = $request->user()->id;
            }

            if (Schema::hasColumn('proof_of_deliveries', 'latitude')) {
                $podData['latitude'] = $request->latitude;
            }
            if (Schema::hasColumn('proof_of_deliveries', 'longitude')) {
                $podData['longitude'] = $request->longitude;
            }

            $encodedPhotos = json_encode($savedPhotos);
            if (Schema::hasColumn('proof_of_deliveries', 'photo_url')) {
                $podData['photo_url'] = $encodedPhotos;
            } elseif (Schema::hasColumn('proof_of_deliveries', 'photo_path')) {
                $podData['photo_path'] = $encodedPhotos;
            }

            if (Schema::hasColumn('proof_of_deliveries', 'signature_url')) {
                $podData['signature_url'] = $signatureRelativePath;
            } elseif (Schema::hasColumn('proof_of_deliveries', 'signature_path')) {
                $podData['signature_path'] = $signatureRelativePath;
            }

            $pod = ProofOfDelivery::updateOrCreate(
                ['shipment_id' => $shipmentModel->id],
                $podData
            );

            $shipmentModel->update([
                'status' => 'Delivered',
            ]);

            if (class_exists(TrackingLog::class)) {
                $logData = [
                    'shipment_id' => $shipmentModel->id,
                    'status'      => 'Delivered',
                    'location'    => $shipmentModel->destination_address ?? 'Lokasi Penerima',
                    'description' => 'Paket telah diterima oleh ' . $request->recipient_name .
                        ($request->recipient_relation ? " ({$request->recipient_relation})" : ''),
                ];

                if (Schema::hasColumn('tracking_logs', 'logged_at')) {
                    $logData['logged_at'] = now();
                }

                if (Schema::hasColumn('tracking_logs', 'updated_by')) {
                    $colType = Schema::getColumnType('tracking_logs', 'updated_by');
                    $isNumeric = in_array(strtolower($colType), ['int', 'integer', 'bigint', 'smallint', 'tinyint', 'mediumint'], true);
                    $logData['updated_by'] = $isNumeric
                        ? $request->user()->id
                        : ($request->user()->name ?? 'Kurir');
                }

                TrackingLog::forceCreate($logData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Proof of Delivery berhasil disimpan.',
                'data'    => $pod->fresh(),
            ], 201);
        });
    }
}