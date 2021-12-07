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
        $document = new \Google_Service_Docs_Document([
            'title' => 'Invoice Template',
            'body' => [
                'content' => new \Google_Service_Docs_Body([ '<h1>Invoice</h1>' ]),
            ],
        ]);

        $document = $service->documents->create($document);

        return $document->getDocumentId();
    }

    public function copy(Request $request, \Google_Client $client)
    {
        $driveService = new \Google_Service_Drive($client);

        $copyTitle = 'Copy Title';
        $copy = new \Google_Service_Drive_DriveFile([
            'name' => $copyTitle
        ]);
        $driveResponse = $driveService->files->copy('1e25R0I_lpcihT8ny2JS3Dg2hM66rjGMNhu9-9e8gbbI', $copy);
        return $driveResponse->id;
    }
}
