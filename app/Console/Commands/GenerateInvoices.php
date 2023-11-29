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
            ->whereDate('send_invoice_at', '<=', today()->toDateString())
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

                    // Send invoice URL in Slack message.
                    $slack = new Slack( $user );
                    $slack->sendInvoiceMessage( $invoice_name, $user->next_invoice_number, $invoice->document->getId(), today()->toDateString());

                    $user->next_invoice_number = $user->next_invoice_number + 1;
                    $user->send_invoice_at = today()->addMonth()->day(min($user->invoice_day, today()->daysInMonth));
                    $user->save();
                }
            } );

        return Command::SUCCESS;
    }
}
