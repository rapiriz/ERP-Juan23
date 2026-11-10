<?php

namespace Tests;

use App\Modules\Login\Models\User;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $protect = function ($connection) {
            $connection->beforeExecuting(fn () => TestDatabaseSafety::check($connection));
        };
        $app['events']->listen(ConnectionEstablished::class, fn ($event) => $protect($event->connection));
        foreach ($app['db']->getConnections() as $connection) {
            $protect($connection);
        }

        return $app;
    }

    protected function signInAs(User $user): static
    {
        $token = Str::random(64);
        $user->forceFill(['current_session_id' => hash('sha256', $token)])->save();

        $this->actingAs($user);
        $this->withSession([
            'session_token' => $token,
            'last_activity' => now()->timestamp,
        ]);

        return $this;
    }
}
