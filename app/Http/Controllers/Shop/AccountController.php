<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Storefront customer accounts. Uses the 'customer' guard exclusively — nothing
 * here can ever touch the staff session.
 */
class AccountController extends Controller
{
    public function showLogin(Request $request)
    {
        return view('shop.account.login', [
            // Customer persona buttons, populated only from the allowlisted IP.
            'demoCustomers' => \App\Support\DemoPersonas::customersFor($request),
        ]);
    }

    /**
     * One-click sign-in as a seeded customer persona, so the account and order
     * history pages can be walked without typing credentials.
     *
     * IP-gated the same way as the admin developer login: the check is enforced
     * here, not just in the button's visibility, so this 404s from any other
     * address. The route carries no captcha middleware, so it bypasses the
     * shop's login captcha as a trusted shortcut.
     */
    public function demoLogin(Request $request, string $persona)
    {
        abort_unless(\App\Support\DemoPersonas::allowed($request), 404);

        $customer = \App\Support\DemoPersonas::resolveCustomer($persona);
        abort_if($customer === null, 404);

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return redirect()->intended(route('shop.account'));
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('customer')->attempt($data, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Those credentials do not match our records.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('shop.account'));
    }

    public function showRegister()
    {
        return view('shop.account.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // A guest who has ordered before already has a passwordless record —
        // claim it rather than colliding with the unique email index.
        $customer = Customer::withTrashed()->firstOrNew(['email' => $data['email']]);

        if ($customer->exists && $customer->has_account) {
            throw ValidationException::withMessages([
                'email' => 'An account with that email already exists. Please sign in.',
            ]);
        }

        $customer->fill([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'password' => $data['password'],
            'accepts_marketing' => $request->boolean('accepts_marketing'),
        ]);
        $customer->deleted_at = null;
        $customer->save();

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return redirect()->route('shop.account')->with('status', 'Welcome. Your account is ready.');
    }

    /*
    |--------------------------------------------------------------------------
    | Password reset (customer guard, dedicated 'customers' broker)
    |--------------------------------------------------------------------------
    */

    public function showForgot()
    {
        return view('shop.account.forgot');
    }

    /**
     * Issue a reset link. Always reports the same generic result so the form
     * cannot be used to probe which emails have an account.
     */
    public function sendResetLink(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        Password::broker('customers')->sendResetLink(['email' => $data['email']]);

        return back()->with('status', 'If an account matches that email, a password reset link is on its way.');
    }

    public function showReset(Request $request, string $token)
    {
        return view('shop.account.reset', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::broker('customers')->reset($data, function (Customer $customer, string $password) {
            $customer->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($customer));

            // A password reset also confirms control of the mailbox: sign in.
            Auth::guard('customer')->login($customer);
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'That reset link is invalid or has expired. Please request a new one.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->route('shop.account')->with('status', 'Your password has been reset.');
    }

    public function logout(Request $request)
    {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('shop.home');
    }

    public function index()
    {
        $customer = auth('customer')->user();

        return view('shop.account.index', [
            'customer' => $customer,
            'orders' => $customer->orders()->with('items')->limit(10)->get(),
            'defaultAddress' => $customer->addresses()->where('is_default', true)->first(),
        ]);
    }

    public function order(Order $order)
    {
        // Scoped to the signed-in customer: order numbers must not be walkable.
        abort_unless($order->customer_id === auth('customer')->id(), 404);

        $order->load(['items', 'fulfillments']);

        return view('shop.account.order', ['order' => $order]);
    }

    public function profile()
    {
        return view('shop.account.profile', ['customer' => auth('customer')->user()]);
    }

    public function updateProfile(Request $request)
    {
        $customer = auth('customer')->user();

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($customer->id)],
            'phone' => ['nullable', 'string', 'max:64'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $customer->fill([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'accepts_marketing' => $request->boolean('accepts_marketing'),
        ]);

        if (filled($data['password'] ?? null)) {
            $customer->password = $data['password'];
        }

        $customer->save();

        return back()->with('status', 'Profile updated.');
    }

    public function addresses()
    {
        return view('shop.account.addresses', [
            'addresses' => auth('customer')->user()->addresses()->orderByDesc('is_default')->get(),
        ]);
    }

    public function storeAddress(Request $request)
    {
        $customer = auth('customer')->user();
        $address = $customer->addresses()->create($this->validatedAddress($request));

        $this->applyDefault($address, $request->boolean('is_default'));

        return back()->with('status', 'Address added.');
    }

    public function updateAddress(Request $request, CustomerAddress $address)
    {
        abort_unless($address->customer_id === auth('customer')->id(), 403);

        $address->update($this->validatedAddress($request));
        $this->applyDefault($address, $request->boolean('is_default'));

        return back()->with('status', 'Address saved.');
    }

    public function destroyAddress(CustomerAddress $address)
    {
        abort_unless($address->customer_id === auth('customer')->id(), 403);

        $address->delete();

        return back()->with('status', 'Address removed.');
    }

    private function validatedAddress(Request $request): array
    {
        return $request->validate([
            'label' => ['nullable', 'string', 'max:64'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:64'],
            'postcode' => ['nullable', 'string', 'max:32'],
            'country' => ['required', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:64'],
        ]);
    }

    /** Exactly one default address per customer. */
    private function applyDefault(CustomerAddress $address, bool $isDefault): void
    {
        if (! $isDefault) {
            return;
        }

        CustomerAddress::where('customer_id', $address->customer_id)
            ->where('id', '!=', $address->id)
            ->update(['is_default' => false]);

        $address->update(['is_default' => true]);
    }
}
