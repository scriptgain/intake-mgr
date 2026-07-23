<?php

namespace Tests\Feature;

use App\Models\CalendarBusyBlock;
use App\Models\CalendarConnection;
use App\Models\Setting;
use App\Models\User;
use App\Services\Calendar\CalendarSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Exercises the Nylas provider end to end with the HTTP layer faked: the
 * app-level credential test, the OAuth callback that stores the grant, and the
 * free/busy pull that feeds availability. No real Nylas account required.
 */
class NylasCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Past the first-run setup gate (EnsureSetup middleware).
        Setting::put('setup_complete', '1');

        // App-level Nylas credentials (DB-driven config, not .env).
        Setting::put(CalendarSettings::KEY_NYLAS_API_KEY, 'nyk_test_key');
        Setting::put(CalendarSettings::KEY_NYLAS_CLIENT_ID, 'client-abc');
        Setting::put(CalendarSettings::KEY_NYLAS_API_REGION, 'us');
        Setting::put(CalendarSettings::KEY_NYLAS_ENABLED, '1');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_credential_test_button_accepts_a_valid_api_key(): void
    {
        Http::fake([
            'api.us.nylas.com/v3/grants*' => Http::response(['data' => [], 'request_id' => 'r1'], 200),
        ]);

        $this->actingAs($this->admin())
            ->post(route('settings.calendar.test', ['provider' => 'nylas']))
            ->assertSessionHas('status');

        Http::assertSent(fn ($r) => str_contains($r->url(), '/v3/grants')
            && $r->hasHeader('Authorization', 'Bearer nyk_test_key'));
    }

    public function test_oauth_callback_stores_the_grant_id(): void
    {
        Http::fake([
            'api.us.nylas.com/v3/connect/token' => Http::response([
                'grant_id' => 'grant-123',
                'email' => 'tech@example.com',
            ], 200),
        ]);

        $admin = $this->admin();
        $state = 'state-token-xyz';

        $this->actingAs($admin)
            ->withSession(['calendar_oauth' => ['state' => $state, 'provider' => 'nylas']])
            ->get(route('calendar.callback', ['provider' => 'nylas', 'code' => 'auth-code', 'state' => $state]))
            ->assertRedirect(route('calendar.index'))
            ->assertSessionHas('status');

        $conn = CalendarConnection::where('user_id', $admin->id)->where('provider', 'nylas')->first();

        $this->assertNotNull($conn, 'connection was created');
        // Regression: the grant must land on nylas_grant_id, or every later
        // Nylas API call short-circuits with "no Nylas grant".
        $this->assertSame('grant-123', $conn->nylas_grant_id);
        $this->assertSame('tech@example.com', $conn->account_email);
        $this->assertSame('connected', $conn->status);
    }

    public function test_sync_now_caches_busy_blocks_from_free_busy(): void
    {
        Http::fake([
            'api.us.nylas.com/v3/grants/*/calendars/free-busy' => Http::response([
                'data' => [[
                    'email' => 'tech@example.com',
                    'time_slots' => [
                        ['start_time' => 1_800_000_000, 'end_time' => 1_800_003_600, 'status' => 'busy'],
                        ['start_time' => 1_800_010_000, 'end_time' => 1_800_013_600, 'status' => 'free'],
                    ],
                ]],
            ], 200),
        ]);

        $admin = $this->admin();
        $conn = CalendarConnection::create([
            'user_id' => $admin->id,
            'provider' => 'nylas',
            'account_email' => 'tech@example.com',
            'nylas_grant_id' => 'grant-123',
            'remote_calendar_id' => 'primary',
            'status' => 'connected',
        ]);

        $this->actingAs($admin)
            ->post(route('calendar.sync'))
            ->assertSessionHas('status');

        $blocks = CalendarBusyBlock::where('calendar_connection_id', $conn->id)->get();

        // Only the 'busy' slot is cached; 'free' is dropped.
        $this->assertCount(1, $blocks);
        $conn->refresh();
        $this->assertNull($conn->last_error);
        $this->assertNotNull($conn->last_synced_at);
    }
}
