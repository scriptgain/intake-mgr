<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Customer::search($request->query('q'))->paginate((int) $request->query('per_page', 25))
        );
    }

    public function store(Request $request)
    {
        return response()->json(Customer::create($this->validated($request)), 201);
    }

    public function show(Customer $customer)
    {
        return response()->json($customer->load(['orders', 'addresses']));
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update($this->validated($request, $customer));

        return response()->json($customer);
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?Customer $customer = null): array
    {
        return $request->validate([
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => [$customer ? 'sometimes' : 'required', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($customer?->id)],
            'phone' => ['nullable', 'string', 'max:64'],
            'accepts_marketing' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
