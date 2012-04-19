# Azure Blob

Small library that allows you to work with Windows Azure Blob Storage from PHP.

The code is forked and adapted from the PHP Azure SDK.
It was cleaned up slightly and removed all its dependencies to be very leightweight.

You can work with Azure Blob from any platform (Windows or Linux) and only need an Azure storage account
to get started.

Features:

* Programmatic API to work with blobs
* Containers Management (Private or Public)
* Streamwrapper
* ACLs

## Installation

Suggested via composer:

    {
        "require": {
            "beberlei/azure-blob": "*"
        }
    }

Then using the composer binary:

    prompt> php composer.phar install

# Configuration

    <?php
    use Beberlei\AzureBlobStorage\BlobClient;

    $accountUrl = "http://myaccount.blob.storage.windows.net";
    $accountName = "myaccount";
    $accountKey = "abcdefg";

    $client = new BlobClient($accountUrl, $accountName, $accountKey);
