<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesReferenceData;
use App\Models\Office;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OfficeController extends Controller
{
    use ManagesReferenceData;

    public function index(): View
    {
        $this->authorizeReferenceData();

        return view('offices.index', ['types' => Office::TYPES]);
    }

    /** JSON feed for the DataTable on the index page. */
    public function data(Request $request): JsonResponse
    {
        $this->authorizeReferenceData();
        $me = $request->user();

        $rows = Office::query()
            ->with(['region', 'parent', 'provinces'])
            ->withCount('users')
            ->orderBy('region_id')
            ->orderBy('office_type')   // enum order: regional first, then IMOs
            ->orderBy('name')
            ->get()
            ->map(fn (Office $o) => [
                'id'            => $o->id,
                'code'          => $o->code,
                'name'          => $o->name,
                'region'        => $o->region?->name,
                'type'          => $o->office_type,
                'type_label'    => Office::TYPES[$o->office_type] ?? $o->office_type,
                'parent'        => $o->parent?->code,
                'provinces'     => $o->provinces->sortBy('code')->map(fn (Province $p) => ['code' => $p->code, 'name' => $p->name])->values(),
                'users_count'   => $o->users_count,
                'is_active'     => (bool) $o->is_active,
                'is_own_office' => (int) $o->id === (int) $me->office_id,
                'edit_url'      => route('offices.edit', $o),
                'status_url'    => route('offices.status', $o),
            ])
            ->values();

        return response()->json(['data' => $rows]);
    }

    public function create(): View
    {
        $this->authorizeReferenceData();

        return view('offices.create', ['office' => new Office] + $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeReferenceData();
        $this->normalizeCode($request);

        $data = $request->validate($this->rules());

        $office = DB::transaction(function () use ($data, $request) {
            $office = new Office(collect($data)->except('provinces')->all());
            $office->parent_office_id = $data['office_type'] === 'regional' ? null : ($data['parent_office_id'] ?? null);
            $office->is_active = $request->boolean('is_active');
            $office->save();

            $office->provinces()->sync($data['provinces'] ?? []);

            return $office;
        });

        return redirect()
            ->route('offices.index')
            ->with('status', __('Office :name was created.', ['name' => $office->name]));
    }

    public function edit(Office $office): View
    {
        $this->authorizeReferenceData();

        return view('offices.edit', ['office' => $office] + $this->formData($office));
    }

    public function update(Request $request, Office $office): RedirectResponse
    {
        $this->authorizeReferenceData();
        $this->normalizeCode($request);

        $data = $request->validate($this->rules($office));

        // A regional office that still has IMOs under it cannot become an IMO itself.
        if ($data['office_type'] === 'imo' && $office->children()->exists()) {
            throw ValidationException::withMessages([
                'office_type' => __('This office has IMOs under it, so it must stay a regional office.'),
            ]);
        }

        DB::transaction(function () use ($data, $request, $office) {
            $office->fill(collect($data)->except('provinces')->all());
            $office->parent_office_id = $data['office_type'] === 'regional' ? null : ($data['parent_office_id'] ?? null);
            $office->is_active = $request->boolean('is_active');

            // Admins can't deactivate the office they belong to.
            if ((int) $office->id === (int) $request->user()->office_id) {
                $office->is_active = true;
            }

            $office->save();
            $office->provinces()->sync($data['provinces'] ?? []);
        });

        return redirect()
            ->route('offices.index')
            ->with('status', __('Office :name was updated.', ['name' => $office->name]));
    }

    /** Activate / deactivate an office (offices are never hard-deleted). */
    public function toggleStatus(Request $request, Office $office): RedirectResponse|JsonResponse
    {
        $this->authorizeReferenceData();

        if ($office->is_active && (int) $office->id === (int) $request->user()->office_id) {
            $message = __('You cannot deactivate your own office.');

            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : back()->with('error', $message);
        }

        $office->is_active = ! $office->is_active;
        $office->save();

        $message = $office->is_active
            ? __('Office :name was activated.', ['name' => $office->name])
            : __('Office :name was deactivated.', ['name' => $office->name]);

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : back()->with('status', $message);
    }

    /* ------------------------------------------------------------------ */

    private function rules(?Office $office = null): array
    {
        return [
            'region_id'        => ['required', 'integer', 'exists:regions,id'],
            'office_type'      => ['required', Rule::in(array_keys(Office::TYPES))],
            // An IMO reports to a regional office; an office can't be its own parent.
            'parent_office_id' => array_filter([
                'nullable',
                'integer',
                Rule::exists('offices', 'id')->where('office_type', 'regional'),
                $office ? Rule::notIn([$office->id]) : null,
            ]),
            'code'             => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\-]+$/', Rule::unique('offices', 'code')->ignore($office?->id)],
            'name'             => ['required', 'string', 'max:150'],
            'is_active'        => ['sometimes', 'boolean'],
            'provinces'        => ['nullable', 'array'],
            'provinces.*'      => ['integer', 'distinct', 'exists:provinces,id'],
        ];
    }

    private function formData(?Office $office = null): array
    {
        return [
            'types'     => Office::TYPES,
            'regions'   => Region::query()->orderBy('code')->get(),
            'provinces' => Province::query()->orderBy('code')->get(),
            'parents'   => Office::query()
                ->where('office_type', 'regional')
                ->when($office, fn ($q) => $q->where('id', '!=', $office->id))
                ->orderBy('name')
                ->get(),
        ];
    }
}
