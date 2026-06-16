<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Faq;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            [
                'question' => 'What is this platform used for?',
                'answers'  => 'This platform is used to manage and book professional home inspections, track reports, and communicate with verified inspectors.',
                'status'   => 1,
            ],
            [
                'question' => 'What types of inspections are available?',
                'answers'  => 'We offer property inspections including home condition, roof, electrical, plumbing, and safety inspections.',
                'status'   => 1,
            ],
            [
                'question' => 'How do I book an inspection?',
                'answers'  => 'You can book an inspection directly from the app by selecting your desired service and available inspector.',
                'status'   => 1,
            ],
            [
                'question' => 'Are inspectors verified?',
                'answers'  => 'Yes, all inspectors go through a verification process before joining the platform.',
                'status'   => 1,
            ],
            [
                'question' => 'Can I schedule an urgent inspection?',
                'answers'  => 'Yes, urgent inspections are available depending on inspector availability and location.',
                'status'   => 1,
            ],
            [
                'question' => 'Can I reschedule my inspection?',
                'answers'  => 'Yes. Homeowners can reschedule inspections through the app depending on inspector availability.',
                'status'   => 1,
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::updateOrCreate(
                ['question' => $faq['question']], // unique key
                [
                    'answers' => $faq['answers'],
                    'status'  => $faq['status'],
                ]
            );
        }
    }
}