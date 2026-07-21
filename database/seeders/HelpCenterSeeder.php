<?php

namespace Database\Seeders;

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Models\StorePage;
use Illuminate\Database\Seeder;

/**
 * Seeds a starter Help Center and the four standard policy pages.
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
                'slug' => 'orders-shipping',
                'name' => 'Orders & Shipping',
                'icon' => 'truck',
                'position' => 1,
                'description' => 'Delivery times, tracking, and where we ship.',
                'articles' => [
                    [
                        'slug' => 'how-long-does-shipping-take',
                        'title' => 'How Long Does Shipping Take?',
                        'excerpt' => 'Most orders arrive within 3 to 7 business days.',
                        'body' => "Most orders ship within **1 to 2 business days** of being placed. Once your order is on its way, delivery usually takes:\n\n- **Standard:** 3 to 7 business days\n- **Expedited:** 2 to 3 business days\n- **Overnight:** the next business day\n\nDelivery estimates begin the day your order ships, not the day you place it. You'll get a shipping confirmation email with tracking as soon as your parcel leaves our warehouse.",
                    ],
                    [
                        'slug' => 'how-do-i-track-my-order',
                        'title' => 'How Do I Track My Order?',
                        'excerpt' => 'Use the tracking link in your shipping confirmation email.',
                        'body' => "When your order ships, we email you a **tracking number** and a link to follow your parcel.\n\nYou can also find tracking on your order:\n\n1. Sign in to your account.\n2. Open **Order History**.\n3. Select the order to see its current status and tracking.\n\nIf tracking hasn't updated within 48 hours of your shipping email, get in touch and we'll look into it.",
                    ],
                    [
                        'slug' => 'do-you-ship-internationally',
                        'title' => 'Do You Ship Internationally?',
                        'excerpt' => 'We currently ship within the United States.',
                        'body' => "At this time we ship to addresses **within the United States**, including PO boxes and APO/FPO addresses.\n\nWe're working on expanding to more countries. If you'd like to be notified when we ship to your region, join our newsletter at the bottom of any page.",
                    ],
                ],
            ],
            [
                'slug' => 'returns-refunds',
                'name' => 'Returns & Refunds',
                'icon' => 'refresh',
                'position' => 2,
                'description' => 'Our return window, how to start one, and when you get your money back.',
                'articles' => [
                    [
                        'slug' => 'what-is-your-return-policy',
                        'title' => 'What Is Your Return Policy?',
                        'excerpt' => 'Return most items within 30 days for a full refund.',
                        'body' => "You can return most items within **30 days of delivery** for a full refund, as long as they're unused and in their original packaging.\n\nA few things can't be returned, including gift cards and final-sale items. For the full details, see our [Refund Policy](/pages/refund-policy).",
                    ],
                    [
                        'slug' => 'how-do-i-start-a-return',
                        'title' => 'How Do I Start A Return?',
                        'excerpt' => 'Reply to your order confirmation or contact us to begin.',
                        'body' => "Starting a return is simple:\n\n1. Reply to your order confirmation email, or contact our support team.\n2. Tell us which item you'd like to return and why.\n3. We'll email you a prepaid return label and instructions.\n\nOnce we receive and inspect the item, your refund is issued to the original payment method.",
                    ],
                    [
                        'slug' => 'when-will-i-get-my-refund',
                        'title' => 'When Will I Get My Refund?',
                        'excerpt' => 'Refunds post within 5 to 10 business days of us receiving your return.',
                        'body' => "After your returned item arrives and passes inspection, we issue your refund to the **original payment method** right away.\n\nDepending on your bank or card issuer, it can take **5 to 10 business days** for the refund to appear on your statement. We'll email you the moment the refund is on its way.",
                    ],
                ],
            ],
            [
                'slug' => 'payments-billing',
                'name' => 'Payments & Billing',
                'icon' => 'credit-card',
                'position' => 3,
                'description' => 'Accepted payment methods and how checkout is secured.',
                'articles' => [
                    [
                        'slug' => 'what-payment-methods-do-you-accept',
                        'title' => 'What Payment Methods Do You Accept?',
                        'excerpt' => 'All major cards and popular digital wallets.',
                        'body' => "We accept:\n\n- **Visa**, **Mastercard**, **American Express**, and **Discover**\n- Popular digital wallets at checkout\n\nYour card is only charged once your order is confirmed. All payments are processed over an encrypted, PCI-compliant connection.",
                    ],
                    [
                        'slug' => 'is-my-payment-information-secure',
                        'title' => 'Is My Payment Information Secure?',
                        'excerpt' => 'Yes. Payments are encrypted and we never store your full card number.',
                        'body' => "Absolutely. Every payment is processed over an **encrypted** connection by a PCI-compliant payment provider.\n\nWe never see or store your full card number. For more on how we handle your data, read our [Privacy Policy](/pages/privacy).",
                    ],
                ],
            ],
            [
                'slug' => 'your-account',
                'name' => 'Your Account',
                'icon' => 'user',
                'position' => 4,
                'description' => 'Managing your profile, addresses, and order history.',
                'articles' => [
                    [
                        'slug' => 'do-i-need-an-account-to-order',
                        'title' => 'Do I Need An Account To Order?',
                        'excerpt' => 'No. You can check out as a guest.',
                        'body' => "You don't need an account to place an order. You're welcome to check out as a **guest** using just your email and shipping address.\n\nThat said, creating an account lets you track orders, save addresses, and reorder faster next time.",
                    ],
                    [
                        'slug' => 'how-do-i-update-my-address',
                        'title' => 'How Do I Update My Address?',
                        'excerpt' => 'Manage saved addresses from your account.',
                        'body' => "To update your saved addresses:\n\n1. Sign in to your account.\n2. Go to **Addresses**.\n3. Add, edit, or remove any address.\n\nIf you need to change the address on an order you've already placed, contact us as soon as possible and we'll update it if the order hasn't shipped yet.",
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
                'slug' => 'shipping',
                'title' => 'Shipping Information',
                'position' => 1,
                'body' => "## Shipping Information\n\nWe want your order to reach you quickly and in perfect condition. Here's how our shipping works.\n\n### Processing Time\n\nOrders are processed and shipped within **1 to 2 business days**. Orders placed on weekends or holidays are processed the next business day.\n\n### Delivery Estimates\n\n| Method | Estimated Delivery |\n| --- | --- |\n| Standard | 3 to 7 business days |\n| Expedited | 2 to 3 business days |\n| Overnight | Next business day |\n\nDelivery estimates begin the day your order ships. You'll receive a tracking number by email as soon as it's on the way.\n\n### Shipping Rates\n\nShipping is calculated at checkout based on your order and destination. **Orders over \$75 ship free.**\n\n### Where We Ship\n\nWe currently ship to addresses within the United States, including PO boxes and APO/FPO addresses.\n\n### Questions\n\nIf your order hasn't arrived within the estimated window, please reach out and we'll help track it down.",
            ],
            [
                'slug' => 'refund-policy',
                'title' => 'Refund Policy',
                'position' => 2,
                'body' => "## Refund Policy\n\nWe stand behind everything we sell. If you're not completely happy, we're here to make it right.\n\n### Our 30-Day Return Window\n\nYou may return most items within **30 days of delivery** for a full refund. To be eligible, items must be:\n\n- Unused and in the same condition you received them\n- In their original packaging with any tags attached\n\n### How To Start A Return\n\n1. Contact our support team or reply to your order confirmation email.\n2. Let us know which item you'd like to return and why.\n3. We'll send you a prepaid return label and instructions.\n\n### Refunds\n\nOnce we receive and inspect your return, we'll email you to confirm. Approved refunds are issued to your **original payment method** within **5 to 10 business days**, depending on your bank.\n\n### Exchanges\n\nThe fastest way to exchange an item is to return the original and place a new order.\n\n### Non-Returnable Items\n\nA few items can't be returned, including gift cards and final-sale merchandise. These are noted on the product page.\n\n### Damaged Or Wrong Items\n\nIf your order arrives damaged or you received the wrong item, contact us within 7 days and we'll replace it at no cost to you.",
            ],
            [
                'slug' => 'terms',
                'title' => 'Terms Of Service',
                'position' => 3,
                'body' => "## Terms Of Service\n\nWelcome. By accessing or using this website and placing an order, you agree to the terms below. Please read them carefully.\n\n### Using This Site\n\nYou agree to use this site only for lawful purposes and not to interfere with its operation or security. You must be at least 18 years old, or have the consent of a parent or guardian, to make a purchase.\n\n### Orders And Pricing\n\nAll orders are subject to acceptance and availability. We reserve the right to refuse or cancel any order, including after it has been placed. Prices and product descriptions are subject to change without notice, and we make every effort to keep them accurate.\n\n### Payment\n\nYou confirm that you are authorized to use the payment method you provide. Your card is charged once your order is confirmed.\n\n### Intellectual Property\n\nAll content on this site, including text, images, logos, and designs, is our property or that of our licensors and may not be reproduced without permission.\n\n### Limitation Of Liability\n\nThis site and its products are provided on an \"as is\" basis. To the fullest extent permitted by law, we are not liable for any indirect or consequential loss arising from your use of the site or products.\n\n### Changes To These Terms\n\nWe may update these terms from time to time. Continued use of the site after changes are posted means you accept the revised terms.\n\n### Contact\n\nQuestions about these terms? Get in touch using the contact details in our footer.",
            ],
            [
                'slug' => 'privacy',
                'title' => 'Privacy Policy',
                'position' => 4,
                'body' => "## Privacy Policy\n\nYour privacy matters to us. This policy explains what we collect, why, and how we protect it.\n\n### Information We Collect\n\nWhen you shop with us or create an account, we may collect:\n\n- Your name, email address, and phone number\n- Shipping and billing addresses\n- Order history and preferences\n\nWe do **not** store your full payment card number. Payments are handled by a secure, PCI-compliant processor.\n\n### How We Use Your Information\n\nWe use your information to:\n\n- Process and deliver your orders\n- Send order confirmations and shipping updates\n- Provide customer support\n- Improve our products and your shopping experience\n\n### Sharing Your Information\n\nWe share information only with the service providers who help us run the store, such as our payment processor and shipping carriers, and only as needed to fulfill your order. We never sell your personal information.\n\n### Cookies\n\nWe use cookies to keep your cart working, remember your preferences, and understand how the site is used. You can control cookies through your browser settings.\n\n### Your Rights\n\nYou may request access to, correction of, or deletion of your personal information at any time by contacting us.\n\n### Data Security\n\nWe use encryption and other safeguards to protect your information, though no method of transmission over the internet is ever completely secure.\n\n### Contact\n\nIf you have questions about this policy or your data, please reach out using the contact details in our footer.",
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
