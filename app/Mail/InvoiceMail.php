<?php

namespace App\Mail;

use App\Classes\GoogleDrive;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $document_id;
    private $client;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user, $document)
    {
        $this->user = $user;
        $this->document_id = $document;
        $this->client = new \Google_Client();
        $this->client->setAccessToken($user->google_access_token);
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $subject = 'Invoice ' . date('M Y') . ' ' . $this->user->name;
        return $this->to(config('mail.payroll_address'))
            ->cc($this->user->email, $this->user->name)
            ->replyTo($this->user->email, $this->user->name)
            ->subject($subject)
            ->attachData($this->getPdfData(), $subject . '.pdf', [
                'mime' => 'application/pdf',
            ])
            ->markdown('emails.invoice', [
                'user' => $this->user,
                'google_doc_link' => GoogleDrive::getDocLinkById( $this->document_id ),
            ]);
    }

    private function getPdfData()
    {
        $service = new \Google_Service_Drive($this->client);
        $response = $service->files->export($this->document_id, [
            'mimeType' => 'application/pdf',
        ]);

        return $response->getBody();
    }
}
