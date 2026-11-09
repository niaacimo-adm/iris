<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesReferenceData;
use App\Models\Region;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RegionController extends Controller
{
    use ManagesReferenceData;

    public function index(): View
    {
        $this->authorizeReferenceData();

        return view('regions.index');
    }

    /** JSON feed for the DataTable on the index page. */
    public function data(): JsonResponse
    {
        $this->authorizeReferenceData();

        $rows = Region::query()
            ->withCount(['provinces', 'offices'])
            ->orderBy('code')
            ->get()
            ->map(function (Region $r) {
                $inUse = $r->provinces_count > 0 || $r->offices_count > 0;

                return [
                    'id'              => $r->id,
                    'code'            => $r->code,
                    'name'            => $r->name,
                    'provinces_count' => $r->provinces_count,
                    'offices_count'   => $r->offices_count,
                    'can_delete'      => ! $inUse,
                    'delete_hint'     => $inUse ? __('Move or remove its provinces and offices first.') : null,
                    'edit_url'        => route('regions.edit', $r),
                    'delete_url'      => route('regions.destroy', $r),
                ];
            })
            ->values();

        return response()->json(['data' => $rows]);
    }

    public function create(): View
    {
        $this->authorizeReferenceData();

        return view('regions.create', ['region' => new Region]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeReferenceData();
        $this->normalizeCode($request);

        $region = Region::create($request->validate($this->rules()));

        return redirect()
            ->route('regions.index')
            ->with('status', __('Region :name was created.', ['name' => $region->name]));
    }

    public function edit(Region $region): View
    {
        $this->authorizeReferenceData();

        return view('regions.edit', ['region' => $region]);
    }

    public function update(Request $request, Region $region): RedirectResponse
    {
        $this->authorizeReferenceData();
        $this->normalizeCode($request);

        $region->update($request->validate($this->rules($region)));

        return redirect()
            ->route('regions.index')
            ->with('status', __('Region :name was updated.', ['name' => $region->name]));
    }

    /** A region that still has provinces or offices cannot be deleted (foreign keys). */
    public function destroy(Request $request, Region $region): RedirectResponse|JsonResponse
    {
        $this->authorizeReferenceData();

        if ($region->provinces()->exists() || $region->offices()->exists()) {
            $message = __('This region still has provinces or offices. Move or remove them first.');

            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : back()->with('error', $message);
        }

        $region->delete();

        $message = __('Region :name was deleted.', ['name' => $region->name]);

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : redirect()->route('regions.index')->with('status', $message);
    }

    /* ------------------------------------------------------------------ */

    private function rules(?Region $region = null): array
    {
        return [
            'code' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('regions', 'code')->ignore($region?->id)],
            'name' => ['required', 'string', 'max:100'],
        ];
    }
}
