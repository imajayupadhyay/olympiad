<?php

namespace App\Support;

use App\Models\User;

class AdminPermissions
{
    public const ACTIONS = ['read', 'write', 'delete'];

    /**
     * Every admin module, in sidebar order. `section` is the sidebar category and the
     * grouping in the Roles permission matrix (AdminLayout renders the sidebar from this
     * list, shared as `admin_modules`). Keys are permanent: they are stored in
     * admin_role_permissions. Labels and sections can change freely.
     */
    public const MODULES = [
        'dashboard' => ['label' => 'Dashboard', 'section' => 'Overview', 'route' => 'admin.dashboard'],
        'reports' => ['label' => 'Student Reports', 'section' => 'Overview', 'route' => 'admin.reports.index'],
        'students' => ['label' => 'Students', 'section' => 'Students', 'route' => 'admin.users.index'],
        'hall_tickets' => ['label' => 'Hall Tickets', 'section' => 'Students', 'route' => 'admin.hall-tickets.index'],
        'support' => ['label' => 'Support Tickets', 'section' => 'Students', 'route' => 'admin.support.index'],
        'exams' => ['label' => 'Exams', 'section' => 'Exams', 'route' => 'admin.exams.index'],
        'questions' => ['label' => 'Question Bank', 'section' => 'Exams', 'route' => 'admin.questions.index'],
        'exam_sessions' => ['label' => 'Ongoing Exams', 'section' => 'Exams', 'route' => 'admin.exam-sessions.index'],
        'results' => ['label' => 'Results', 'section' => 'Results & Certificates', 'route' => 'admin.results'],
        'certificates' => ['label' => 'Certificates', 'section' => 'Results & Certificates', 'route' => 'admin.certificates'],
        'payments' => ['label' => 'Payments', 'section' => 'Payments & Offers', 'route' => 'admin.payments'],
        'receipts' => ['label' => 'Receipts & GST', 'section' => 'Payments & Offers', 'route' => 'admin.receipts.index'],
        'coupons' => ['label' => 'Coupons', 'section' => 'Payments & Offers', 'route' => 'admin.coupons'],
        'referrals' => ['label' => 'Referrals', 'section' => 'Payments & Offers', 'route' => 'admin.referrals'],
        'notifications' => ['label' => 'Notifications', 'section' => 'Communication', 'route' => 'admin.notifications'],
        'emails' => ['label' => 'Email Templates', 'section' => 'Communication', 'route' => 'admin.emails'],
        'content' => ['label' => 'Homepage Content', 'section' => 'Website', 'route' => 'admin.content'],
        'forms' => ['label' => 'Contact Leads', 'section' => 'Website', 'route' => 'admin.forms.index'],
        'schools' => ['label' => 'Schools', 'section' => 'Schools', 'route' => 'admin.schools.index'],
        'data_entry' => ['label' => 'Data Entry', 'section' => 'Schools', 'route' => 'admin.data-entry.index'],
        'school_designations' => ['label' => 'Designations', 'section' => 'Schools', 'route' => 'admin.school-designations.index'],
        'settings_subjects' => ['label' => 'Subjects', 'section' => 'Settings', 'route' => 'admin.settings.subjects'],
        'settings_categories' => ['label' => 'Question Categories', 'section' => 'Settings', 'route' => 'admin.settings.categories'],
        'settings_classes' => ['label' => 'Class Levels', 'section' => 'Settings', 'route' => 'admin.settings.classes'],
        'settings_hall_ticket' => ['label' => 'Hall Ticket Layout', 'section' => 'Settings', 'route' => 'admin.settings.hall-ticket'],
        'settings_receipts' => ['label' => 'Receipt Settings', 'section' => 'Settings', 'route' => 'admin.settings.receipts'],
        'staff_users' => ['label' => 'Staff Users', 'section' => 'Staff & Access', 'route' => 'admin.staff-users.index'],
        'roles' => ['label' => 'Roles & Permissions', 'section' => 'Staff & Access', 'route' => 'admin.roles.index'],
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
