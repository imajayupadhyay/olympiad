<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SchoolDataEntryService
{
    public function filters(array $input): array
    {
        $validated = validator($input, [
            'search' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:100'],
            'queue' => ['nullable', 'in:all,incomplete,missing_district,missing_email,missing_phone,missing_coordinator,missing_pin,blocked'],
            'per_page' => ['nullable', 'integer', 'in:50,100,200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        $filters = collect($validated)
            ->reject(fn ($value) => $value === null || $value === '')
            ->all();

        $filters['queue'] ??= 'incomplete';
        $filters['per_page'] ??= 100;

        return $filters;
    }

    public function paginate(array $filters): array
    {
        $paginator = $this->query($filters)
            ->with('coordinators')
            ->paginate($filters['per_page'])
            ->withQueryString();

        return $this->payload($paginator);
    }

    public function updateRows(array $rows): array
    {
        $ids = collect($rows)->pluck('id')->all();

        return DB::transaction(function () use ($rows, $ids): array {
            $schools = School::query()
                ->managed()
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            abort_if($schools->count() !== count(array_unique($ids)), 404, 'One or more schools are not available for data entry.');

            foreach ($rows as $row) {
                /** @var \App\Models\School $school */
                $school = $schools->get($row['id']);
                $school->update([
                    'name' => $row['name'],
                    'address' => $row['address'] ?? null,
                    'state' => $row['state'],
                    'district' => $row['district'] ?? null,
                    'city' => $row['city'] ?? null,
                    'pin_code' => $row['pin_code'] ?? null,
                    'email' => $row['email'] ?? null,
                    'mobile' => $row['mobile'] ?? null,
                    'head_phone' => $row['head_phone'] ?? null,
                    'is_active' => (bool) $row['is_active'],
                    'is_managed' => true,
                ]);

                $school->coordinators()->delete();
                foreach (array_values($row['coordinators'] ?? []) as $index => $coordinator) {
                    $school->coordinators()->create([
                        'name' => $coordinator['name'],
                        'designation' => $coordinator['designation'] ?? null,
                        'phone' => $coordinator['phone'] ?? null,
                        'email' => $coordinator['email'] ?? null,
                        'sort_order' => $index + 1,
                    ]);
                }
            }

            return School::query()
                ->whereIn('id', $ids)
                ->with('coordinators')
                ->orderBy('school_code')
                ->get()
                ->map(fn (School $school): array => $this->row($school))
                ->values()
                ->all();
        });
    }

    public function summary(): array
    {
        $base = School::query()->managed();

        return [
            'total' => (clone $base)->count(),
            'incomplete' => $this->applyQueue((clone $base), 'incomplete')->count(),
            'missing_district' => $this->applyQueue((clone $base), 'missing_district')->count(),
            'missing_email' => $this->applyQueue((clone $base), 'missing_email')->count(),
            'missing_phone' => $this->applyQueue((clone $base), 'missing_phone')->count(),
            'missing_coordinator' => $this->applyQueue((clone $base), 'missing_coordinator')->count(),
            'missing_pin' => $this->applyQueue((clone $base), 'missing_pin')->count(),
            'blocked' => $this->applyQueue((clone $base), 'blocked')->count(),
        ];
    }

    public function states(): array
    {
        return School::managed()
            ->whereNotNull('state')
            ->where('state', '!=', '')
            ->distinct()
            ->orderBy('state')
            ->pluck('state')
            ->values()
            ->all();
    }

    public function queueOptions(): array
    {
        return [
            ['value' => 'incomplete', 'label' => 'Next incomplete'],
            ['value' => 'all', 'label' => 'All schools'],
            ['value' => 'missing_district', 'label' => 'Missing district'],
            ['value' => 'missing_email', 'label' => 'Missing email'],
            ['value' => 'missing_phone', 'label' => 'Missing phone'],
            ['value' => 'missing_coordinator', 'label' => 'No coordinator'],
            ['value' => 'missing_pin', 'label' => 'Missing PIN'],
            ['value' => 'blocked', 'label' => 'Blocked'],
        ];
    }

    private function query(array $filters): Builder
    {
        $query = School::query()->managed();
        $exactIdentityMatch = false;

        if (isset($filters['search'])) {
            $term = trim($filters['search']);
            $forcedIdentity = $this->forcedIdentity($term);

            if ($forcedIdentity !== null) {
                $query->where($forcedIdentity[0], $forcedIdentity[1]);
                $exactIdentityMatch = true;
            } elseif (School::managed()->where('school_code', $term)->exists()) {
                $query->where('school_code', $term);
                $exactIdentityMatch = true;
            } elseif (School::managed()->where('external_school_id', $term)->exists()) {
                $query->where('external_school_id', $term);
                $exactIdentityMatch = true;
            } else {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
                $like = "%{$escaped}%";

                $query->where(function (Builder $query) use ($like) {
                    $query->where('external_school_id', 'like', $like)
                        ->orWhere('school_code', 'like', $like)
                        ->orWhere('name', 'like', $like)
                        ->orWhere('address', 'like', $like)
                        ->orWhere('state', 'like', $like)
                        ->orWhere('district', 'like', $like)
                        ->orWhere('city', 'like', $like)
                        ->orWhere('pin_code', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('mobile', 'like', $like)
                        ->orWhere('head_phone', 'like', $like);
                });
            }
        }

        if (! $exactIdentityMatch && isset($filters['state'])) {
            $query->where('state', $filters['state']);
        }

        if (! $exactIdentityMatch) {
            $this->applyQueue($query, $filters['queue'] ?? 'incomplete');
        }

        return $query->orderBy('school_code')->orderBy('id');
    }

    private function forcedIdentity(string $term): ?array
    {
        if (preg_match('/\Acode\s*:\s*(.+)\z/i', $term, $matches)) {
            return ['school_code', trim($matches[1])];
        }

        if (preg_match('/\A(?:sid|schid)\s*:\s*(.+)\z/i', $term, $matches)) {
            return ['external_school_id', trim($matches[1])];
        }

        return null;
    }

    private function applyQueue(Builder $query, string $queue): Builder
    {
        return match ($queue) {
            'missing_district' => $this->blank($query, 'district'),
            'missing_email' => $this->blank($query, 'email'),
            'missing_phone' => $query->where(fn (Builder $query) => $this->blank($query, 'mobile')->orWhere(fn (Builder $query) => $this->blank($query, 'head_phone'))),
            'missing_coordinator' => $query->doesntHave('coordinators'),
            'missing_pin' => $this->blank($query, 'pin_code'),
            'blocked' => $query->where('is_active', false),
            'incomplete' => $query->where(function (Builder $query) {
                $query->where(fn (Builder $query) => $this->blank($query, 'district'))
                    ->orWhere(fn (Builder $query) => $this->blank($query, 'email'))
                    ->orWhere(fn (Builder $query) => $this->blank($query, 'mobile'))
                    ->orWhere(fn (Builder $query) => $this->blank($query, 'head_phone'))
                    ->orWhere(fn (Builder $query) => $this->blank($query, 'pin_code'))
                    ->orWhereDoesntHave('coordinators');
            }),
            default => $query,
        };
    }

    private function blank(Builder $query, string $column): Builder
    {
        return $query->where(fn (Builder $query) => $query->whereNull($column)->orWhere($column, ''));
    }

    private function payload(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => collect($paginator->items())
                ->map(fn (School $school): array => $this->row($school))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    private function row(School $school): array
    {
        return [
            'id' => $school->id,
            'external_school_id' => $school->external_school_id,
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
            'coordinators' => $school->coordinators
                ->map(fn ($coordinator): array => [
                    'name' => $coordinator->name,
                    'designation' => $coordinator->designation,
                    'phone' => $coordinator->phone,
                    'email' => $coordinator->email,
                ])
                ->values()
                ->all(),
            'updated_at' => $school->updated_at?->toIso8601String(),
        ];
    }
}
