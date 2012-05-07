<?php
namespace Beberlei\Tests\AzureBlobStorage;

use Beberlei\AzureBlobStorage\StorageRegistry;

class StorageRegistryTest extends \PHPUnit_Framework_TestCase
{
    public function testGet()
    {
        $registry = new StorageRegistry();
        $registry->registerAccount('test', 'test', 'teststream');

        $client = $registry->get('test');

        $this->assertInstanceOf('Beberlei\AzureBlobStorage\BlobClient', $client);
        $this->assertTrue(in_array('teststream', stream_get_wrappers()));
    }
}

