<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProofOfDelivery;
use App\Models\Shipment;
use App\Models\TrackingLog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PodController extends Controller
{
    public function show($shipment)
    {
        $shipmentId = $shipment instanceof Shipment ? $shipment->id : $shipment;
        $pod = ProofOfDelivery::where('shipment_id', $shipmentId)->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => $pod->makeHidden(['photo_data', 'signature_data']),
        ]);
    }

    public function store(Request $request, $shipment)
    {
        return $this->savePod($request, $shipment);
    }

    public function submit(Request $request, $shipment)
    {
        return $this->savePod($request, $shipment);
    }

    public function photo($podId, $index = 0)
    {
        $pod = ProofOfDelivery::findOrFail($podId);
        $photos = json_decode($pod->photo_data ?? '[]', true);
        $dataUri = $photos[(int) $index] ?? null;

        return $this->streamDataUri($dataUri, 'Foto POD Terarsip');
    }

    public function signature($podId)
    {
        $pod = ProofOfDelivery::findOrFail($podId);

        return $this->streamDataUri($pod->signature_data ?? null, 'TTD Terverifikasi');
    }

    private function streamDataUri(?string $dataUri, string $fallbackText = 'Arsip POD')
    {
        if ($dataUri && preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.+)$/', $dataUri, $matches)) {
            $binary = base64_decode($matches[2], true);
            if ($binary !== false) {
                return response($binary, 200)
                    ->header('Content-Type', $matches[1])
                    ->header('Cache-Control', 'public, max-age=86400');
            }
        }

        if (function_exists('imagecreatetruecolor')) {
            $img = imagecreatetruecolor(400, 160);
            $bg = imagecolorallocate($img, 241, 245, 249);
            $border = imagecolorallocate($img, 203, 213, 225);
            $textColor = imagecolorallocate($img, 71, 85, 105);

            imagefill($img, 0, 0, $bg);
            imagerectangle($img, 0, 0, 399, 159, $border);
            imagestring($img, 4, 115, 72, $fallbackText, $textColor);

            ob_start();
            imagepng($img);
            $pngBinary = ob_get_clean();
            imagedestroy($img);

            return response($pngBinary, 200)
                ->header('Content-Type', 'image/png')
                ->header('Cache-Control', 'public, max-age=3600');
        }

        $minimalPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        return response($minimalPng, 200)
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'public, max-age=3600');
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

        if (!Schema::hasColumn('proof_of_deliveries', 'photo_data') || !Schema::hasColumn('proof_of_deliveries', 'signature_data')) {
            Schema::table('proof_of_deliveries', function (Blueprint $table) {
                if (!Schema::hasColumn('proof_of_deliveries', 'photo_data')) {
                    $table->longText('photo_data')->nullable();
                }
                if (!Schema::hasColumn('proof_of_deliveries', 'signature_data')) {
                    $table->longText('signature_data')->nullable();
                }
            });
        }

        return DB::transaction(function () use ($request, $shipmentModel) {
            $photoDataUris = [];
            $files = $request->hasFile('photos')
                ? $request->file('photos')
                : ($request->hasFile('photo') ? [$request->file('photo')] : []);

            foreach ($files as $file) {
                $mime = $file->getMimeType() ?: 'image/jpeg';
                $bytes = file_get_contents($file->getRealPath());
                $photoDataUris[] = 'data:' . $mime . ';base64,' . base64_encode($bytes);
            }

            $signatureDataUri = null;
            if ($request->filled('signature_base64')) {
                $rawSig = trim($request->input('signature_base64'));
                if (str_starts_with($rawSig, 'data:image/')) {
                    $signatureDataUri = $rawSig;
                } else {
                    $decodedBytes = base64_decode($rawSig, true);
                    if ($decodedBytes === false) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Format tanda tangan tidak valid.',
                        ], 422);
                    }
                    $signatureDataUri = 'data:image/png;base64,' . base64_encode($decodedBytes);
                }
            } elseif ($request->hasFile('signature')) {
                $sigFile = $request->file('signature');
                $sigMime = $sigFile->getMimeType() ?: 'image/png';
                $sigBytes = file_get_contents($sigFile->getRealPath());
                $signatureDataUri = 'data:' . $sigMime . ';base64,' . base64_encode($sigBytes);
            }

            $podData = [
                'shipment_id'        => $shipmentModel->id,
                'recipient_name'     => $request->recipient_name,
                'recipient_relation' => $request->recipient_relation,
                'notes'              => $request->notes,
                'delivered_at'       => now(),
                'photo_data'         => json_encode($photoDataUris),
                'signature_data'     => $signatureDataUri,
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

            $pod = ProofOfDelivery::updateOrCreate(
                ['shipment_id' => $shipmentModel->id],
                $podData
            );

            $host = preg_replace('/^http:/i', 'https:', rtrim($request->getSchemeAndHttpHost(), '/'));
            $photoUrls = [];
            foreach (array_keys($photoDataUris) as $idx) {
                $photoUrls[] = "{$host}/api/pods/{$pod->id}/photo/{$idx}";
            }
            $signatureUrl = "{$host}/api/pods/{$pod->id}/signature";

            $urlUpdates = [];
            if (Schema::hasColumn('proof_of_deliveries', 'photo_url')) {
                $urlUpdates['photo_url'] = json_encode($photoUrls);
            } elseif (Schema::hasColumn('proof_of_deliveries', 'photo_path')) {
                $urlUpdates['photo_path'] = json_encode($photoUrls);
            }

            if (Schema::hasColumn('proof_of_deliveries', 'signature_url')) {
                $urlUpdates['signature_url'] = $signatureUrl;
            } elseif (Schema::hasColumn('proof_of_deliveries', 'signature_path')) {
                $urlUpdates['signature_path'] = $signatureUrl;
            }

            if (!empty($urlUpdates)) {
                $pod->update($urlUpdates);
            }

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
                'data'    => $pod->fresh()->makeHidden(['photo_data', 'signature_data']),
            ], 201);
        });
    }
}