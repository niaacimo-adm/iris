<?php

namespace App\Http\Controllers;

use App\Models\IaMapLot;
use App\Models\IaProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Map of the IA service areas (ia.view). The office rule comes from IaProfile: IMO users see their own office,
 * Super Admin / Regional Staff see all. Filtering and colouring are done in the browser so they feel instant.
 */
class IaMapController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('ia.view'), 403);

        return view('ia-profiles.map', [
            'focusId' => $request->integer('ia') ?: null,
            'lotStatuses' => IaMapLot::STATUSES,
        ]);
    }

    /** { ias: {id: {...}}, lots: GeoJSON FeatureCollection }. The stored geometry is stitched in as text (no decode/encode). */
    public function data(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('ia.view'), 403);

        $lots = IaMapLot::query()
            ->select(['id', 'ia_profile_id', 'lot_name', 'area_ha', 'lot_status', 'municity', 'geometry'])
            ->orderBy('id')
            ->get();

        $profiles = IaProfile::query()
            ->with('province:id,name')
            ->whereIn('id', $lots->pluck('ia_profile_id')->unique())
            ->get();

        $ias = [];
        foreach ($profiles as $p) {
            $ias[$p->id] = [
                'name' => $p->ia_name,
                'code' => $p->ia_code,
                'cis' => $p->cis_name,
                'province' => $p->province?->name,
                'province_id' => $p->province_id,
                'systems' => $p->systems(),
                'status' => $p->status,
                'status_label' => IaProfile::STATUSES[$p->status] ?? $p->status,
                'service_area' => $p->service_area_ha !== null ? (float) $p->service_area_ha : null,
                'url' => route('ia-profiles.show', $p),
            ];
        }

        $features = $lots->map(fn (IaMapLot $l) => '{"type":"Feature","properties":'.json_encode([
            'i' => $l->ia_profile_id,
            'n' => $l->lot_name,
            'a' => (float) $l->area_ha,
            'l' => $l->lot_status,
            'm' => $l->municity,
        ], JSON_UNESCAPED_UNICODE).',"geometry":'.$l->geometry.'}')->implode(',');

        $json = '{"ias":'.json_encode($ias, JSON_UNESCAPED_UNICODE).',"lots":{"type":"FeatureCollection","features":['.$features.']}}';

        return response($json, 200, ['Content-Type' => 'application/json']);
    }
}
