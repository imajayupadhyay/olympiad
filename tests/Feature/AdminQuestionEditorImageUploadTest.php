<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminQuestionEditorImageUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_editor_image_for_question_options(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        $file = UploadedFile::fake()->image('option-diagram.png', 640, 360);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.questions.editor-images.store'), [
                'image' => $file,
            ]);

        $response
            ->assertOk()
            ->assertJsonStructure(['url', 'path'])
            ->assertJsonPath('path', fn (string $path) => str_starts_with($path, 'questions/editor/'));

        Storage::disk('public')->assertExists($response->json('path'));
        $this->assertStringStartsWith('/storage/questions/editor/', $response->json('url'));
    }

    public function test_editor_image_upload_requires_a_valid_image(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        $file = UploadedFile::fake()->create('notes.pdf', 12, 'application/pdf');

        $this->actingAs($admin)
            ->postJson(route('admin.questions.editor-images.store'), [
                'image' => $file,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');

        Storage::disk('public')->assertMissing('questions/editor/notes.pdf');
    }
}
