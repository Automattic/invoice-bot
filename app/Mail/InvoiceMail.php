<?php

namespace App\Mail;

use App\Classes\GoogleDrive;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $documentId;
    private $client;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user, $invoiceData)
    {
        $this->user = $user;
        $this->client = new \Google_Client();
        $this->client->setAccessToken($user->google_access_token);

        $this->documentId = data_get($invoiceData, 'invoice_id');
        $this->invoiceName = data_get($invoiceData, 'invoice_name');
        $this->invoiceNumber = data_get($invoiceData, 'invoice_number');
        $this->invoiceDate = Carbon::parse(data_get($invoiceData, 'invoice_date'));
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $subject = 'Invoice ' . $this->invoiceDate->format('M Y') . ' ' . $this->user->name;
        return $this->to(config('mail.payroll_address'))
            ->cc($this->user->email, $this->user->name)
            ->replyTo($this->user->email, $this->user->name)
            ->subject($subject)
            ->attachData($this->getPdfData(), $subject . '.pdf', [
                'mime' => 'application/pdf',
            ])
            ->markdown('emails.invoice', [
                'user' => $this->user,
                'google_doc_link' => GoogleDrive::getDocLinkById( $this->documentId ),
            ]);
    }

    private function getPdfData()
    {
        $service = new \Google_Service_Drive($this->client);
        $response = $service->files->export($this->documentId, [
            'mimeType' => 'application/pdf',
        ]);

        return $response->getBody();
    }
}
