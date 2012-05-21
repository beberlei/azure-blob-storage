<?php

namespace Beberlei\Tests\AzureBlobStorage;

/**
 * @copyright  Copyright (c) 2009 - 2012, RealDolmen (http://www.realdolmen.com)
 * @license    http://phpazure.codeplex.com/license
 */
class BlobStreamTest extends BlobTestCase
{
    /**
     * Test read file
     */
    public function testReadFile()
    {
        $containerName = $this->generateName();
        $fileName = 'azure://' . $containerName . '/test.txt';

        $storageClient = $this->createStorageInstance();
        $storageClient->registerStreamWrapper();

        $fh = fopen($fileName, 'w');
        fwrite($fh, "Hello world!");
        fclose($fh);

        $result = file_get_contents($fileName);

        $this->assertEquals('Hello world!', $result);
    }

    /**
     * Test write file
     */
    public function testWriteFile()
    {
        $containerName = $this->generateName();
        $fileName = 'azure://' . $containerName . '/test.txt';

        $storageClient = $this->createStorageInstance();
        $storageClient->registerStreamWrapper();

        $fh = fopen($fileName, 'w');
        fwrite($fh, "Hello world!");
        fclose($fh);

        $instance = $storageClient->getBlobInstance($containerName, 'test.txt');
        $this->assertEquals('test.txt', $instance->Name);
    }

    /**
     * Test unlink file
     */
    public function testUnlinkFile()
    {
        $containerName = $this->generateName();
        $fileName = 'azure://' . $containerName . '/test.txt';

        $storageClient = $this->createStorageInstance();
        $storageClient->registerStreamWrapper();

        $fh = fopen($fileName, 'w');
        fwrite($fh, "Hello world!");
        fclose($fh);

        unlink($fileName);

        $result = $storageClient->listBlobs($containerName);
        $this->assertEquals(0, count($result));
    }

    /**
     * Test copy file
     */
    public function testCopyFile()
    {
        $containerName = $this->generateName();
        $sourceFileName = 'azure://' . $containerName . '/test.txt';
        $destinationFileName = 'azure://' . $containerName . '/test2.txt';

        $storageClient = $this->createStorageInstance();
        $storageClient->registerStreamWrapper();

        $fh = fopen($sourceFileName, 'w');
        fwrite($fh, "Hello world!");
        fclose($fh);

        copy($sourceFileName, $destinationFileName);

        $instance = $storageClient->getBlobInstance($containerName, 'test2.txt');
        $this->assertEquals('test2.txt', $instance->Name);
    }

    /**
     * Test rename file
     */
    public function testRenameFile()
    {
        $containerName = $this->generateName();
        $sourceFileName = 'azure://' . $containerName . '/test.txt';
        $destinationFileName = 'azure://' . $containerName . '/test2.txt';

        $storageClient = $this->createStorageInstance();
        $storageClient->registerStreamWrapper();

        $fh = fopen($sourceFileName, 'w');
        fwrite($fh, "Hello world!");
        fclose($fh);

        rename($sourceFileName, $destinationFileName);

        $instance = $storageClient->getBlobInstance($containerName, 'test2.txt');
        $this->assertEquals('test2.txt', $instance->Name);
    }

    /**
     * Test mkdir
     */
    public function testMkdir()
    {
        $containerName = $this->generateName();

        $storageClient = $this->createStorageInstance();
        $storageClient->registerStreamWrapper();

        $current = count($storageClient->listContainers());

        mkdir('azure://' . $containerName);

        $after = count($storageClient->listContainers());

        $this->assertEquals($current + 1, $after, "One new container should exist");
        $this->assertTrue($storageClient->containerExists($containerName));
    }

    /**
     * Test rmdir
     */
    public function testRmdir()
    {
        $containerName = $this->generateName();

        $storageClient = $this->createStorageInstance();
        $storageClient->registerStreamWrapper();

        mkdir('azure://' . $containerName);
        rmdir('azure://' . $containerName);

        $result = $storageClient->listContainers();

        $this->assertFalse($storageClient->containerExists($containerName));
    }

    /**
     * Test opendir
     */
    public function testOpendir()
    {
        $containerName = $this->generateName();
        $storageClient = $this->createStorageInstance();
        $storageClient->createContainer($containerName);

        $storageClient->putBlob($containerName, 'images/WindowsAzure1.gif', self::$path . 'WindowsAzure.gif');
        $storageClient->putBlob($containerName, 'images/WindowsAzure2.gif', self::$path . 'WindowsAzure.gif');
        $storageClient->putBlob($containerName, 'images/WindowsAzure3.gif', self::$path . 'WindowsAzure.gif');
        $storageClient->putBlob($containerName, 'images/WindowsAzure4.gif', self::$path . 'WindowsAzure.gif');
        $storageClient->putBlob($containerName, 'images/WindowsAzure5.gif', self::$path . 'WindowsAzure.gif');

        $result1 = $storageClient->listBlobs($containerName);

        $storageClient->registerStreamWrapper();

        $result2 = array();
        if ($handle = opendir('azure://' . $containerName)) {
            while (false !== ($file = readdir($handle))) {
                $result2[] = $file;
            }
            closedir($handle);
        }

        $result = $storageClient->listContainers();

        $this->assertEquals(count($result1), count($result2));
    }

    static public function dataNestedDirectory()
    {
        return array(
            array('/nested/test.txt'),
            array('/nested1/nested2/test.txt'),
        );
    }

    /**
     * @dataProvider dataNestedDirectory
     */
    public function testNestedDirectory($file)
    {
        $containerName = $this->generateName();
        $fileName = 'azure://' . $containerName . $file;

        $storageClient = $this->createStorageInstance();
        $storageClient->registerStreamWrapper();

        $fh = fopen($fileName, 'w');
        fwrite($fh, "Hello world!");
        fclose($fh);

        $result = file_get_contents($fileName);

        $this->assertEquals('Hello world!', $result);
    }
}
