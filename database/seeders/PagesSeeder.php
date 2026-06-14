<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PagesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Privacy Policy
        Page::updateOrCreate(
            ['slug' => Str::slug('Privacy Policy')],
            [
                'title' => 'Privacy Policy',
                'content' => '<div class="privacy-policy">
  <h1>Privacy Policy</h1>
  <p><strong>Last Updated:</strong> May 2026</p>

  <p>
    This Privacy Policy explains how our Home Inspection Marketplace Platform collects, uses, stores, and protects your information when you use our mobile application and related services.
  </p>

  <p>
    Our platform connects homeowners with licensed home inspectors for scheduling and managing residential inspection services.
  </p>

  <p>
    By using the app, you agree to the terms outlined in this Privacy Policy.
  </p>

  <h2>Information We Collect</h2>
  <p>To provide inspection booking and management services, we may collect the following information:</p>

  <h3>Account Information</h3>
  <ul>
    <li>Full name</li>
    <li>Email address</li>
    <li>Phone number</li>
    <li>Profile photo</li>
  </ul>

  <h3>Property Information</h3>
  <ul>
    <li>Property address</li>
    <li>Inspection location details</li>
    <li>Inspection notes submitted during booking</li>
  </ul>

  <h3>Booking &amp; Inspection Information</h3>
  <ul>
    <li>Inspection type</li>
    <li>Appointment date and time</li>
    <li>Booking history</li>
    <li>Inspection reports and uploaded images</li>
  </ul>

  <h3>Inspector Verification Information</h3>
  <ul>
    <li>Professional license information</li>
    <li>Insurance documentation</li>
    <li>Additional certifications if required</li>
  </ul>

  <h3>Payment Information</h3>
  <p>
    Payments are securely processed through third-party payment providers. We may store limited transaction details for billing and record-keeping purposes, but we do not store complete payment card information on our servers.
  </p>
</div>',
                'status' => 1,
            ]
        );

        // Terms and Conditions
        Page::updateOrCreate(
            ['slug' => Str::slug('Terms and Conditions')],
            [
                'title' => 'Terms and Conditions',
                'content' => '<div class="terms-conditions">
  <h1>Terms &amp; Conditions</h1>
  <p><strong>Last Updated:</strong> May 2026</p>

  <p>
    This Privacy Policy explains how our Home Inspection Marketplace Platform collects, uses, stores, and protects your information when you use our mobile application and related services.
  </p>

  <p>
    Our platform connects homeowners with licensed home inspectors for scheduling and managing residential inspection services.
  </p>

  <p>
    By using the app, you agree to the terms outlined in this Privacy Policy.
  </p>

  <h2>Information We Collect</h2>
  <p>To provide inspection booking and management services, we may collect the following information:</p>

  <h3>Account Information</h3>
  <ul>
    <li>Full name</li>
    <li>Email address</li>
    <li>Phone number</li>
    <li>Profile photo</li>
  </ul>

  <h3>Property Information</h3>
  <ul>
    <li>Property address</li>
    <li>Inspection location details</li>
    <li>Inspection notes submitted during booking</li>
  </ul>

  <h3>Booking &amp; Inspection Information</h3>
  <ul>
    <li>Inspection type</li>
    <li>Appointment date and time</li>
    <li>Booking history</li>
    <li>Inspection reports and uploaded images</li>
  </ul>

  <h3>Inspector Verification Information</h3>
  <ul>
    <li>Professional license information</li>
    <li>Insurance documentation</li>
    <li>Additional certifications if required</li>
  </ul>

  <h3>Payment Information</h3>
  <p>
    Payments are securely processed through third-party payment providers. We may store limited transaction details for billing and record-keeping purposes, but we do not store complete payment card information on our servers.
  </p>
</div>
',
                'status' => 1,
            ]
        );
    }
}
