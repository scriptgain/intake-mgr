<?php

namespace Database\Seeders;

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Models\StorePage;
use Illuminate\Database\Seeder;

/**
 * Seeds a starter Help Center and the standard policy pages.
 *
 * Idempotent: everything is keyed on its slug with updateOrCreate, so running
 * this on an existing store never duplicates a category, article, or page. It
 * only fills the gaps and leaves merchant edits to existing rows in place for
 * anything already present (matched by slug).
 */
class HelpCenterSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedHelpCenter();
        $this->seedPolicyPages();
    }

    private function seedHelpCenter(): void
    {
        $categories = [
            [
                'slug' => 'getting-started',
                'name' => 'Getting Started',
                'icon' => 'book',
                'position' => 1,
                'description' => 'How to request service and what to expect from us.',
                'articles' => [
                    [
                        'slug' => 'how-do-i-request-service',
                        'title' => 'How Do I Request Service?',
                        'excerpt' => 'Submit a service request in a couple of minutes.',
                        'body' => "Requesting service takes just a couple of minutes. Fill out the **Request Service** form with a few details:\n\n- What's going on and where it's happening on your property\n- The best way to reach you\n- Photos of the issue, if you have them\n\nPhotos help our technicians understand the job before they even arrive, which often means a faster, more accurate visit.\n\nOnce you submit your request, our team reviews it and reaches out to confirm details and get you on the schedule.",
                    ],
                    [
                        'slug' => 'do-i-need-an-account',
                        'title' => 'Do I Need An Account?',
                        'excerpt' => 'No. You can request service as a guest.',
                        'body' => "You don't need an account to request service. You're welcome to submit a request as a **guest** using just your name, phone number, and service address.\n\nThat said, creating an account gives you a home base for everything:\n\n- Track the status of your requests\n- See upcoming and past work orders\n- View and pay invoices\n\nIf you request as a guest, you can always create an account later using the same email address to link your history.",
                    ],
                    [
                        'slug' => 'how-soon-will-you-respond',
                        'title' => 'How Soon Will You Respond?',
                        'excerpt' => 'We review new requests quickly and reach out to schedule.',
                        'body' => "We review new service requests quickly, usually the same business day. Once we've had a look, we'll contact you to:\n\n1. Confirm the details of what you need done.\n2. Answer any questions and give you a rough idea of cost, when possible.\n3. Get a visit on the schedule at a time that works for you.\n\nIf your issue is urgent, let us know in the request and give us a call. We'll do our best to prioritize it.",
                    ],
                ],
            ],
            [
                'slug' => 'scheduling-appointments',
                'name' => 'Scheduling & Appointments',
                'icon' => 'bell',
                'position' => 2,
                'description' => 'Booking a visit, changing plans, and adding it to your calendar.',
                'articles' => [
                    [
                        'slug' => 'how-does-scheduling-work',
                        'title' => 'How Does Scheduling Work?',
                        'excerpt' => 'We confirm the details, then book a technician for a date and time.',
                        'body' => "After we review your service request and confirm the details with you, we book a technician and turn it into a **scheduled work order**.\n\nYou'll receive:\n\n- A confirmed date and, where possible, a time window\n- The name of the technician assigned to the job\n- A reminder as your appointment approaches\n\nYou can see all of this from your account under **Work Orders**, along with the current status of the job.",
                    ],
                    [
                        'slug' => 'can-i-reschedule-or-cancel',
                        'title' => 'Can I Reschedule Or Cancel?',
                        'excerpt' => 'Yes, from your account, subject to reasonable notice.',
                        'body' => "Plans change, and that's fine. You can request a reschedule or cancellation right from a scheduled work order in your account.\n\nA few notes:\n\n- We ask for as much notice as possible so we can offer the slot to another customer.\n- Cancellations made with little notice may affect how quickly we can get you back on the schedule.\n- If you're not sure how much notice you need to give, just contact us and we'll sort it out.\n\nWe'll always confirm the change by email once it's made.",
                    ],
                    [
                        'slug' => 'how-do-i-add-a-visit-to-my-calendar',
                        'title' => 'How Do I Add A Visit To My Calendar?',
                        'excerpt' => 'Use the Add to Calendar button on a scheduled work order.',
                        'body' => "Once a work order has a confirmed date and time, you'll see an **Add to Calendar** button when you open it in your account.\n\nThis works with:\n\n- Apple Calendar\n- Google Calendar\n- Outlook\n\nOne click adds the visit, along with the address and technician details, straight to your calendar so you don't have to remember it.",
                    ],
                ],
            ],
            [
                'slug' => 'billing-payments',
                'name' => 'Billing & Payments',
                'icon' => 'credit-card',
                'position' => 3,
                'description' => 'How invoices work and what payment methods we accept.',
                'articles' => [
                    [
                        'slug' => 'how-and-when-do-i-pay',
                        'title' => 'How And When Do I Pay?',
                        'excerpt' => 'You get an invoice once the work is completed.',
                        'body' => "You're invoiced once the work on your service request is **completed**, not before. There's no need to pay anything up front just to get on the schedule.\n\nWhen your invoice is ready:\n\n1. You'll receive an email letting you know it's available.\n2. Sign in to your account and open **Invoices**.\n3. Review the details and pay online in a few clicks.\n\nYou can always come back later to view your full billing history.",
                    ],
                    [
                        'slug' => 'what-payment-methods-do-you-accept',
                        'title' => 'What Payment Methods Do You Accept?',
                        'excerpt' => 'Major credit and debit cards, processed securely.',
                        'body' => "We accept all major credit and debit cards. Payments are processed over an **encrypted, secure connection**.\n\nWe never store your full card number on our systems. For more on how we handle your information, see our [Privacy Policy](/pages/privacy).",
                    ],
                    [
                        'slug' => 'how-do-refunds-work',
                        'title' => 'How Do Refunds Work?',
                        'excerpt' => 'Contact us and approved refunds return to your original payment method.',
                        'body' => "If something about a completed job isn't right, the first step is simply to contact us and let us know. We'll take a look and make it right, which often means sending a technician back out at no extra cost.\n\nWhen a refund is the right outcome, we'll process it back to your **original payment method**. Depending on your bank or card issuer, it can take a few business days to appear on your statement.",
                    ],
                ],
            ],
            [
                'slug' => 'your-account',
                'name' => 'Your Account',
                'icon' => 'users',
                'position' => 4,
                'description' => 'Tracking your history and keeping your details up to date.',
                'articles' => [
                    [
                        'slug' => 'how-do-i-track-my-requests-and-work-orders',
                        'title' => 'How Do I Track My Requests And Work Orders?',
                        'excerpt' => 'Sign in to see requests, tickets, work orders, and invoices in one place.',
                        'body' => "Once you're signed in, your account is the single place to see everything happening with your service:\n\n- **Requests** you've submitted and their current status\n- **Tickets** if you've reached out with a question or follow-up\n- **Work Orders** that are scheduled, in progress, or completed\n- **Invoices** and your full payment history\n\nIf a request turns into a scheduled visit, you'll see it move from a request to a work order automatically, no need to submit anything twice.",
                    ],
                    [
                        'slug' => 'how-do-i-update-my-details',
                        'title' => 'How Do I Update My Details?',
                        'excerpt' => 'Manage your profile and service addresses from your account.',
                        'body' => "Keeping your details current helps us reach you and get to the right address. To update your information:\n\n1. Sign in to your account.\n2. Go to your **Profile** to update your name, email, or phone number.\n3. Go to **Addresses** to add, edit, or remove a service address.\n\nIf you need to change details on a request or work order that's already been submitted, contact us and we'll update it for you.",
                    ],
                ],
            ],
            [
                'slug' => 'service-visits',
                'name' => 'Service Visits',
                'icon' => 'truck',
                'position' => 5,
                'description' => 'What happens before, during, and after a technician visit.',
                'articles' => [
                    [
                        'slug' => 'what-to-expect-during-your-visit',
                        'title' => 'What To Expect During Your Visit',
                        'excerpt' => "From arrival to wrap-up, here's how a service visit goes.",
                        'body' => "Your technician arrives within the scheduled window and will call or text when they're on the way.\n\nHere's the typical flow:\n\n- **Walkthrough** — we look at the issue and confirm what you booked.\n- **Diagnosis** — we explain what's going on and what it takes to fix it.\n- **The work** — with your go-ahead, we get it done on the spot when we can.\n- **Wrap-up** — we clean up, show you what we did, and go over any follow-up.\n\nIf the job turns out bigger than the appointment allows, we'll schedule a return visit at a time that works for you.",
                    ],
                    [
                        'slug' => 'how-to-prepare-your-home',
                        'title' => 'How To Prepare Your Home',
                        'excerpt' => 'A few quick things that help your visit go faster.',
                        'body' => "You don't need to do much, but these help:\n\n- **Clear the area** around the equipment or the problem spot.\n- **Secure pets** so your technician can move freely.\n- **Make a list** of anything you've noticed, even small things.\n- **Know your access** — gate codes, crawl spaces, and shutoff locations.\n\nThe clearer the path to the work, the more we can get done in one visit.",
                    ],
                    [
                        'slug' => 'after-your-appointment',
                        'title' => 'After Your Appointment',
                        'excerpt' => 'What happens once the work is done.',
                        'body' => "When the visit wraps up, you'll get a summary of what we did. If we recommend follow-up work, it's spelled out with no pressure.\n\n- **Receipts and invoices** land in your email and your account.\n- **Warranties** on parts and labor are noted on your work order.\n- **Questions later?** Reply to your confirmation or send a new request.\n\nIf anything doesn't feel right after we leave, tell us. We would rather come back and make it right.",
                    ],
                ],
            ],
            [
                'slug' => 'troubleshooting',
                'name' => 'Troubleshooting',
                'icon' => 'bolt',
                'position' => 6,
                'description' => 'Quick checks and common fixes to try before you book.',
                'articles' => [
                    [
                        'slug' => 'quick-checks-before-you-book',
                        'title' => 'Quick Checks Before You Book',
                        'excerpt' => 'Simple things to try that might save you a visit.',
                        'body' => "Before you book, a few basics are worth a look:\n\n- **Power** — check the breaker or GFCI outlet for the equipment.\n- **Water** — confirm shutoff valves are fully open.\n- **Filters** — a clogged filter causes a surprising number of issues.\n- **Settings** — make sure a thermostat or timer isn't the culprit.\n\nStill stuck? Book a visit and note what you already tried so we come prepared.",
                    ],
                    [
                        'slug' => 'common-issues-and-simple-fixes',
                        'title' => 'Common Issues And Simple Fixes',
                        'excerpt' => 'The problems we see most, and what you can safely check.',
                        'body' => "**No hot water** — check the breaker and the pilot or reset button before booking.\n\n**Pool pump won't run** — confirm the timer setting and that the breaker hasn't tripped.\n\n**Weak water pressure** — a clogged aerator or a partly closed valve is often the cause.\n\n**Uneven heating or cooling** — clean or replace the filter and clear any blocked vents.\n\nIf a quick check doesn't solve it, we're glad to take it from there.",
                    ],
                    [
                        'slug' => 'when-its-an-emergency',
                        'title' => "When It's An Emergency",
                        'excerpt' => 'How to tell an urgent problem from one that can wait.',
                        'body' => "Some issues shouldn't wait for a standard appointment. Call us right away or book an **Emergency Callout** if you have:\n\n- **Active leaks or flooding** you can't stop at the shutoff.\n- **No heat or no cooling** in extreme weather.\n- **A gas smell** — leave the area first, then call.\n- **Sparking, burning smells, or exposed wiring.**\n\nFor anything involving gas, smoke, or fire, contact your utility or emergency services first. Safety comes before the repair.",
                    ],
                ],
            ],
        ];

        foreach ($categories as $data) {
            $articles = $data['articles'];
            unset($data['articles']);

            $category = HelpCategory::updateOrCreate(
                ['slug' => $data['slug']],
                array_merge($data, ['is_published' => true]),
            );

            foreach ($articles as $i => $article) {
                HelpArticle::updateOrCreate(
                    ['slug' => $article['slug']],
                    array_merge($article, [
                        'help_category_id' => $category->id,
                        'position' => $i + 1,
                        'is_published' => true,
                    ]),
                );
            }
        }
    }

    private function seedPolicyPages(): void
    {
        $pages = [
            [
                'slug' => 'terms',
                'title' => 'Terms Of Service',
                'position' => 1,
                'body' => "## Terms Of Service\n\nWelcome. By requesting service, scheduling a visit, or otherwise using this site, you agree to the terms below. Please read them carefully.\n\n### Requesting Service\n\nWhen you submit a service request, you're asking us to review your job and reach out to schedule it. Submitting a request does not guarantee a specific date, time, or price. We'll confirm those details with you before any work is booked.\n\n### Estimates And Final Price\n\nAny estimate we provide before a visit is based on the information you give us and is not a fixed price. The final price is confirmed once a technician has assessed the job on site, and may differ if the actual scope of work differs from what was described.\n\n### Scheduling And Access\n\nOnce a visit is scheduled, please make sure a technician can safely access the work area at the agreed date and time. If we're unable to access the property or complete the work as scheduled, we may need to reschedule, and additional charges may apply for repeat trips.\n\n### Cancellations\n\nYou may reschedule or cancel a scheduled work order from your account. We ask for as much notice as possible. Cancellations made with little notice may affect how quickly we can rebook you.\n\n### Payment\n\nInvoices are issued once work is completed and are due upon receipt unless other arrangements have been made. You confirm that you are authorized to use the payment method you provide.\n\n### Limitation Of Liability\n\nWe perform work using qualified, insured technicians and stand behind our workmanship as described in our [Service Guarantee](/pages/service-guarantee). To the fullest extent permitted by law, we are not liable for indirect or consequential losses arising from your use of this site or our services.\n\n### Changes To These Terms\n\nWe may update these terms from time to time. Continued use of this site after changes are posted means you accept the revised terms.\n\n### Contact\n\nQuestions about these terms? Get in touch using the contact details in our footer.",
            ],
            [
                'slug' => 'privacy',
                'title' => 'Privacy Policy',
                'position' => 2,
                'body' => "## Privacy Policy\n\nYour privacy matters to us. This policy explains what we collect, why, and how we protect it.\n\n### Information We Collect\n\nWhen you request service or create an account, we may collect:\n\n- Your name, email address, and phone number\n- Your service address\n- Details and photos related to your service request\n- Your work order and invoice history\n\nWe do **not** store your full payment card number. Payments are handled by a secure, PCI-compliant payment processor.\n\n### How We Use Your Information\n\nWe use your information to:\n\n- Schedule and perform the service you've requested\n- Send updates about your requests, appointments, and invoices\n- Provide customer support\n- Process payment for completed work\n\n### Sharing Your Information\n\nWe share information only with the service providers who help us operate, such as our payment processor, and only as needed to schedule and complete your service. We never sell your personal information.\n\n### Cookies\n\nWe use cookies to keep your account signed in, remember your preferences, and understand how the site is used. You can control cookies through your browser settings.\n\n### Your Rights\n\nYou may request access to, correction of, or deletion of your personal information at any time by contacting us.\n\n### Data Security\n\nWe use encryption and other safeguards to protect your information, though no method of transmission over the internet is ever completely secure.\n\n### Contact\n\nIf you have questions about this policy or your data, please reach out using the contact details in our footer.",
            ],
            [
                'slug' => 'service-guarantee',
                'title' => 'Service Guarantee',
                'position' => 3,
                'body' => "## Service Guarantee\n\nWe stand behind the work we do. Every visit is carried out by a vetted, insured technician, and we back that work with a straightforward guarantee.\n\n### Our Promise\n\nIf something isn't right after a completed job, whether the issue wasn't fully resolved or a new problem shows up that's related to our work, let us know within a reasonable window and we'll make it right. In most cases, that means sending a technician back out at no additional cost to you.\n\n### Vetted, Insured Technicians\n\nEvery technician we send is background checked, trained, and covered by insurance. You should feel confident having them at your property.\n\n### How To Use Your Guarantee\n\n1. Contact us and describe what's going on.\n2. We'll review your service history for that job.\n3. If it's covered, we'll schedule a follow-up visit as quickly as we can.\n\n### What It Doesn't Cover\n\nThis guarantee covers the quality of our workmanship on the original job. It does not cover unrelated new issues, normal wear and tear, or damage caused after our visit by something outside our control.\n\n### Contact\n\nQuestions about a completed job? Reach out using the contact details in our footer and we'll take care of it.",
            ],
        ];

        foreach ($pages as $data) {
            StorePage::updateOrCreate(
                ['slug' => $data['slug']],
                array_merge($data, ['is_published' => true]),
            );
        }
    }
}
