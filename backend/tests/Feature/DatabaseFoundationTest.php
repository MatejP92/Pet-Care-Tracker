<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_persists_and_reads_a_user_in_the_test_database(): void
    {
        $this->assertSame('db_test', DB::selectOne('SELECT DATABASE() AS name')->name);

        $user = User::factory()->create(['email' => 'care@example.test']);

        $this->assertSame('care@example.test', $user->fresh()->email);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'care@example.test']);
    }

    public function test_the_test_user_cannot_read_the_development_database(): void
    {
        try {
            DB::select('SELECT COUNT(*) FROM db.users');
        } catch (QueryException $exception) {
            $this->assertContains((int) $exception->errorInfo[1], [1044, 1142]);

            return;
        }

        $this->fail('The test database user must not have access to the development database.');
    }
}
