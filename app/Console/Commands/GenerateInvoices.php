<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Classes\Slack;
use App\Classes\Invoice;

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
        # NOTE: Put your Google auth token here in JSON format. Should start with something like:
        # {"access_token":"ya29.a0ARrdaM-5OuECq6KJ1QU8....
        # TODO: Replace this with an auth token stored in the database.
        $json_auth_code = '';

        $gclient = app( \Google_Client::class );
        $gclient->setAccessToken( json_decode( $json_auth_code, true ) );

        // TODO: actually look at the Users table and find users who are due an invoice.
        // For now this is a hard-coded app home channel id, and a google template doc id.
        $due_users = [ [
            'user_channel' => 'D02QFHEKXSL',
            'template_doc_id' => '1Eln2VZD95W7DmU-9fG3fuq1wwLWXrlR_HbbgCclhI9k',
        ] ];

        foreach ( $due_users as $due_user ) {
            // Generate an invoice.
            // TODO: Replace these values with actually updateable things.
            $invoice = Invoice::create( $gclient, $due_user['template_doc_id'], 'Invoice for ' . date( 'd M Y' ) );
            $invoice->replaceText([
                '{{invoiceDate}}' => '2018-01-01',
                '{{invoiceNumber}}' => '1',
                '{{invoiceYear}}' => '2018',
                '[INITIALS]' => 'AMH',
            ]);

            $invoice_url = 'https://docs.google.com/document/d/' . $invoice->document->getId();

            // Send invoice URL in Slack message.
            Slack::post( 'chat.postMessage', [
                'channel' => $due_user['user_channel'],
                #'text' => "I've prepared a <$invoice_url|new Invoice> for you! Please review it and click Submit when ready.",
                'unfurl_links' => false,
                'blocks' => [
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'mrkdwn',
                            'text' => "I've prepared a <$invoice_url|new Invoice> for you! Please review it and click Submit when ready.",
                        ],
                    ],
                    [
                        'type' => 'actions',
                        'block_id' => 'invoice_actions',
                        'elements' => [
                            [
                                'type' => 'button',
                                'text' => [
                                    'type' => 'plain_text',
                                    'text' => 'Submit',
                                ],
                                'value' => 'submit',
                                'action_id' => 'submit',
                            ]
                        ]
                    ]
                ]
            ] );
        }

        return Command::SUCCESS;
    }
}
