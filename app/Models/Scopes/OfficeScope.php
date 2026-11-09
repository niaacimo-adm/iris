<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Keeps one IMO away from another IMO's data, automatically, on EVERY query of the model.
 *   region-wide role                        -> sees all offices
 *   office role                             -> only rows where office_id = own office
 *   no user on a web request / no office    -> sees nothing (fails closed)
 * Console commands (seeders, imports) are not filtered.
 */
class OfficeScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if ($user === null) {
            if (! app()->runningInConsole()) {
                $builder->whereRaw('1 = 0');
            }

            return;
        }

        if ($user->role?->data_scope === 'region') {
            return;
        }

        if (empty($user->office_id)) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('office_id'), $user->office_id);
    }
}
