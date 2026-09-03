<?php

namespace App\Support;

use App\Models\User;

class AdminPermissions
{
    public const ACTIONS = ['read', 'write', 'delete'];

    public const MODULES = [
        'dashboard' => ['label' => 'Dashboard', 'section' => 'Main', 'route' => 'admin.dashboard'],
        'students' => ['label' => 'Students', 'section' => 'Main', 'route' => 'admin.users.index'],
        'reports' => ['label' => 'Reports', 'section' => 'Main', 'route' => 'admin.reports.index'],
        'staff_users' => ['label' => 'Users', 'section' => 'Access', 'route' => 'admin.staff-users.index'],
        'roles' => ['label' => 'Permission Management', 'section' => 'Access', 'route' => 'admin.roles.index'],
        'schools' => ['label' => 'Schools', 'section' => 'School Data', 'route' => 'admin.schools.index'],
        'data_entry' => ['label' => 'Data Entry', 'section' => 'School Data', 'route' => 'admin.data-entry.index'],
        'school_designations' => ['label' => 'School Designations', 'section' => 'School Data', 'route' => 'admin.school-designations.index'],
        'questions' => ['label' => 'Question Bank', 'section' => 'Exams', 'route' => 'admin.questions.index'],
        'exams' => ['label' => 'Exams', 'section' => 'Exams', 'route' => 'admin.exams.index'],
        'results' => ['label' => 'Results', 'section' => 'Exams', 'route' => 'admin.results'],
        'certificates' => ['label' => 'Certificates', 'section' => 'Exams', 'route' => 'admin.certificates'],
        'payments' => ['label' => 'Payments', 'section' => 'Finance & Comms', 'route' => 'admin.payments'],
        'receipts' => ['label' => 'Receipts', 'section' => 'Finance & Comms', 'route' => 'admin.receipts.index'],
        'coupons' => ['label' => 'Coupons', 'section' => 'Finance & Comms', 'route' => 'admin.coupons'],
        'referrals' => ['label' => 'Referrals', 'section' => 'Finance & Comms', 'route' => 'admin.referrals'],
        'notifications' => ['label' => 'Notifications', 'section' => 'Finance & Comms', 'route' => 'admin.notifications'],
        'support' => ['label' => 'Support', 'section' => 'Finance & Comms', 'route' => 'admin.support.index'],
        'emails' => ['label' => 'Emails', 'section' => 'Finance & Comms', 'route' => 'admin.emails'],
        'content' => ['label' => 'Content', 'section' => 'Finance & Comms', 'route' => 'admin.content'],
        'forms' => ['label' => 'Forms', 'section' => 'Finance & Comms', 'route' => 'admin.forms.index'],
        'settings_subjects' => ['label' => 'Subjects', 'section' => 'Settings', 'route' => 'admin.settings.subjects'],
        'settings_categories' => ['label' => 'Categories', 'section' => 'Settings', 'route' => 'admin.settings.categories'],
        'settings_classes' => ['label' => 'Class Levels', 'section' => 'Settings', 'route' => 'admin.settings.classes'],
        'settings_receipts' => ['label' => 'Receipt Settings', 'section' => 'Settings', 'route' => 'admin.settings.receipts'],
    ];

    public static function moduleOptions(): array
    {
        return collect(self::MODULES)
            ->map(fn (array $module, string $key) => [
                'key' => $key,
                'label' => $module['label'],
                'section' => $module['section'],
                'route' => $module['route'],
            ])
            ->values()
            ->all();
    }

    public static function emptyMatrix(): array
    {
        return collect(array_keys(self::MODULES))
            ->mapWithKeys(fn (string $module) => [$module => [
                'read' => false,
                'write' => false,
                'delete' => false,
            ]])
            ->all();
    }

    public static function fullMatrix(): array
    {
        return collect(array_keys(self::MODULES))
            ->mapWithKeys(fn (string $module) => [$module => [
                'read' => true,
                'write' => true,
                'delete' => true,
            ]])
            ->all();
    }

    public static function normalizeMatrix(array $permissions): array
    {
        $matrix = self::emptyMatrix();

        foreach ($matrix as $module => $actions) {
            $incoming = is_array($permissions[$module] ?? null) ? $permissions[$module] : [];

            $read = filter_var($incoming['read'] ?? false, FILTER_VALIDATE_BOOL);
            $write = filter_var($incoming['write'] ?? false, FILTER_VALIDATE_BOOL);
            $delete = filter_var($incoming['delete'] ?? false, FILTER_VALIDATE_BOOL);

            $matrix[$module] = [
                'read' => $read || $write || $delete,
                'write' => $write,
                'delete' => $delete,
            ];
        }

        return $matrix;
    }

    public static function matrixForUser(?User $user): array
    {
        if (! $user?->isAdmin()) {
            return self::emptyMatrix();
        }

        if ($user->isSuperAdmin()) {
            return self::fullMatrix();
        }

        $role = $user->relationLoaded('adminRole') ? $user->adminRole : $user->adminRole()->with('permissions')->first();

        if (! $role?->is_active) {
            return self::emptyMatrix();
        }

        return $role->permissionMatrix();
    }

    public static function allows(?User $user, string $module, string $action = 'read'): bool
    {
        if (! isset(self::MODULES[$module]) || ! in_array($action, self::ACTIONS, true)) {
            return false;
        }

        if (! $user?->isAdmin()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $matrix = self::matrixForUser($user);

        return (bool) ($matrix[$module][$action] ?? false);
    }

    public static function firstAllowedRoute(?User $user): ?string
    {
        if (! $user?->isAdmin()) {
            return null;
        }

        if (self::allows($user, 'dashboard', 'read')) {
            return route('admin.dashboard');
        }

        foreach (self::MODULES as $module => $definition) {
            if ($module === 'dashboard') {
                continue;
            }

            if (self::allows($user, $module, 'read')) {
                return route($definition['route']);
            }
        }

        return null;
    }
}
