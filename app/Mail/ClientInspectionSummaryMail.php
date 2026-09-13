<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use App\Services\ClientReportService;

class ClientInspectionSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $report;
    protected ?string $pdfBinary = null;

    /**
     * Create a new message instance.
     */
    public function __construct(array $report)
    {
        $this->report = $report;
        
        $reportService = new ClientReportService();
        $pdf = $reportService->generatePdf($report);
        $this->pdfBinary = $pdf->output();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $freq = ucfirst($this->report['period']['frequency'] ?? 'Weekly');
        $company = $this->report['client']['company_name'] ?? 'Partner';
        
        return new Envelope(
            subject: "ConnectToInspect - {$freq} Inspection Status Report for {$company}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.client_inspection_summary',
            with: [
                'report' => $this->report,
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        if ($this->pdfBinary) {
            $date = date('Y-m-d');
            return [
                Attachment::fromData(fn () => $this->pdfBinary, "inspection_summary_report_{$date}.pdf")
                    ->withMime('application/pdf'),
            ];
        }

        return [];
    }
}
