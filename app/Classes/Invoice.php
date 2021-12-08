<?php

namespace App\Classes;

class Invoice
{
    public $document;
    private $client;

    public function __construct( $client, $document )
    {
        $this->client = $client;
        $this->document = $document;
    }

    public static function create($client, $template, $name)
    {
        $driveService = new \Google_Service_Drive($client);
        $copy = new \Google_Service_Drive_DriveFile([
            'name' => $name,
        ]);
        $driveFile = $driveService->files->copy($template, $copy);

        return new Invoice($client, $driveFile);
    }

    public function replaceText( $changes )
    {
        $changes =  collect( $changes );
        $changes = $changes->map( function( $replacement, $contains ) {
            return [
                'replaceAllText' => [
                    'replaceText' => $replacement,
                    'containsText' => [
                        'text' => $contains,
                        'matchCase' => true,
                    ],
                ],
            ];
        });
        $requests = new \Google_Service_Docs_BatchUpdateDocumentRequest();
        $requests->setRequests( $changes->values()->toArray() );

        $service = new \Google_Service_Docs( $this->client );
        $service->documents->batchUpdate( $this->document->getId(), $requests );
    }
}