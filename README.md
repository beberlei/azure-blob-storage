# Azure Blob Storage

Small library that allows you to work with Windows Azure Blob Storage from PHP.

The code is forked and adapted from the [PHP Azure SDK](http://phpazure.codeplex.com/).
It was cleaned up slightly and removed all its dependencies to be very leightweight.

You can work with Windows Azure Blob from any platform (Windows or Linux) and only need an Azure storage account to get started.

Features:

* Programmatic API to work with blobs
* Containers Management (Private or Public)
* Streamwrapper
* ACLs

## Installation

Suggested via composer:

    {
        "require": {
            "beberlei/azure-blob-storage": "*"
        }
    }

Then using the composer binary:

    prompt> php composer.phar install

## Configuration

    <?php
    use Beberlei\AzureBlobStorage\BlobClient;

    $accountUrl  = "http://myaccount.blob.storage.windows.net";
    $accountName = "myaccount";
    $accountKey  = "abcdefg";

    $client = new BlobClient($accountUrl, $accountName, $accountKey);

    // With Dev-Storage (localhost:10000)
    $client = new BlobClient();

## Usage

### Container API

Containers are equivalent to harddrives with a name in Azure Blob Storage. You have to create
a container before being able to store files:

    <?php
    $container = "testing";

    if ( ! $client->containerExists($container)) {
        $client->createContainer($container);
    }

    $client->createContainerIfNotExists($container);
    $client->deleteContainer($container);

You can also set/get arbitrary metadata for a container:

    <?php
    $container = "testing_metadata";
    $metadata = array('x-ms-application-user' => 'beberlei');

    $client->createContainer($container, $metadata);
    $metadata = $client->getContainerMetadata($container);
    $metadata['x-ms-another-header'] = 'value';

    $client->setContainerMetadata($container, $metadata);

### Blob API

A container holds blobs (files) with names. Azure Blob Storage has no concept of directories within
a container, but you can just use "/" (Yes, not "\") as seperator to simulate them.

    <?php
    $container = 'testing_blob';
    $client->createContainerIfNotExists($container);

    $blobFileName = '/path/to/testing.gif';

    $client->putBlob($container, 'testing.gif', $blobFileName);
    $client->putBlobData($container, 'testing.gif', file_get_contents($blobFileName));

    $client->copyBlob($container, 'testing.gif', $container, 'testing2.gif');

    $blob = $client->getBlobInstance($container, 'testing2.gif');
    // $blob instanceof Beberlei\AzureBlobStorage\BlobInstance

    $localFileName = sys_get_temp_dir() . '/testing2.gif';
    $client->getBlob($container, 'testing2.gif', $localFileName);

    $data = $client->getBlobData($container, 'testing2.gif');

    $blobs = $client->listBlobs($container, 'testing');
    // array of Beberlei\AzureBlobStroage\BlobInstance

    $client->deleteBlob($containerName, 'testing.gif');
    $client->deleteBlob($containerName, 'testing2.gif');

By default Azure Blob Storage creates [Block Blobs and not Page Blobs](http://msdn.microsoft.com/en-us/library/windowsazure/ee691964.aspx).
You can use the `$client->putPageBlob()` API to create page blobs.

## Streamwrapper

To register the stream wrapper for Windows Azure Blob-Storage you have to define a prefix:

    <?php
    use Beberlei\AzureBlobStorage\BlobClient;
    $client = new BlobClient();

    $client->registerStreamWrapper('azure');

    file_put_contents('azure://test.txt', 'Hello World!');


