<?php
namespace Beberlei\Tests\AzureBlobStorage;

use Beberlei\AzureBlobStorage\BlobClient;

class BlobTestCase extends \PHPUnit_Framework_TestCase
{
    const CONTAINER_PREFIX = 'aztest';

    protected static $path;
    protected static $uniqId;
    protected static $uniqStart;

    /**
     * Test setup
     */
    protected function setUp()
    {
        self::$path = dirname(__FILE__).'/_files/';
        date_default_timezone_set('UTC');
    }

    /**
     * Test teardown
     */
    protected function tearDown()
    {
        $storageClient = $this->createStorageInstance();
        for ($i = self::$uniqStart; $i <= self::$uniqId; $i++) {
            try {
                $storageClient->deleteContainer( self::CONTAINER_PREFIX . $i);
            } catch (\Exception $e) {
            }
        }
    }

    protected function createStorageInstance()
    {
        $storageClient = null;
        if (true) {
            $storageClient = new BlobClient($GLOBALS['AZURESTORAGE_HOST'], $GLOBALS['AZURESTORAGE_ACCOUNT'], $GLOBALS['AZURESTORAGE_KEY'], false);
        } else {
            $storageClient = new BlobClient(TESTS_BLOB_HOST_DEV, TESTS_STORAGE_ACCOUNT_DEV, TESTS_STORAGE_KEY_DEV, true, Microsoft_WindowsAzure_RetryPolicy_RetryPolicyAbstract::retryN(10, 250));
        }

        return $storageClient;
    }

    protected function generateName()
    {
        if (self::$uniqId === null) {
            self::$uniqId = self::$uniqStart = time();
        }
        self::$uniqId++;
        return self::CONTAINER_PREFIX . self::$uniqId;
    }
}

