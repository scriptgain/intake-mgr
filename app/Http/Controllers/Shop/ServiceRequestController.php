<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ServiceRequest;
use App\Models\Setting;
use App\Notifications\ServiceRequestReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

/**
 * The public "Request Service" intake front door. Anonymous and mail-generating,
 * so the route carries captcha + throttle (declared on the route, not here).
 *
 * A submission becomes a ServiceRequest (status 'new', source 'web'); staff then
 * triage it in the admin and convert it to a ticket and/or a work order.
 */
class ServiceRequestController extends Controller
{
    public function create()
    {
        return view('shop.request', [
            // Optional "what do you need help with" dropdown. Product is the
            // service catalog here; only active services are offered.
            'services' => Product::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'service_id' => ['nullable', 'integer', 'exists:products,id'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'line1' => ['nullable', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:64'],
            'postcode' => ['nullable', 'string', 'max:32'],
            'attachments' => ['nullable', 'array', 'max:8'],
            'attachments.*' => ['file', 'image', 'max:5120'],
        ]);

        $address = array_filter([
            'line1' => $data['line1'] ?? null,
            'line2' => $data['line2'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'postcode' => $data['postcode'] ?? null,
        ], fn ($v) => filled($v));

        $customer = auth('customer')->user();

        $serviceRequest = ServiceRequest::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'service_id' => $data['service_id'] ?? null,
            'subject' => $data['subject'],
            'description' => $data['description'] ?? null,
            'address' => $address ?: null,
            'status' => 'new',
            'source' => 'web',
            'priority' => 'normal',
            'customer_id' => $customer?->id,
        ]);

        $serviceRequest->recordActivity('created', 'Request submitted via the website', [], null, $serviceRequest->name);

        $this->storeAttachments($request, $serviceRequest, $customer?->id);

        // Acknowledge the requester and alert the store. A mail failure must not
        // block the submission; the request is already saved.
        rescue(fn () => Notification::route('mail', $serviceRequest->email)
            ->notify(new ServiceRequestReceived($serviceRequest, 'customer')), null, false);

        if ($to = $this->staffNotifyEmail()) {
            rescue(fn () => Notification::route('mail', $to)
                ->notify(new ServiceRequestReceived($serviceRequest, 'staff')), null, false);
        }

        return redirect()
            ->route('shop.request')
            ->with('submitted', $serviceRequest->number);
    }

    /** Persist uploaded photos to the private disk and hang them off the request. */
    private function storeAttachments(Request $request, ServiceRequest $serviceRequest, ?int $customerId): void
    {
        if (! $request->hasFile('attachments')) {
            return;
        }

        foreach ($request->file('attachments') as $file) {
            if (! $file->isValid()) {
                continue;
            }

            $path = $file->store('service-requests/'.$serviceRequest->id, 'local');

            $serviceRequest->attachments()->create([
                'uploaded_by_customer_id' => $customerId,
                'disk' => 'local',
                'path' => $path,
                'filename' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'is_internal' => false,
            ]);
        }
    }

    private function staffNotifyEmail(): ?string
    {
        return Setting::get('order_notify_email')
            ?: Setting::get('store_email')
            ?: config('shop.store_email');
    }
}
