<?php

namespace Database\Seeders;

use App\Models\ComplaintCategory;
use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Database\Seeder;

class CustomerSupportSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Complaint Categories
        $categories = [
            'Delivery Delay',
            'Damaged Gallon / Leakage',
            'Missing Items / Incorrect Quantity',
            'Billing & Payment Dispute',
            'Water Taste or Quality',
            'Driver or Staff Service',
            'App or Account Issue',
            'Other Concerns',
        ];

        foreach ($categories as $name) {
            ComplaintCategory::firstOrCreate(['name' => $name]);
        }

        // 2. FAQ Categories
        $generalCat = FaqCategory::firstOrCreate(['name' => 'General & Ordering']);
        $deliveryCat = FaqCategory::firstOrCreate(['name' => 'Delivery & Pickup']);
        $creditCat = FaqCategory::firstOrCreate(['name' => 'Payments & Credit']);
        $gallonsCat = FaqCategory::firstOrCreate(['name' => 'Gallon Guidelines']);

        // 3. FAQs
        $faqs = [
            [
                'category_id' => $generalCat->id,
                'question' => 'How does water refill ordering work?',
                'answer' => 'Place an order through the app selecting your preferred refill product and quantity. Our driver will pick up your empty gallons, bring them to our station for refilling, and promptly deliver them back to your door.',
            ],
            [
                'category_id' => $deliveryCat->id,
                'question' => 'What should I prepare for empty gallon pickup?',
                'answer' => 'Have your empty gallons ready at your designated delivery address. The assigned driver will inspect container condition and count the gallons before bringing them to the station.',
            ],
            [
                'category_id' => $generalCat->id,
                'question' => 'How is my refill order processed?',
                'answer' => 'After your gallons are checked in and inspected at the station, staff process the refill. You can track your order status from your account.',
            ],
            [
                'category_id' => $deliveryCat->id,
                'question' => 'When will my refilled water be delivered?',
                'answer' => 'Once refilling and quality seals are applied, your order is marked as Ready for Delivery. An Aqua Clayne driver will be dispatched to deliver your filled containers.',
            ],
            [
                'category_id' => $creditCat->id,
                'question' => 'How does Customer Account Credit work?',
                'answer' => 'Account Credit allows pre-approved customers to order without immediate cash or online payment. Credit limits and settlement schedules are set by station management.',
            ],
            [
                'category_id' => $creditCat->id,
                'question' => 'What payment methods are supported?',
                'answer' => 'We support Cash on Delivery (COD), GCash, and Customer Account Credit (for approved accounts).',
            ],
            [
                'category_id' => $gallonsCat->id,
                'question' => 'What happens if a gallon is damaged or cracked?',
                'answer' => 'Drivers inspect empty gallons at pickup. Any container with visible damage or leaks will be flagged. You will be notified immediately to choose whether to replace or return the damaged container.',
            ],
            [
                'category_id' => $generalCat->id,
                'question' => 'Can I submit a complaint or concern?',
                'answer' => 'Yes! Go to Profile > Complaints & Support > Submit Complaint. Choose the relevant category and describe your issue. Our management team actively investigates all customer submissions.',
            ],
        ];

        foreach ($faqs as $f) {
            Faq::firstOrCreate(
                ['question' => $f['question']],
                [
                    'category_id' => $f['category_id'],
                    'answer' => $f['answer'],
                ]
            );
        }
    }
}
