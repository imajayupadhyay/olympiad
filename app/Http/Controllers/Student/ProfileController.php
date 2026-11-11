<?php

namespace App\Http\Controllers\Student;

use App\Exceptions\HallTicketException;
use App\Http\Controllers\Controller;
use App\Models\ClassLevel;
use App\Models\User;
use App\Rules\ValidPhoneNumber;
use App\Services\HallTicketService;
use App\Services\PhoneNumberService;
use App\Services\StudentCustomFieldService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(private HallTicketService $hallTickets) {}

    public function index(Request $request): Response
    {
        $user = $request->user()->load('classLevel', 'hallTicket');

        return Inertia::render('Student/Profile/Index', [
            'profile' => $user,
            'classLevels' => ClassLevel::active(),
            'states' => User::indianStates(),
            'hallTicket' => $this->hallTickets->summary($user),
            'documents' => $this->documents($user),
            'lockedFields' => $this->hallTickets->isLocked($user) ? HallTicketService::LOCKED_FIELDS : [],
            'customFields' => app(StudentCustomFieldService::class)->forProfile($user),
        ]);
    }

    /**
     * Update personal details. While a hall ticket is pending or approved, only
     * the contact fields (email, phone) can change.
     */
    public function update(Request $request)
    {
        $user = $request->user();
        $locked = $this->hallTickets->isLocked($user);

        $rules = [
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
            'phone' => ['nullable', 'string', 'max:25', new ValidPhoneNumber],
            'class_level_id' => 'required|exists:class_levels,id',
            'dob' => 'nullable|date|before:today',
            'school' => 'nullable|string|max:200',
            'school_address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'pincode' => 'nullable|digits:6',
            'state' => 'nullable|string|max:100',
            'coordinator_name' => 'nullable|string|max:100',
            'coordinator_phone' => ['nullable', 'string', 'max:25', new ValidPhoneNumber],
        ];

        if ($locked) {
            $rules = array_diff_key($rules, array_flip(HallTicketService::LOCKED_FIELDS));
        }

        $customFields = app(StudentCustomFieldService::class);
        if (! $locked) {
            // Admin-defined details are verified too, so they lock with the rest.
            $rules = [...$rules, ...$customFields->rules()];
        }

        $data = $request->validate($rules, [], [
            'coordinator_name' => 'coordinator name',
            'coordinator_phone' => 'coordinator phone',
            ...$customFields->attributes(),
        ]);

        $custom = $data['custom'] ?? null;
        unset($data['custom']);

        if (array_key_exists('coordinator_phone', $data)) {
            $data['coordinator_phone'] = app(PhoneNumberService::class)->tryNormalize($data['coordinator_phone']);
        }

        // Re-verification flow not enforced — email is editable directly.
        $user->update($data);

        if (! $locked && is_array($custom)) {
            $customFields->save($user, $custom);
        }

        return back()->with('success', $locked
            ? 'Contact details updated. Your other details are locked while your hall ticket is under review or approved.'
            : 'Your profile has been updated.');
    }

    /** Upload or replace a hall-ticket document (live photo, passport photo, school ID card). */
    public function storeDocument(Request $request, string $type)
    {
        $request->validate(['file' => $this->hallTickets->documentRules($type)], [
            'file.dimensions' => 'The passport photo must be at least 200 × 200 pixels.',
        ], ['file' => HallTicketService::DOCUMENTS[$type]['label'] ?? 'file']);

        try {
            $this->hallTickets->storeDocument($request->user(), $type, $request->file('file'));
        } catch (HallTicketException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', HallTicketService::DOCUMENTS[$type]['label'].' saved.');
    }

    public function deleteDocument(Request $request, string $type)
    {
        try {
            $this->hallTickets->deleteDocument($request->user(), $type);
        } catch (HallTicketException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', HallTicketService::DOCUMENTS[$type]['label'].' removed.');
    }

    /** Stream the signed-in student's own document. */
    public function showDocument(Request $request, string $type)
    {
        return $this->hallTickets->documentResponse($request->user(), $type);
    }

    /** @return array<string, array{label:string, uploaded:bool, url:?string, is_pdf:bool}> */
    private function documents(User $user): array
    {
        return collect(HallTicketService::DOCUMENTS)->map(fn (array $doc, string $type) => [
            'label' => $doc['label'],
            'uploaded' => filled($user->getAttribute($type)),
            // Cache-buster so a replaced photo shows immediately.
            'url' => $user->getAttribute($type) ? route('student.profile.documents.show', ['type' => $type, 'v' => substr(md5((string) $user->getAttribute($type)), 0, 8)]) : null,
            'is_pdf' => str_ends_with((string) $user->getAttribute($type), '.pdf'),
        ])->all();
    }

    /**
     * Upload / replace the profile photo.
     */
    public function updatePhoto(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,jpg,png,webp|max:4096',
        ]);

        $user = $request->user();

        if ($user->photo && Storage::disk('public')->exists($user->photo)) {
            Storage::disk('public')->delete($user->photo);
        }

        $path = $request->file('photo')->store('avatars', 'public');
        $user->update(['photo' => $path]);

        return back()->with('success', 'Profile photo updated.');
    }

    /**
     * Remove the current profile photo.
     */
    public function deletePhoto(Request $request)
    {
        $user = $request->user();

        if ($user->photo && Storage::disk('public')->exists($user->photo)) {
            Storage::disk('public')->delete($user->photo);
        }

        $user->update(['photo' => null]);

        return back()->with('success', 'Profile photo removed.');
    }

    /**
     * Change account password.
     */
    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
            'password_changed_at' => now(),
        ]);

        return back()->with('success', 'Password changed successfully.');
    }
}
