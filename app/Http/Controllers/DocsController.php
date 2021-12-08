<?php

namespace App\Http\Controllers;

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
        $service = new \Google_Service_Docs($client);
        $templateFile = resource_path('json/invoice-template.json');
        $template = json_decode(file_get_contents($templateFile), true, 512, JSON_OBJECT_AS_ARRAY);

        $document = new \Google_Service_Docs_Document($template);
        $document = $service->documents->create($document);
        return redirect( 'https://docs.google.com/document/d/'.$document->getDocumentId().'/edit' );
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
        $driveService = new \Google_Service_Drive($client);
        $folder = new \Google_Service_Drive_DriveFile();
        $folder->setName('Invoice Bot');
        $folder->setMimeType('application/vnd.google-apps.folder');
        $folder = $driveService->files->create($folder);

        $driveFile = new \Google_Service_Drive_DriveFile([
            'name' => 'Template'
        ]);


        // TODO: Create the template file.

        return $folder->getId();
    }
}
