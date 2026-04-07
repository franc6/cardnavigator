<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

class DatabaseControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    #[TestDox('The index lists every seeder except DatabaseSeeder and prefers each seeder\'s label() value')]
    public function test_index_lists_seeders_excluding_database_seeder(): void
    {
        // Arrange
        $admin = $this->admin();

        // Act
        $response = $this->actingAs($admin)->get(route('admin.database.index'));

        // Assert
        $response->assertOk();
        $seeders = $response->viewData('seeders');
        $classes = $seeders->pluck('class')->all();
        $this->assertNotContains('DatabaseSeeder', $classes);
        $this->assertContains('CardSeeder', $classes);
        $this->assertContains('CategorySeeder', $classes);
        $this->assertContains('PercentageSeeder', $classes);
        $cardEntry = $seeders->firstWhere('class', 'CardSeeder');
        $this->assertSame('Example Cards', $cardEntry['label']);
    }

    // -------------------------------------------------------------------------
    // migrate
    // -------------------------------------------------------------------------

    #[TestDox('Migrate runs the artisan migrate --force command and reports output in the session')]
    public function test_migrate_runs_and_reports_output(): void
    {
        // Arrange
        $admin = $this->admin();

        // Act
        $response = $this->actingAs($admin)->post(route('admin.database.migrate'));

        // Assert
        $response->assertRedirect(route('admin.database.index'));
        $response->assertSessionHas('status', __('Migrations run successfully.'));
        $this->assertNotNull(session('output'));
    }

    // -------------------------------------------------------------------------
    // seed
    // -------------------------------------------------------------------------

    #[TestDox('Seed rejects names with non-letter characters')]
    public function test_seed_rejects_invalid_seeder_name(): void
    {
        // Arrange
        $admin = $this->admin();

        // Act
        $response = $this->actingAs($admin)
            ->from(route('admin.database.index'))
            ->post(route('admin.database.seed'), ['seeder' => 'Card-Seeder']);

        // Assert
        $response->assertSessionHasErrors('seeder');
    }

    #[TestDox('Seed aborts 422 when the seeder class does not exist')]
    public function test_seed_aborts_when_class_missing(): void
    {
        // Arrange
        $admin = $this->admin();

        // Act
        $response = $this->actingAs($admin)
            ->from(route('admin.database.index'))
            ->post(route('admin.database.seed'), ['seeder' => 'NonExistentSeeder']);

        // Assert
        $response->assertStatus(422);
    }

    #[TestDox('Seed runs the named seeder via artisan db:seed and reports output')]
    public function test_seed_runs_named_seeder(): void
    {
        // Arrange
        $admin = $this->admin();
        Artisan::call('migrate', ['--force' => true]);

        // Act
        $response = $this->actingAs($admin)->post(route('admin.database.seed'), ['seeder' => 'CategorySeeder']);

        // Assert
        $response->assertRedirect(route('admin.database.index'));
        $response->assertSessionHas('status', __(':seeder run successfully.', ['seeder' => 'CategorySeeder']));
        $this->assertNotNull(session('output'));
    }
}
