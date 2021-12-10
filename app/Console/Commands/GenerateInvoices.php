<?php

namespace App\Console\Commands;

use App\Classes\GoogleDrive;
use Illuminate\Console\Command;
use App\Classes\Slack;
use App\Classes\Invoice;
use App\Models\User;

class GenerateInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:invoices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Figure out which users need a new invoice generated, and generate them.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $gclient = app( \Google_Client::class );

        User::whereStatus('active')
            // ->whereDate('send_invoice_at', '<=', today()->toDateString())
            ->chunk(100, function($users) use ($gclient) {
                foreach( $users as $user ) {
                    $gclient->setAccessToken( $user->google_access_token );
                    $user->google_access_token = GoogleDrive::maybeRefreshAccessToken( $gclient );
                    $invoice_name =  'Invoice: ' . date( 'd M Y' );
                    $invoice = Invoice::create( $gclient, $user->gdrive_template_id, $invoice_name );
                    $invoice->replaceText([
                        '{{invoiceDate}}' => today()->toDateString(),
                        '{{invoiceNumber}}' => (string) $user->next_invoice_number, // Must be string
                        '{{invoiceYear}}' => today()->format('Y'),
                    ]);

                    $invoice_url = 'https://docs.google.com/document/d/' . $invoice->document->getId();

                    // Send invoice URL in Slack message.
                    $slack = new Slack( $user );
                    $slack->sendMessage("I've prepared a new Invoice for you! Please review it and click Submit when ready.", [
                        'blocks' => [
                            [
                                'type' => 'section',
                                'text' => [
                                    'type' => 'mrkdwn',
                                    'text' => "I've prepared a <$invoice_url|new Invoice> for you! Please review it and send it when ready.",
                                ],
                            ],
                            Slack::getInvoicePreviewBlock( $user->next_invoice_number, $invoice_name  ),
                            [
                                'type' => 'actions',
                                'block_id' => 'invoice_actions',
                                'elements' => [
                                    [
                                        'type' => 'button',
                                        'text' => [
                                            'type' => 'plain_text',
                                            'text' => 'Review',
                                        ],
                                        'value' => 'review',
                                        'url' => $invoice_url,
                                        'action_id' => 'review-invoice',
                                    ],
                                    [
                                        'type' => 'button',
                                        'text' => [
                                            'type' => 'plain_text',
                                            'text' => 'Send Invoice',
                                        ],
                                        'style' => 'primary',
                                        'value' => json_encode(['invoice_number' => $user->next_invoice_number, 'invoice_url' => $invoice_url, 'invoice_name' => $invoice_name]),
                                        'action_id' => 'submit-invoice',
                                    ]
                                ]
                            ]
                        ]
                    ]);


                    $user->next_invoice_number = $user->next_invoice_number + 1;
                    $user->send_invoice_at = today()->addMonth()->setDay(28);
                    $user->save();

                    $this->info( "Generated invoice for {$user->name}." );
                    $this->line( "Invoice URL: $invoice_url" );
                }
            } );

        return Command::SUCCESS;
    }
}
