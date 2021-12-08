<?php

namespace App\Http\Controllers;

use App\Classes\GoogleDriveSetup;
use App\Classes\Invoice;
use Illuminate\Http\Request;

class DocsController extends Controller
{
    public function index(\Google_Client $client)
    {
        $service = new \Google_Service_Docs($client);
        $results = $service->documents->getDocuments();
        return $results;
    }

    public function create(Request $request, \Google_Client $client)
    {
        $templateFile = resource_path( 'template.docx' );
        $templateData = file_get_contents($templateFile);

        $service = new \Google_Service_Drive( $client );

        $file = new \Google\Service\Drive\DriveFile( $client );
        $file->setName( 'Invoice Template' );
        $file->setMimeType('application/vnd.google-apps.document');

        $createdFile = $service->files->create($file, array(
            'data' => $templateData,
            'mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'uploadType' => 'multipart',
        ));

        return redirect( 'https://docs.google.com/document/d/'.$createdFile->getId().'/edit' );
    }

    public function export( \Google_Client $client)
    {
        $service = new \Google_Service_Docs($client);
        $document = $service->documents->get('1e25R0I_lpcihT8ny2JS3Dg2hM66rjGMNhu9-9e8gbbI');
        return json_encode($document->toSimpleObject());
    }

    public function copy(Request $request, \Google_Client $client)
    {
        $driveService = new \Google_Service_Drive($client);
        $copy = new \Google_Service_Drive_DriveFile([
            'name' => 'Invoice Bot Template'
        ]);
        $driveFile = $driveService->files->copy('1e25R0I_lpcihT8ny2JS3Dg2hM66rjGMNhu9-9e8gbbI', $copy);
        return redirect( 'https://docs.google.com/document/d/'.$driveFile->getId().'/edit' );
    }

    public function setUp( \Google_Client $client )
    {
        $setup = new GoogleDriveSetup($client);
        session(['invoice_folder' => $setup->createFolder()->getId()]);
        session(['invoice_template' => $setup->createTemplate()->getId()]);
    }

    public function invoice( \Google_Client $client )
    {
        $template = session('invoice_template');

        $invoice = Invoice::create($client, $template, 'Invoice #1');
        $invoice->replaceText([
            '{{invoiceDate}}' => '2018-01-01',
            '{{invoiceNumber}}' => '1',
            '{{invoiceYear}}' => '2018',
            '[INITIALS]' => 'AMH',
        ]);


        return redirect( 'https://docs.google.com/document/d/'.$invoice->document->getId().'/edit' );
    }
}
