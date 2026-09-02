<?php

namespace App\Services;

use App\Models\School;
use App\Models\SchoolDesignation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SchoolManagementService
{
    public function query(array $filters): Builder
    {
        $query = $this->filteredQuery($filters)->withCount('coordinators');

        return $this->applySort($query, $filters);
    }

    public function create(array $attributes, array $coordinators, ?int $sourceSchoolId = null): School
    {
        return DB::transaction(function () use ($attributes, $coordinators, $sourceSchoolId) {
            $school = $sourceSchoolId
                ? School::query()->lockForUpdate()->find($sourceSchoolId)
                : null;

            if ($school && ! $school->is_managed) {
                $school->forceFill(array_merge($attributes, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]))->save();
            } else {
                $school = School::create($attributes);
            }

            $this->syncCoordinators($school, $coordinators);

            return $school->load('coordinators');
        });
    }

    public function update(School $school, array $attributes, array $coordinators): School
    {
        return DB::transaction(function () use ($school, $attributes, $coordinators) {
            $school->update($attributes);
            $this->syncCoordinators($school, $coordinators);

            return $school->refresh()->load('coordinators');
        });
    }

    public function row(School $school): array
    {
        return [
            'id' => $school->id,
            'school_code' => $school->school_code,
            'name' => $school->name,
            'address' => $school->address,
            'state' => $school->state,
            'district' => $school->district,
            'city' => $school->city,
            'pin_code' => $school->pin_code,
            'email' => $school->email,
            'mobile' => $school->mobile,
            'head_phone' => $school->head_phone,
            'is_active' => (bool) $school->is_active,
            'coordinators_count' => (int) ($school->coordinators_count ?? $school->coordinators->count()),
            'coordinators' => $school->coordinators->map(fn ($coordinator) => [
                'id' => $coordinator->id,
                'name' => $coordinator->name,
                'email' => $coordinator->email,
                'phone' => $coordinator->phone,
                'designation' => $coordinator->designation,
            ])->values()->all(),
            'created_at' => $school->created_at?->toIso8601String(),
            'updated_at' => $school->updated_at?->toIso8601String(),
        ];
    }

    public function summary(array $filters): array
    {
        $query = $this->filteredQuery($filters);

        return [
            'matched' => (clone $query)->count(),
            'active' => (clone $query)->where('is_active', true)->count(),
            'inactive' => (clone $query)->where('is_active', false)->count(),
            'with_coordinators' => (clone $query)->has('coordinators')->count(),
            'states' => (clone $query)->whereNotNull('state')->where('state', '!=', '')->distinct()->count('state'),
        ];
    }

    public function metadata(): array
    {
        $distinct = fn (string $column): Collection => School::managed()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->values();

        return [
            'states' => collect(User::indianStates())
                ->merge($distinct('state'))
                ->unique()
                ->values()
                ->all(),
            'districts' => $distinct('district')->all(),
            'cities' => $distinct('city')->all(),
            'schoolDesignations' => SchoolDesignation::active()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (SchoolDesignation $designation) => [
                    'id' => $designation->id,
                    'name' => $designation->name,
                ])
                ->all(),
        ];
    }

    public function filterLabels(array $filters): array
    {
        $labels = [];

        if (isset($filters['search'])) {
            $labels[] = 'Search: '.$filters['search'];
        }

        foreach (['state' => 'State', 'district' => 'District', 'city' => 'City'] as $key => $label) {
            if (isset($filters[$key])) {
                $labels[] = "{$label}: {$filters[$key]}";
            }
        }

        if (isset($filters['status'])) {
            $labels[] = $filters['status'] === 'active' ? 'Active schools' : 'Inactive schools';
        }

        if (isset($filters['has_coordinators'])) {
            $labels[] = $filters['has_coordinators'] === 'yes' ? 'Has coordinators' : 'No coordinators';
        }

        foreach (['date_from' => 'Added from', 'date_to' => 'Added to'] as $key => $label) {
            if (isset($filters[$key])) {
                $labels[] = "{$label}: {$filters[$key]}";
            }
        }

        return $labels ?: ['All managed schools'];
    }

    private function filteredQuery(array $filters): Builder
    {
        $query = School::query()->managed();

        if (isset($filters['search'])) {
            $term = trim($filters['search']);
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
            $like = "%{$escaped}%";

            $query->where(function (Builder $query) use ($like) {
                $query->where('school_code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('address', 'like', $like)
                    ->orWhere('state', 'like', $like)
                    ->orWhere('district', 'like', $like)
                    ->orWhere('city', 'like', $like)
                    ->orWhere('pin_code', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('mobile', 'like', $like)
                    ->orWhere('head_phone', 'like', $like)
                    ->orWhereHas('coordinators', function (Builder $coordinator) use ($like) {
                        $coordinator->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('phone', 'like', $like)
                            ->orWhere('designation', 'like', $like);
                    });
            });
        }

        foreach (['state', 'district', 'city'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (isset($filters['status'])) {
            $query->where('is_active', $filters['status'] === 'active');
        }

        if (($filters['has_coordinators'] ?? null) === 'yes') {
            $query->has('coordinators');
        }

        if (($filters['has_coordinators'] ?? null) === 'no') {
            $query->doesntHave('coordinators');
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    private function applySort(Builder $query, array $filters): Builder
    {
        $direction = $filters['direction'] ?? 'desc';

        return match ($filters['sort'] ?? 'created_at') {
            'name' => $query->orderBy('name', $direction)->orderBy('id'),
            'school_code' => $query->orderBy('school_code', $direction)->orderBy('id'),
            'state' => $query->orderBy('state', $direction)->orderBy('district')->orderBy('city')->orderBy('name'),
            'city' => $query->orderBy('city', $direction)->orderBy('name'),
            'coordinators' => $query->orderBy('coordinators_count', $direction)->orderBy('name'),
            default => $query->orderBy('created_at', $direction)->orderBy('id'),
        };
    }

    private function syncCoordinators(School $school, array $coordinators): void
    {
        $school->coordinators()->delete();

        foreach (array_values($coordinators) as $index => $coordinator) {
            $school->coordinators()->create([
                'name' => $coordinator['name'],
                'email' => $coordinator['email'] ?? null,
                'phone' => $coordinator['phone'] ?? null,
                'designation' => $coordinator['designation'] ?? null,
                'sort_order' => $index + 1,
            ]);
        }
    }
}
