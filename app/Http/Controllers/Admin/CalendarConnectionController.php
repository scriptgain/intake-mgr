<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CalendarConnection;
use App\Services\Calendar\CalendarManager;
use App\Services\Calendar\CalendarSettings;
use App\Services\Calendar\CalendarSync;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Per-staff calendar connections. Each staff member connects their OWN calendar
 * from the My Calendar page; OAuth state lives in the session and is verified on
 * callback. Apple uses a CalDAV credential form rather than OAuth.
 */
class CalendarConnectionController extends Controller
{
    public function __construct(private readonly CalendarManager $manager)
    {
    }

    /** The My Calendar page: connections, connect buttons, feed URL. */
    public function index(Request $request)
    {
        $user = $request->user();

        return view('admin.calendar.connections', [
            'connections' => $user->calendarConnections()->get()->keyBy('provider'),
            'enabledProviders' => $this->manager->enabledProviders(),
            'providerLabels' => CalendarConnection::PROVIDER_LABELS,
            'feedUrl' => route('calendar.feed', ['token' => $user->feedToken()]),
        ]);
    }

    /** Begin an OAuth connect (google | microsoft | nylas). */
    public function connect(Request $request, string $provider)
    {
        abort_unless(in_array($provider, ['google', 'microsoft', 'nylas'], true), 404);

        $impl = $this->manager->get($provider);
        abort_unless($impl && $impl->isEnabled(), 404);

        $state = Str::random(40);
        session(['calendar_oauth' => ['state' => $state, 'provider' => $provider]]);

        return redirect()->away($impl->authorizeUrl($request->user(), $state));
    }

    /** OAuth redirect target: exchange the code and store the connection. */
    public function callback(Request $request, string $provider)
    {
        $saved = session('calendar_oauth');
        session()->forget('calendar_oauth');

        abort_unless(
            is_array($saved) && ($saved['provider'] ?? null) === $provider
                && hash_equals((string) ($saved['state'] ?? ''), (string) $request->query('state')),
            403
        );

        if ($request->query('error')) {
            return redirect()->route('calendar.index')->with('warning', 'Calendar connection was cancelled.');
        }

        $impl = $this->manager->get($provider);
        abort_unless($impl, 404);

        $result = $impl->exchangeCode((string) $request->query('code'));

        if (! ($result['ok'] ?? false)) {
            return redirect()->route('calendar.index')->with('warning', 'Could not connect that calendar. Please try again.');
        }

        $this->store($request, $provider, $result);

        return redirect()->route('calendar.index')->with('status', CalendarConnection::PROVIDER_LABELS[$provider].' connected.');
    }

    /** Apple iCloud via CalDAV (Apple ID + app-specific password). */
    public function connectApple(Request $request)
    {
        abort_unless(CalendarSettings::enabled('apple'), 404);

        $data = $request->validate([
            'apple_email' => ['required', 'email'],
            'app_password' => ['required', 'string'],
        ]);

        $apple = $this->manager->get('apple');
        $result = $apple?->verify($data['apple_email'], $data['app_password']) ?? ['ok' => false];

        if (! ($result['ok'] ?? false)) {
            return back()->withErrors(['apple_email' => 'Could not sign in to iCloud with those details. Use an app-specific password.']);
        }

        CalendarConnection::updateOrCreate(
            ['user_id' => $request->user()->id, 'provider' => 'apple'],
            [
                'account_email' => $data['apple_email'],
                'caldav_url' => $result['caldav_url'] ?? null,
                'caldav_username' => $data['apple_email'],
                'caldav_password' => $data['app_password'],
                'remote_calendar_id' => $result['calendar_id'] ?? null,
                'status' => 'connected',
                'last_error' => null,
            ]
        );

        return redirect()->route('calendar.index')->with('status', 'Apple iCloud connected.');
    }

    public function disconnect(Request $request, CalendarConnection $connection)
    {
        abort_unless($connection->user_id === $request->user()->id, 403);

        // Remove the remote events we created, then delete locally (mappings
        // + busy blocks cascade via FK).
        $provider = $this->manager->for($connection);
        if ($provider) {
            foreach ($connection->syncedEvents as $mapping) {
                rescue(fn () => $provider->deleteEvent($connection, $mapping->remote_event_id), null, false);
            }
        }
        $connection->delete();

        return back()->with('status', 'Calendar disconnected.');
    }

    /** Pull busy times now for the current user's connections. */
    public function sync(Request $request, CalendarSync $sync)
    {
        $count = $sync->syncUser($request->user()->id);

        return back()->with('status', $count > 0 ? 'Synced '.$count.' calendar(s).' : 'Nothing to sync yet.');
    }

    private function store(Request $request, string $provider, array $result): void
    {
        CalendarConnection::updateOrCreate(
            ['user_id' => $request->user()->id, 'provider' => $provider],
            [
                'account_email' => $result['account_email'] ?? null,
                'access_token' => $result['access_token'] ?? null,
                'refresh_token' => $result['refresh_token'] ?? null,
                'token_expires_at' => isset($result['expires_in']) ? now()->addSeconds((int) $result['expires_in']) : null,
                'scopes' => $result['scope'] ?? null,
                'nylas_grant_id' => $result['nylas_grant_id'] ?? null,
                'remote_calendar_id' => $result['calendar_id'] ?? 'primary',
                'status' => 'connected',
                'last_error' => null,
            ]
        );
    }
}
