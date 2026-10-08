<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GoogleIdentityMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_rollback_preserves_users_and_existing_passwords(): void
    {
        $firstGoogleUser = User::factory()->create([
            'google_id' => 'google-first',
            'password' => null,
        ]);
        $secondGoogleUser = User::factory()->create([
            'google_id' => 'google-second',
            'password' => null,
        ]);
        $passwordUser = User::factory()->create();
        $originalPassword = $passwordUser->password;

        $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();

        $this->assertDatabaseCount('users', 3);
        $this->assertFalse(Schema::hasColumn('users', 'google_id'));
        $passwordColumn = collect(Schema::getColumns('users'))->firstWhere('name', 'password');
        $this->assertFalse($passwordColumn['nullable']);

        $firstPassword = $firstGoogleUser->fresh()->password;
        $secondPassword = $secondGoogleUser->fresh()->password;
        $this->assertTrue(Hash::isHashed($firstPassword));
        $this->assertTrue(Hash::isHashed($secondPassword));
        $this->assertNotSame($firstPassword, $secondPassword);
        $this->assertFalse(Hash::check('password', $firstPassword));
        $this->assertSame($originalPassword, $passwordUser->fresh()->password);

        $this->artisan('migrate')->assertSuccessful();

        $this->assertTrue(Schema::hasColumn('users', 'google_id'));
        $passwordColumn = collect(Schema::getColumns('users'))->firstWhere('name', 'password');
        $this->assertTrue($passwordColumn['nullable']);
    }
}
