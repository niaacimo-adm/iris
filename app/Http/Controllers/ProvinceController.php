<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesReferenceData;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProvinceController extends Controller
{
    use ManagesReferenceData;

    public function index(): View
    {
        $this->authorizeReferenceData();

        return view('provinces.index', [
            'regions' => Region::query()->orderBy('code')->get(),
        ]);
    }

    /** JSON feed for the DataTable on the index page. */
    public function data(): JsonResponse
    {
        $this->authorizeReferenceData();

        // Provinces referenced by IA profiles (no model needed, we only check usage).
        $usedByProfiles = DB::table('ia_profiles')
            ->whereNotNull('province_id')
            ->distinct()
            ->pluck('province_id')
            ->flip();

        $rows = Province::query()
            ->with('region')
            ->withCount(['districts', 'offices'])
            ->orderBy('code')
            ->get()
            ->map(function (Province $p) use ($usedByProfiles) {
                $inUse = $p->districts_count > 0 || $p->offices_count > 0 || $usedByProfiles->has($p->id);

                return [
                    'id'              => $p->id,
                    'code'            => $p->code,
                    'name'            => $p->name,
                    'region'          => $p->region?->name,
                    'districts_count' => $p->districts_count,
                    'offices_count'   => $p->offices_count,
                    'can_delete'      => ! $inUse,
                    'delete_hint'     => $inUse ? __('It is still used by districts, offices or IA profiles.') : null,
                    'edit_url'        => route('provinces.edit', $p),
                    'delete_url'      => route('provinces.destroy', $p),
                ];
            })
            ->values();

        return response()->json(['data' => $rows]);
    }

    public function create(): View
    {
        $this->authorizeReferenceData();

        return view('provinces.create', [
            'province' => new Province,
            'regions'  => Region::query()->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeReferenceData();
        $this->normalizeCode($request);

        $province = Province::create($request->validate($this->rules()));

        return redirect()
            ->route('provinces.index')
            ->with('status', __('Province :name was created.', ['name' => $province->name]));
    }

    public function edit(Province $province): View
    {
        $this->authorizeReferenceData();

        return view('provinces.edit', [
            'province' => $province,
            'regions'  => Region::query()->orderBy('code')->get(),
        ]);
    }

    public function update(Request $request, Province $province): RedirectResponse
    {
        $this->authorizeReferenceData();
        $this->normalizeCode($request);

        $province->update($request->validate($this->rules($province)));

        return redirect()
            ->route('provinces.index')
            ->with('status', __('Province :name was updated.', ['name' => $province->name]));
    }

    /** A province used by districts, offices or IA profiles cannot be deleted (foreign keys). */
    public function destroy(Request $request, Province $province): RedirectResponse|JsonResponse
    {
        $this->authorizeReferenceData();

        $inUse = $province->districts()->exists()
            || $province->offices()->exists()
            || DB::table('ia_profiles')->where('province_id', $province->id)->exists();

        if ($inUse) {
            $message = __('This province is still used by districts, offices or IA profiles.');

            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : back()->with('error', $message);
        }

        $province->delete();

        $message = __('Province :name was deleted.', ['name' => $province->name]);

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : redirect()->route('provinces.index')->with('status', $message);
    }

    /* ------------------------------------------------------------------ */

    private function rules(?Province $province = null): array
    {
        return [
            'region_id' => ['required', 'integer', 'exists:regions,id'],
            'code'      => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('provinces', 'code')->ignore($province?->id)],
            'name'      => ['required', 'string', 'max:100'],
        ];
    }
}
