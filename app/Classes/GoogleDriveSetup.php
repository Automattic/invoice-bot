<?php

namespace App\Classes;

class GoogleDriveSetup
{
    private $client;
    private $folder;

    public function __construct( $client )
    {
        $this->client = $client;
    }

    public function createFolder()
    {
        if( $this->folder ) {
            return $this->folder;
        }

        $service = new \Google_Service_Drive( $this->client );

        $metadata = new \Google_Service_Drive_DriveFile( [
            'name' => config('app.name'),
            'mimeType' => 'application/vnd.google-apps.folder'
        ] );

        $folder = $service->files->create( $metadata, [
            'fields' => 'id'
        ]);

        $this->folder = $folder;

        return $folder;
    }

    public function createTemplate()
    {
        $folder = $this->createFolder();

        $templateFile = resource_path( 'template.docx' );
        $templateData = file_get_contents($templateFile);

        $service = new \Google_Service_Drive( $this->client );

        $file = new \Google\Service\Drive\DriveFile( $this->client );
        $file->setName( 'Invoice Template' );
        $file->setMimeType('application/vnd.google-apps.document');
        $file->setParents([$folder->id]);

        $createdFile = $service->files->create($file, array(
            'data' => $templateData,
            'mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'uploadType' => 'multipart',
        ));

        return $createdFile;
    }
}