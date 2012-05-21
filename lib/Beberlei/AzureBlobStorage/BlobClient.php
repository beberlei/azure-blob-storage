<?php
/**
 * WindowsAzure BlobStorage Client
 *
 * Copyright (c) 2009 - 2012, RealDolmen
 * Copyright (c) 2012, Benjamin Eberlei
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to kontakt@beberlei.de so I can send you a copy immediately.
 */

namespace Beberlei\AzureBlobStorage;

use Assert\Assertion;

/**
 * Client for Microsoft Windows Azure Blob Storage.
 *
 * Originally implemented in the PHP Azure SDK.
 *
 * @copyright  Copyright (c) 2009 - 2012, RealDolmen (http://www.realdolmen.com)
 * @license    http://phpazure.codeplex.com/license
 * @license    New BSD
 */
class BlobClient
{
    /**
     * ACL - Private access
     */
    const ACL_PRIVATE = null;

    /**
     * ACL - Public access (read all blobs)
     *
     * @deprecated Use ACL_PUBLIC_CONTAINER or ACL_PUBLIC_BLOB instead.
     */
    const ACL_PUBLIC = 'container';

    /**
     * ACL - Blob Public access (read all blobs)
     */
    const ACL_PUBLIC_BLOB = 'blob';

    /**
     * ACL - Container Public access (enumerate and read all blobs)
     */
    const ACL_PUBLIC_CONTAINER = 'container';

    /**
     * Blob lease constants
     */
    const LEASE_ACQUIRE = 'acquire';
    const LEASE_RENEW   = 'renew';
    const LEASE_RELEASE = 'release';
    const LEASE_BREAK   = 'break';

    /**
     * Maximal blob size (in bytes)
     */
    const MAX_BLOB_SIZE = 67108864;

    /**
     * Maximal blob transfer size (in bytes)
     */
    const MAX_BLOB_TRANSFER_SIZE = 4194304;

    /**
     * Blob types
     */
    const BLOBTYPE_BLOCK = 'BlockBlob';
    const BLOBTYPE_PAGE  = 'PageBlob';

    /**
     * Put page write options
     */
    const PAGE_WRITE_UPDATE = 'update';
    const PAGE_WRITE_CLEAR  = 'clear';

    const URL_DEV_BLOB = 'http://127.0.0.1:10000';
    const URL_CLOUD_BLOB = 'ssl://blob.core.windows.net';

    const RESOURCE_CONTAINER   = "c";
    const RESOURCE_BLOB        = "b";

    const PERMISSION_READ        = "r";
    const PERMISSION_WRITE       = "w";
    const PERMISSION_DELETE      = "d";
    const PERMISSION_LIST        = "l";

    /**
     * Stream wrapper clients
     *
     * @var array
     */
    protected static $wrapperClients = array();

    /**
     * SharedAccessSignature credentials
     *
     * @var Microsoft_WindowsAzure_Credentials_SharedAccessSignature
     */
    protected $credentials = null;

    /**
     * @var string
     */
    protected $host;

    /**
     * @var string
     */
    protected $accountName;

    /**
     * @var string
     */
    protected $accountKey;

    protected $apiVersion = '2009-09-19';
    protected $protocol;

    /**
     * Creates a new BlobClient instance
     *
     * @param string $host Storage host name
     * @param string $accountName Account name for Windows Azure
     * @param string $accountKey Account key for Windows Azure
     */
    public function __construct($host = self::URL_DEV_BLOB, $accountName = self::DEVSTORE_ACCOUNT, $accountKey = self::DEVSTORE_KEY)
    {
        $this->host = $host;
        $this->accountName = $accountName;
        $this->accountKey = $accountKey;

        $this->credentials = new SharedKey($accountName, $accountKey, false);
        $this->httpClient = new \Beberlei\AzureBlobStorage\Http\SocketClient;
    }

    /**
     * Perform request using Microsoft_Http_Client channel
     *
     * @param string $path Path
     * @param array $query Query parameters
     * @param string $httpVerb HTTP verb the request will use
     * @param array $headers x-ms headers to add
     * @param boolean $forTableStorage Is the request for table storage?
     * @param mixed $rawData Optional RAW HTTP data to be sent over the wire
     * @param string $resourceType Resource type
     * @param string $requiredPermission Required permission
     * @return Microsoft_Http_Response
     */
    protected function performRequest(
        $path = '/',
        $query = array(),
        $httpVerb = 'GET',
        $headers = array(),
        $forTableStorage = false,
        $rawData = null,
        $resourceType = self::RESOURCE_UNKNOWN,
        $requiredPermission = self::PERMISSION_READ
    ) {
        // Clean path
        if (strpos($path, '/') !== 0) {
            $path = '/' . $path;
        }

        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = '';
        }

        if (!isset($headers['content-length']) && ($rawData !== null || $httpVerb == "PUT")) {
            $headers['Content-Length'] = strlen((string)$rawData);
        }
        $headers['Expect'] = '';

        // Add version header
        $headers['x-ms-version'] = $this->apiVersion;

        // Generate URL
        $path = str_replace(' ', '%20', $path);
        $requestUrl = $this->getBaseUrl() . $path;
        if (count($query) > 0) {
            $queryString = '';
            foreach ($query as $key => $value) {
                $queryString .= ($queryString ? '&' : '?') . rawurlencode($key) . '=' . rawurlencode($value);
            }
            $requestUrl .= $queryString;
        }

        $requestUrl = $this->credentials->signRequestUrl($requestUrl, $resourceType, $requiredPermission);
        $headers    = $this->credentials->signRequestHeaders(
            $httpVerb,
            $path,
            $query,
            $headers,
            $forTableStorage,
            $resourceType,
            $requiredPermission,
            $rawData
        );

        return $this->httpClient->request($httpVerb, $requestUrl, $rawData, $headers);
    }

    /**
     * Check if a blob exists
     *
     * @param string $containerName Container name
     * @param string $blobName      Blob name
     * @param string $snapshotId    Snapshot identifier
     * @return boolean
     */
    public function blobExists($containerName = '', $blobName = '', $snapshotId = null)
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);
        Assertion::notEmpty($blobName, 'Blob name is not specified.');

        // Get blob instance
        try {
            $this->getBlobInstance($containerName, $blobName, $snapshotId);
        } catch (BlobException $e) {
            return false;
        }

        return true;
    }

    /**
     * Check if a container exists
     *
     * @param string $containerName Container name
     * @return boolean
     */
    public function containerExists($containerName = '')
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);

        // List containers
        $containers = $this->listContainers($containerName, 1);
        foreach ($containers as $container) {
            if ($container->Name == $containerName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create container
     *
     * @param string $containerName Container name
     * @param array  $metadata      Key/value pairs of meta data
     * @return object Container properties
     * @throws BlobException
     */
    public function createContainer($containerName = '', $metadata = array())
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);
        Assertion::isArray($metadata, 'Meta data should be an array of key and value pairs.');

        // Create metadata headers
        $headers = $this->generateMetadataHeaders($metadata);

        // Perform request
        $response = $this->performRequest($containerName, array('restype' => 'container'), 'PUT', $headers, false, null, self::RESOURCE_CONTAINER, self::PERMISSION_WRITE);
        if ( ! $response->isSuccessful()) {
            throw new BlobException($this->getErrorMessage($response, 'Resource could not be accessed.'));
        }

        return new BlobContainer(
            $containerName,
            $response->getHeader('Etag'),
            $response->getHeader('Last-modified'),
            $metadata
        );
    }

    /**
     * Create container if it does not exist
     *
     * @param string $containerName Container name
     * @param array  $metadata      Key/value pairs of meta data
     * @throws BlobException
     */
    public function createContainerIfNotExists($containerName = '', $metadata = array())
    {
        if ( ! $this->containerExists($containerName)) {
            $this->createContainer($containerName, $metadata);
        }
    }

    /**
     * Get container ACL
     *
     * @param string $containerName Container name
     * @param bool   $signedIdentifiers Display only private/blob/container or display signed identifiers?
     * @return string Acl, to be compared with Blob::ACL_*
     * @throws BlobException
     */
    public function getContainerAcl($containerName = '', $signedIdentifiers = false)
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);

        // Perform request
        $response = $this->performRequest($containerName, array('restype' => 'container', 'comp' => 'acl'), 'GET', array(), false, null, self::RESOURCE_CONTAINER, self::PERMISSION_READ);
        if ( ! $response->isSuccessful()) {
            throw new BlobException($this->getErrorMessage($response, 'Resource could not be accessed.'));
        }

        if ($signedIdentifiers == false)  {
            // Only private/blob/container
            $accessType = $response->getHeader(Storage::PREFIX_STORAGE_HEADER . 'blob-public-access');
            if (strtolower($accessType) == 'true') {
                $accessType = self::ACL_PUBLIC_CONTAINER;
            }
            return $accessType;
        }

        // Parse result
        $result = $this->parseResponse($response);
        if ( ! $result) {
            return array();
        }

        $entries = null;
        if ($result->SignedIdentifier) {
            if (count($result->SignedIdentifier) > 1) {
                $entries = $result->SignedIdentifier;
            } else {
                $entries = array($result->SignedIdentifier);
            }
        }

        // Return value
        $returnValue = array();
        foreach ($entries as $entry) {
            $returnValue[] = new SignedIdentifier(
                    $entry->Id,
                    $entry->AccessPolicy ? $entry->AccessPolicy->Start ? $entry->AccessPolicy->Start : '' : '',
                    $entry->AccessPolicy ? $entry->AccessPolicy->Expiry ? $entry->AccessPolicy->Expiry : '' : '',
                    $entry->AccessPolicy ? $entry->AccessPolicy->Permission ? $entry->AccessPolicy->Permission : '' : ''
                    );
        }

        return $returnValue;
    }

    /**
     * Set container ACL
     *
     * @param string $containerName Container name
     * @param bool $acl Blob::ACL_*
     * @param array $signedIdentifiers Signed identifiers
     * @throws BlobException
     */
    public function setContainerAcl($containerName = '', $acl = self::ACL_PRIVATE, $signedIdentifiers = array())
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);

        // Headers
        $headers = array();

        // Acl specified?
        if ($acl != self::ACL_PRIVATE && !is_null($acl) && $acl != '') {
            $headers[Storage::PREFIX_STORAGE_HEADER . 'blob-public-access'] = $acl;
        }

        // Policies
        $policies = null;
        if (is_array($signedIdentifiers) && count($signedIdentifiers) > 0) {
            $policies  = '';
            $policies .= '<?xml version="1.0" encoding="utf-8"?>' . "\r\n";
            $policies .= '<SignedIdentifiers>' . "\r\n";
            foreach ($signedIdentifiers as $signedIdentifier) {
                $policies .= '  <SignedIdentifier>' . "\r\n";
                $policies .= '    <Id>' . $signedIdentifier->Id . '</Id>' . "\r\n";
                $policies .= '    <AccessPolicy>' . "\r\n";
                if ($signedIdentifier->Start != '')
                $policies .= '      <Start>' . $signedIdentifier->Start . '</Start>' . "\r\n";
                if ($signedIdentifier->Expiry != '')
                $policies .= '      <Expiry>' . $signedIdentifier->Expiry . '</Expiry>' . "\r\n";
                if ($signedIdentifier->Permissions != '')
                $policies .= '      <Permission>' . $signedIdentifier->Permissions . '</Permission>' . "\r\n";
                $policies .= '    </AccessPolicy>' . "\r\n";
                $policies .= '  </SignedIdentifier>' . "\r\n";
            }
            $policies .= '</SignedIdentifiers>' . "\r\n";
        }

        // Perform request
        $response = $this->performRequest($containerName, array('restype' => 'container', 'comp' => 'acl'), 'PUT', $headers, false, $policies, self::RESOURCE_CONTAINER, self::PERMISSION_WRITE);
        if ( ! $response->isSuccessful()) {
            throw new BlobException($this->getErrorMessage($response, 'Resource could not be accessed.'));
        }
    }

    /**
     * Get container
     *
     * @param string $containerName  Container name
     * @return BlobContainer
     * @throws BlobException
     */
    public function getContainer($containerName = '')
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);

        // Perform request
        $response = $this->performRequest($containerName, array('restype' => 'container'), 'GET', array(), false, null, self::RESOURCE_CONTAINER, self::PERMISSION_READ);
        if ( ! $response->isSuccessful()) {
            throw new BlobException($this->getErrorMessage($response, 'Resource could not be accessed.'));
        }

        // Parse metadata
        $metadata = $this->parseMetadataHeaders($response->getHeaders());

        // Return container
        return new BlobContainer(
            $containerName,
            $response->getHeader('Etag'),
            $response->getHeader('Last-modified'),
            $metadata
        );
    }

    /**
     * Get container metadata
     *
     * @param string $containerName  Container name
     * @return array Key/value pairs of meta data
     * @throws BlobException
     */
    public function getContainerMetadata($containerName = '')
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);

        return $this->getContainer($containerName)->Metadata;
    }

    /**
     * Set container metadata
     *
     * Calling the Set Container Metadata operation overwrites all existing metadata that is associated with the container. It's not possible to modify an individual name/value pair.
     *
     * @param string $containerName      Container name
     * @param array  $metadata           Key/value pairs of meta data
     * @param array  $additionalHeaders  Additional headers. See http://msdn.microsoft.com/en-us/library/dd179371.aspx for more information.
     * @throws BlobException
     */
    public function setContainerMetadata($containerName = '', $metadata = array(), $additionalHeaders = array())
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);
        Assertion::isArray($metadata, 'Meta data should be an array of key and value pairs.');

        if (count($metadata) == 0) {
            return;
        }

        // Create metadata headers
        $headers = array();
        $headers = array_merge($headers, $this->generateMetadataHeaders($metadata));

        // Additional headers?
        foreach ($additionalHeaders as $key => $value) {
            $headers[$key] = $value;
        }

        // Perform request
        $response = $this->performRequest($containerName, array('restype' => 'container', 'comp' => 'metadata'), 'PUT', $headers, false, null, self::RESOURCE_CONTAINER, self::PERMISSION_WRITE);
        if ( ! $response->isSuccessful()) {
            throw new BlobException($this->getErrorMessage($response, 'Resource could not be accessed.'));
        }
    }

    /**
     * Delete container
     *
     * @param string $containerName      Container name
     * @param array  $additionalHeaders  Additional headers. See http://msdn.microsoft.com/en-us/library/dd179371.aspx for more information.
     * @throws BlobException
     */
    public function deleteContainer($containerName = '', $additionalHeaders = array())
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);

        // Additional headers?
        $headers = array();
        foreach ($additionalHeaders as $key => $value) {
            $headers[$key] = $value;
        }

        // Perform request
        $response = $this->performRequest($containerName, array('restype' => 'container'), 'DELETE', $headers, false, null, self::RESOURCE_CONTAINER, self::PERMISSION_WRITE);
        if ( ! $response->isSuccessful()) {
            throw new BlobException($this->getErrorMessage($response, 'Resource could not be accessed.'));
        }
    }

    /**
     * List containers
     *
     * @param string $prefix     Optional. Filters the results to return only containers whose name begins with the specified prefix.
     * @param int    $maxResults Optional. Specifies the maximum number of containers to return per call to Azure storage. This does NOT affect list size returned by this function. (maximum: 5000)
     * @param string $marker     Optional string value that identifies the portion of the list to be returned with the next list operation.
     * @param string $include    Optional. Include this parameter to specify that the container's metadata be returned as part of the response body. (allowed values: '', 'metadata')
     * @param int    $currentResultCount Current result count (internal use)
     * @return array
     * @throws BlobException
     */
    public function listContainers($prefix = null, $maxResults = null, $marker = null, $include = null, $currentResultCount = 0)
    {
        // Build query string
        $query = array('comp' => 'list');
        if (!is_null($prefix)) {
            $query['prefix'] = $prefix;
        }
        if (!is_null($maxResults)) {
            $query['maxresults'] = $maxResults;
        }
        if (!is_null($marker)) {
            $query['marker'] = $marker;
        }
        if (!is_null($include)) {
            $query['include'] = $include;
        }

        // Perform request
        $response = $this->performRequest('', $query, 'GET', array(), false, null, self::RESOURCE_CONTAINER, self::PERMISSION_LIST);
        if ( ! $response->isSuccessful()) {
            throw new BlobException($this->getErrorMessage($response, 'Resource could not be accessed.'));
        }

        $xmlContainers = $this->parseResponse($response)->Containers->Container;
        $xmlMarker = (string)$this->parseResponse($response)->NextMarker;

        $containers = array();
        if (!is_null($xmlContainers)) {
            for ($i = 0; $i < count($xmlContainers); $i++) {
                $containers[] = new BlobContainer(
                        (string)$xmlContainers[$i]->Name,
                        (string)$xmlContainers[$i]->Etag,
                        (string)$xmlContainers[$i]->LastModified,
                        $this->parseMetadataElement($xmlContainers[$i])
                        );
            }
        }
        $currentResultCount = $currentResultCount + count($containers);
        if (!is_null($maxResults) && $currentResultCount < $maxResults) {
            if (!is_null($xmlMarker) && $xmlMarker != '') {
                $containers = array_merge($containers, $this->listContainers($prefix, $maxResults, $xmlMarker, $include, $currentResultCount));
            }
        }
        if (!is_null($maxResults) && count($containers) > $maxResults) {
            $containers = array_slice($containers, 0, $maxResults);
        }

        return $containers;
    }

    /**
     * Put blob
     *
     * @param string $containerName      Container name
     * @param string $blobName           Blob name
     * @param string $localFileName      Local file name to be uploaded
     * @param array  $metadata           Key/value pairs of meta data
     * @param string $leaseId            Lease identifier
     * @param array  $additionalHeaders  Additional headers. See http://msdn.microsoft.com/en-us/library/dd179371.aspx for more information.
     * @return object Partial blob properties
     * @throws BlobException
     */
    public function putBlob($containerName = '', $blobName = '', $localFileName = '', $metadata = array(), $leaseId = null, $additionalHeaders = array())
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);
        Assertion::notEmpty($blobName, 'Blob name is not specified.');
        Assertion::notEmpty($localFileName, 'Local file name is not specified.');
        Assertion::file($localFileName, 'Local file name is not specified.');
        self::assertValidRootContainerBlobName($containerName, $blobName);

        // Check file size
        if (filesize($localFileName) >= self::MAX_BLOB_SIZE) {
            return $this->putLargeBlob($containerName, $blobName, $localFileName, $metadata, $leaseId, $additionalHeaders);
        }

        // Put the data to Windows Azure Storage
        return $this->putBlobData($containerName, $blobName, file_get_contents($localFileName), $metadata, $leaseId, $additionalHeaders);
    }

    /**
     * Put blob data
     *
     * @param string $containerName      Container name
     * @param string $blobName           Blob name
     * @param mixed  $data               Data to store
     * @param array  $metadata           Key/value pairs of meta data
     * @param string $leaseId            Lease identifier
     * @param array  $additionalHeaders  Additional headers. See http://msdn.microsoft.com/en-us/library/dd179371.aspx for more information.
     * @return object Partial blob properties
     * @throws BlobException
     */
    public function putBlobData($containerName = '', $blobName = '', $data = '', $metadata = array(), $leaseId = null, $additionalHeaders = array())
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);
        Assertion::notEmpty($blobName, 'Blob name is not specified.');
        self::assertValidRootContainerBlobName($containerName, $blobName);

        // Create metadata headers
        $headers = array();
        if (!is_null($leaseId)) {
            $headers['x-ms-lease-id'] = $leaseId;
        }
        $headers = array_merge($headers, $this->generateMetadataHeaders($metadata));

        // Additional headers?
        foreach ($additionalHeaders as $key => $value) {
            $headers[$key] = $value;
        }

        // Specify blob type
        $headers[Storage::PREFIX_STORAGE_HEADER . 'blob-type'] = self::BLOBTYPE_BLOCK;

        // Resource name
        $resourceName = self::createResourceName($containerName , $blobName);

        // Perform request
        $response = $this->performRequest($resourceName, array(), 'PUT', $headers, false, $data, self::RESOURCE_BLOB, self::PERMISSION_WRITE);
        if ( ! $response->isSuccessful()) {
            throw new BlobException($this->getErrorMessage($response, 'Resource could not be accessed.'));
        }

        return new BlobInstance(
            $containerName,
            $blobName,
            null,
            $response->getHeader('Etag'),
            $response->getHeader('Last-modified'),
            $this->getBaseUrl() . '/' . $containerName . '/' . $blobName,
            strlen($data),
                '',
                '',
                '',
            false,
            $metadata
        );
    }

    /**
     * Put large blob (> 64 MB)
     *
     * @param string $containerName Container name
     * @param string $blobName Blob name
     * @param string $localFileName Local file name to be uploaded
     * @param array  $metadata      Key/value pairs of meta data
     * @param string $leaseId       Lease identifier
     * @param array  $additionalHeaders  Additional headers. See http://msdn.microsoft.com/en-us/library/dd179371.aspx for more information.
     * @return object Partial blob properties
     * @throws BlobException
     */
    public function putLargeBlob($containerName = '', $blobName = '', $localFileName = '', $metadata = array(), $leaseId = null, $additionalHeaders = array())
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);
        Assertion::notEmpty($blobName, 'Blob name is not specified.');
        Assertion::notEmpty($localFileName, 'Local file name is not specified.');
        Assertion::file($localFileName, 'Local file name is not specified.');
        self::assertValidRootContainerBlobName($containerName, $blobName);

        // Check file size
        if (filesize($localFileName) < self::MAX_BLOB_SIZE) {
            return $this->putBlob($containerName, $blobName, $localFileName, $metadata, $leaseId, $additionalHeaders);
        }

        // Determine number of parts
        $numberOfParts = ceil( filesize($localFileName) / self::MAX_BLOB_TRANSFER_SIZE );

        // Generate block id's
        $blockIdentifiers = array();
        for ($i = 0; $i < $numberOfParts; $i++) {
            $blockIdentifiers[] = $this->generateBlockId($i);
        }

        // Open file
        $fp = fopen($localFileName, 'r');
        if ($fp === false) {
            throw new BlobException('Could not open local file.');
        }

        // Upload parts
        for ($i = 0; $i < $numberOfParts; $i++) {
            // Seek position in file
            fseek($fp, $i * self::MAX_BLOB_TRANSFER_SIZE);

            // Read contents
            $fileContents = fread($fp, self::MAX_BLOB_TRANSFER_SIZE);

            // Put block
            $this->putBlock($containerName, $blobName, $blockIdentifiers[$i], $fileContents, $leaseId);

            // Dispose file contents
            $fileContents = null;
            unset($fileContents);
        }

        // Close file
        fclose($fp);

        // Put block list
        $this->putBlockList($containerName, $blobName, $blockIdentifiers, $metadata, $leaseId, $additionalHeaders);

        // Return information of the blob
        return $this->getBlobInstance($containerName, $blobName, null, $leaseId);
    }

    /**
     * Put large blob block
     *
     * @param string $containerName Container name
     * @param string $blobName      Blob name
     * @param string $identifier    Block ID
     * @param array  $contents      Contents of the block
     * @param string $leaseId       Lease identifier
     * @throws BlobException
     */
    public function putBlock($containerName = '', $blobName = '', $identifier = '', $contents = '', $leaseId = null)
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);
        Assertion::notEmpty($blobName, 'Blob name is not specified.');
        Assertion::notEmpty($identifier, 'Block identifier is not specified.');
        self::assertValidRootContainerBlobName($containerName, $blobName);

        if (strlen($contents) > self::MAX_BLOB_TRANSFER_SIZE) {
            throw new BlobException('Block size is too big.');
        }

        // Headers
        $headers = array();
        if (!is_null($leaseId)) {
            $headers['x-ms-lease-id'] = $leaseId;
        }

        // Resource name
        $resourceName = self::createResourceName($containerName , $blobName);

        // Upload
        $response = $this->performRequest($resourceName, array('comp' => 'block', 'blockid' => base64_encode($identifier)), 'PUT', $headers, false, $contents, self::RESOURCE_BLOB, self::PERMISSION_WRITE);
        if ( ! $response->isSuccessful()) {
            throw new BlobException($this->getErrorMessage($response, 'Resource could not be accessed.'));
        }
    }

    /**
     * Put block list
     *
     * @param string $containerName      Container name
     * @param string $blobName           Blob name
     * @param array $blockList           Array of block identifiers
     * @param array  $metadata           Key/value pairs of meta data
     * @param string $leaseId            Lease identifier
     * @param array  $additionalHeaders  Additional headers. See http://msdn.microsoft.com/en-us/library/dd179371.aspx for more information.
     * @throws BlobException
     */
    public function putBlockList($containerName = '', $blobName = '', $blockList = array(), $metadata = array(), $leaseId = null, $additionalHeaders = array())
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);
        Assertion::notEmpty($blobName, 'Blob name is not specified.');
        Assertion::notEmpty($blockList, 'Block list does not contain any elements.');
        self::assertValidRootContainerBlobName($containerName, $blobName);

        // Generate block list
        $blocks = '';
        foreach ($blockList as $block) {
            $blocks .= '  <Latest>' . base64_encode($block) . '</Latest>' . "\n";
        }

        // Generate block list request
        $fileContents = utf8_encode(implode("\n", array(
            '<?xml version="1.0" encoding="utf-8"?>',
            '<BlockList>',
            $blocks,
            '</BlockList>'
        )));

        // Create metadata headers
        $headers = array();
        if (!is_null($leaseId)) {
            $headers['x-ms-lease-id'] = $leaseId;
        }
        $headers = array_merge($headers, $this->generateMetadataHeaders($metadata));

        // Additional headers?
        foreach ($additionalHeaders as $key => $value) {
            $headers[$key] = $value;
        }

        // Resource name
        $resourceName = self::createResourceName($containerName , $blobName);

        // Perform request
        $response = $this->performRequest($resourceName, array('comp' => 'blocklist'), 'PUT', $headers, false, $fileContents, self::RESOURCE_BLOB, self::PERMISSION_WRITE);
        if ( ! $response->isSuccessful()) {
            throw new BlobException($this->getErrorMessage($response, 'Resource could not be accessed.'));
        }
    }

    /**
     * Get block list
     *
     * @param string $containerName Container name
     * @param string $blobName      Blob name
     * @param string $snapshotId    Snapshot identifier
     * @param string $leaseId       Lease identifier
     * @param integer $type         Type of block list to retrieve. 0 = all, 1 = committed, 2 = uncommitted
     * @return array
     * @throws BlobException
     */
    public function getBlockList($containerName = '', $blobName = '', $snapshotId = null, $leaseId = null, $type = 0)
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);
        Assertion::notEmpty($blobName, 'Blob name is not specified.');

        if ($type < 0 || $type > 2) {
            throw new BlobException('Invalid type of block list to retrieve.');
        }

        // Set $blockListType
        $blockListType = 'all';
        if ($type == 1) {
            $blockListType = 'committed';
        }
        if ($type == 2) {
            $blockListType = 'uncommitted';
        }

        // Headers
        $headers = array();
        if (!is_null($leaseId)) {
            $headers['x-ms-lease-id'] = $leaseId;
        }

        // Build query string
        $query = array('comp' => 'blocklist', 'blocklisttype' => $blockListType);
        if (!is_null($snapshotId)) {
            $query['snapshot'] = $snapshotId;
        }

        // Resource name
        $resourceName = self::createResourceName($containerName , $blobName);

        // Perform request
        $response = $this->performRequest($resourceName, $query, 'GET', $headers, false, null, self::RESOURCE_BLOB, self::PERMISSION_READ);
        if ( ! $response->isSuccessful()) {
            throw new BlobException($this->getErrorMessage($response, 'Resource could not be accessed.'));
        }

        // Parse response
        $blockList = $this->parseResponse($response);

        // Create return value
        $returnValue = array();
        if ($blockList->CommittedBlocks) {
            foreach ($blockList->CommittedBlocks->Block as $block) {
                $returnValue['CommittedBlocks'][] = (object)array(
                    'Name' => (string)$block->Name,
                    'Size' => (string)$block->Size
                );
            }
        }
        if ($blockList->UncommittedBlocks)  {
            foreach ($blockList->UncommittedBlocks->Block as $block) {
                $returnValue['UncommittedBlocks'][] = (object)array(
                    'Name' => (string)$block->Name,
                    'Size' => (string)$block->Size
                );
            }
        }

        return $returnValue;
    }

    /**
     * Create page blob
     *
     * @param string $containerName      Container name
     * @param string $blobName           Blob name
     * @param int    $size               Size of the page blob in bytes
     * @param array  $metadata           Key/value pairs of meta data
     * @param string $leaseId            Lease identifier
     * @param array  $additionalHeaders  Additional headers. See http://msdn.microsoft.com/en-us/library/dd179371.aspx for more information.
     * @return object Partial blob properties
     * @throws BlobException
     */
    public function createPageBlob($containerName = '', $blobName = '', $size = 0, $metadata = array(), $leaseId = null, $additionalHeaders = array())
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);
        Assertion::notEmpty($blobName, 'Blob name is not specified.');
        self::assertValidRootContainerBlobName($containerName, $blobName);

        if ($size <= 0) {
            throw new BlobException('Page blob size must be specified.');
        }

        // Create metadata headers
        $headers = array();
        if (!is_null($leaseId)) {
            $headers['x-ms-lease-id'] = $leaseId;
        }
        $headers = array_merge($headers, $this->generateMetadataHeaders($metadata));

        // Additional headers?
        foreach ($additionalHeaders as $key => $value) {
            $headers[$key] = $value;
        }

        // Specify blob type & blob length
        $headers[Storage::PREFIX_STORAGE_HEADER . 'blob-type'] = self::BLOBTYPE_PAGE;
        $headers[Storage::PREFIX_STORAGE_HEADER . 'blob-content-length'] = $size;
        $headers['Content-Length'] = 0;

        // Resource name
        $resourceName = self::createResourceName($containerName , $blobName);

        // Perform request
        $response = $this->performRequest($resourceName, array(), 'PUT', $headers, false, '', self::RESOURCE_BLOB, self::PERMISSION_WRITE);
        if ( ! $response->isSuccessful()) {
            throw new BlobException($this->getErrorMessage($response, 'Resource could not be accessed.'));
        }

        return new BlobInstance(
            $containerName,
            $blobName,
            null,
            $response->getHeader('Etag'),
            $response->getHeader('Last-modified'),
            $this->getBaseUrl() . '/' . $containerName . '/' . $blobName,
            $size,
                '',
                '',
                '',
            false,
            $metadata
        );
    }

    /**
     * Put page in page blob
     *
     * @param string $containerName      Container name
     * @param string $blobName           Blob name
     * @param int    $startByteOffset    Start byte offset
     * @param int    $endByteOffset      End byte offset
     * @param mixed  $contents           Page contents
     * @param string $writeMethod        Write method (Blob::PAGE_WRITE_*)
     * @param string $leaseId            Lease identifier
     * @param array  $additionalHeaders  Additional headers. See http://msdn.microsoft.com/en-us/library/dd179371.aspx for more information.
     * @throws BlobException
     */
    public function putPage($containerName = '', $blobName = '', $startByteOffset = 0, $endByteOffset = 0, $contents = '', $writeMethod = self::PAGE_WRITE_UPDATE, $leaseId = null, $additionalHeaders = array())
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);
        Assertion::notEmpty($blobName, 'Blob name is not specified.');
        self::assertValidRootContainerBlobName($containerName, $blobName);

        if ($startByteOffset % 512 != 0) {
            throw new BlobException('Start byte offset must be a modulus of 512.');
        }
        if (($endByteOffset + 1) % 512 != 0) {
            throw new BlobException('End byte offset must be a modulus of 512 minus 1.');
        }

        // Determine size
        $size = strlen($contents);
        if ($size >= self::MAX_BLOB_TRANSFER_SIZE) {
            throw new BlobException('Page blob size must not be larger than ' + self::MAX_BLOB_TRANSFER_SIZE . ' bytes.');
        }

        // Create metadata headers
        $headers = array();
        if (!is_null($leaseId)) {
            $headers['x-ms-lease-id'] = $leaseId;
        }

        // Additional headers?
        foreach ($additionalHeaders as $key => $value) {
            $headers[$key] = $value;
        }

        // Specify range
        $headers['Range'] = 'bytes=' . $startByteOffset . '-' . $endByteOffset;

        // Write method
        $headers[Storage::PREFIX_STORAGE_HEADER . 'page-write'] = $writeMethod;

        // Resource name
        $resourceName = self::createResourceName($containerName , $blobName);

        // Perform request
        $response = $this->performRequest($resourceName, array('comp' => 'page'), 'PUT', $headers, false, $contents, self::RESOURCE_BLOB, self::PERMISSION_WRITE);
        if ( ! $response->isSuccessful()) {
            throw new BlobException($this->getErrorMessage($response, 'Resource could not be accessed.'));
        }
    }

    /**
     * Put page in page blob
     *
     * @param string $containerName      Container name
     * @param string $blobName           Blob name
     * @param int    $startByteOffset    Start byte offset
     * @param int    $endByteOffset      End byte offset
     * @param string $leaseId            Lease identifier
     * @return array Array of page ranges
     * @throws BlobException
     */
    public function getPageRegions($containerName = '', $blobName = '', $startByteOffset = 0, $endByteOffset = 0, $leaseId = null)
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);
        Assertion::notEmpty($blobName, 'Blob name is not specified.');
        self::assertValidRootContainerBlobName($containerName, $blobName);

        if ($startByteOffset % 512 != 0) {
            throw new BlobException('Start byte offset must be a modulus of 512.');
        }
        if ($endByteOffset > 0 && ($endByteOffset + 1) % 512 != 0) {
            throw new BlobException('End byte offset must be a modulus of 512 minus 1.');
        }

        // Create metadata headers
        $headers = array();
        if (!is_null($leaseId)) {
            $headers['x-ms-lease-id'] = $leaseId;
        }

        // Specify range?
        if ($endByteOffset > 0) {
            $headers['Range'] = 'bytes=' . $startByteOffset . '-' . $endByteOffset;
        }

        // Resource name
        $resourceName = self::createResourceName($containerName , $blobName);

        // Perform request
        $response = $this->performRequest($resourceName, array('comp' => 'pagelist'), 'GET', $headers, false, null, self::RESOURCE_BLOB, self::PERMISSION_WRITE);
        if ( ! $response->isSuccessful()) {
            throw new BlobException($this->getErrorMessage($response, 'Resource could not be accessed.'));
        }

        $result = $this->parseResponse($response);
        $xmlRanges = null;
        if (count($result->PageRange) > 1) {
            $xmlRanges = $result->PageRange;
        } else {
            $xmlRanges = array($result->PageRange);
        }

        $ranges = array();
        for ($i = 0; $i < count($xmlRanges); $i++) {
            $ranges[] = new PageRegionInstance(
            (int)$xmlRanges[$i]->Start,
            (int)$xmlRanges[$i]->End
            );
        }

        return $ranges;
    }

    /**
     * Copy blob
     *
     * @param string $sourceContainerName       Source container name
     * @param string $sourceBlobName            Source blob name
     * @param string $destinationContainerName  Destination container name
     * @param string $destinationBlobName       Destination blob name
     * @param array  $metadata                  Key/value pairs of meta data
     * @param string $sourceSnapshotId          Source snapshot identifier
     * @param string $destinationLeaseId        Destination lease identifier
     * @param array  $additionalHeaders         Additional headers. See http://msdn.microsoft.com/en-us/library/dd894037.aspx for more information.
     * @return object Partial blob properties
     * @throws BlobException
     */
    public function copyBlob($sourceContainerName = '', $sourceBlobName = '', $destinationContainerName = '', $destinationBlobName = '', $metadata = array(), $sourceSnapshotId = null, $destinationLeaseId = null, $additionalHeaders = array())
    {
        Assertion::notEmpty($sourceContainerName, 'Source container name is not specified.');
        self::assertValidContainerName($sourceContainerName);
        Assertion::notEmpty($sourceBlobName, 'Source blob name is not specified.');
        self::assertValidRootContainerBlobName($sourceContainerName, $sourceBlobName);

        Assertion::notEmpty($destinationContainerName, 'Destination container name is not specified.');
        self::assertValidContainerName($destinationContainerName);
        Assertion::notEmpty($destinationBlobName, 'Destination blob name is not specified.');
        self::assertValidRootContainerBlobName($destinationContainerName, $destinationBlobName);

        // Create metadata headers
        $headers = array();
        if (!is_null($destinationLeaseId)) {
            $headers['x-ms-lease-id'] = $destinationLeaseId;
        }
        $headers = array_merge($headers, $this->generateMetadataHeaders($metadata));

        // Additional headers?
        foreach ($additionalHeaders as $key => $value) {
            $headers[$key] = $value;
        }

        // Resource names
        $sourceResourceName = self::createResourceName($sourceContainerName, $sourceBlobName);
        if (!is_null($sourceSnapshotId)) {
            $sourceResourceName .= '?snapshot=' . $sourceSnapshotId;
        }
        $destinationResourceName = self::createResourceName($destinationContainerName, $destinationBlobName);

        // Set source blob
        $headers["x-ms-copy-source"] = '/' . $this->accountName . '/' . $sourceResourceName;

        // Perform request
        $response = $this->performRequest($destinationResourceName, array(), 'PUT', $headers, false, null, self::RESOURCE_BLOB, self::PERMISSION_WRITE);
        if ( ! $response->isSuccessful()) {
            throw new BlobException($this->getErrorMessage($response, 'Resource could not be accessed.'));
        }

        return new BlobInstance(
            $destinationContainerName,
            $destinationBlobName,
            null,
            $response->getHeader('Etag'),
            $response->getHeader('Last-modified'),
            $this->getBaseUrl() . '/' . $destinationContainerName . '/' . $destinationBlobName,
            0,
                '',
                '',
                '',
            false,
            $metadata
        );
    }

    /**
     * Get blob
     *
     * @param string $containerName      Container name
     * @param string $blobName           Blob name
     * @param string $localFileName      Local file name to store downloaded blob
     * @param string $snapshotId         Snapshot identifier
     * @param string $leaseId            Lease identifier
     * @param array  $additionalHeaders  Additional headers. See http://msdn.microsoft.com/en-us/library/dd179371.aspx for more information.
     * @throws BlobException
     */
    public function getBlob($containerName = '', $blobName = '', $localFileName = '', $snapshotId = null, $leaseId = null, $additionalHeaders = array())
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);
        Assertion::notEmpty($blobName, 'Blob name is not specified.');
        Assertion::notEmpty($localFileName, 'Local file name is not specified.');

        // Fetch data
        file_put_contents($localFileName, $this->getBlobData($containerName, $blobName, $snapshotId, $leaseId, $additionalHeaders));
    }

    /**
     * Get blob data
     *
     * @param string $containerName      Container name
     * @param string $blobName           Blob name
     * @param string $snapshotId         Snapshot identifier
     * @param string $leaseId            Lease identifier
     * @param array  $additionalHeaders  Additional headers. See http://msdn.microsoft.com/en-us/library/dd179371.aspx for more information.
     * @return mixed Blob contents
     * @throws BlobException
     */
    public function getBlobData($containerName = '', $blobName = '', $snapshotId = null, $leaseId = null, $additionalHeaders = array())
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);
        Assertion::notEmpty($blobName, 'Blob name is not specified.');

        // Build query string
        $query = array();
        if (!is_null($snapshotId)) {
            $query['snapshot'] = $snapshotId;
        }

        // Additional headers?
        $headers = array();
        if (!is_null($leaseId)) {
            $headers['x-ms-lease-id'] = $leaseId;
        }
        foreach ($additionalHeaders as $key => $value) {
            $headers[$key] = $value;
        }

        // Resource name
        $resourceName = self::createResourceName($containerName , $blobName);

        // Perform request
        $response = $this->performRequest($resourceName, $query, 'GET', $headers, false, null, self::RESOURCE_BLOB, self::PERMISSION_READ);
        if ( ! $response->isSuccessful()) {
            throw new BlobException($this->getErrorMessage($response, 'Resource could not be accessed.'));
        }

        return $response->getContent();
    }

    /**
     * Get blob instance
     *
     * @param string $containerName      Container name
     * @param string $blobName           Blob name
     * @param string $snapshotId         Snapshot identifier
     * @param string $leaseId            Lease identifier
     * @param array  $additionalHeaders  Additional headers. See http://msdn.microsoft.com/en-us/library/dd179371.aspx for more information.
     * @return BlobInstance
     * @throws BlobException
     */
    public function getBlobInstance($containerName = '', $blobName = '', $snapshotId = null, $leaseId = null, $additionalHeaders = array())
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);
        Assertion::notEmpty($blobName, 'Blob name is not specified.');
        self::assertValidRootContainerBlobName($containerName, $blobName);

        // Build query string
        $query = array();
        if (!is_null($snapshotId)) {
            $query['snapshot'] = $snapshotId;
        }

        // Additional headers?
        $headers = array();
        if (!is_null($leaseId)) {
            $headers['x-ms-lease-id'] = $leaseId;
        }
        foreach ($additionalHeaders as $key => $value) {
            $headers[$key] = $value;
        }

        // Resource name
        $resourceName = self::createResourceName($containerName , $blobName);

        // Perform request
        $response = $this->performRequest($resourceName, $query, 'HEAD', $headers, false, null, self::RESOURCE_BLOB, self::PERMISSION_READ);
        if ( ! $response->isSuccessful()) {
            throw new BlobException($this->getErrorMessage($response, 'Resource could not be accessed.'));
        }

        // Parse metadata
        $metadata = $this->parseMetadataHeaders($response->getHeaders());

        // Return blob
        return new BlobInstance(
            $containerName,
            $blobName,
            $snapshotId,
            $response->getHeader('Etag'),
            $response->getHeader('Last-modified'),
            $this->getBaseUrl() . '/' . $containerName . '/' . $blobName,
            $response->getHeader('Content-Length'),
            $response->getHeader('Content-Type'),
            $response->getHeader('Content-Encoding'),
            $response->getHeader('Content-Language'),
            $response->getHeader('Cache-Control'),
            $response->getHeader('x-ms-blob-type'),
            $response->getHeader('x-ms-lease-status'),
            false,
            $metadata
        );
    }

    /**
     * Get blob url
     *
     * @param string $containerName  Container name
     * @param string $blobName       Blob name
     * @param string $snapshotId     Snapshot identifier
     * @param string $leaseId        Lease identifier
     * @return array Key/value pairs of meta data
     * @throws BlobException
     */
    public function getBlobUrl($containerName = '', $blobName = '', $snapshotId = null, $leaseId = null)
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);
        Assertion::notEmpty($blobName, 'Blob name is not specified.');
        self::assertValidRootContainerBlobName($containerName, $blobName);

        return $this->getBlobInstance($containerName, $blobName, $snapshotId, $leaseId)->Url;
    }

    /**
     * Get blob metadata
     *
     * @param string $containerName  Container name
     * @param string $blobName       Blob name
     * @param string $snapshotId     Snapshot identifier
     * @param string $leaseId        Lease identifier
     * @return array Key/value pairs of meta data
     * @throws BlobException
     */
    public function getBlobMetadata($containerName = '', $blobName = '', $snapshotId = null, $leaseId = null)
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);
        Assertion::notEmpty($blobName, 'Blob name is not specified.');
        self::assertValidRootContainerBlobName($containerName, $blobName);

        return $this->getBlobInstance($containerName, $blobName, $snapshotId, $leaseId)->Metadata;
    }

    /**
     * Set blob metadata
     *
     * Calling the Set Blob Metadata operation overwrites all existing metadata that is associated with the blob. It's not possible to modify an individual name/value pair.
     *
     * @param string $containerName      Container name
     * @param string $blobName           Blob name
     * @param array  $metadata           Key/value pairs of meta data
     * @param string $leaseId            Lease identifier
     * @param array  $additionalHeaders  Additional headers. See http://msdn.microsoft.com/en-us/library/dd179371.aspx for more information.
     * @throws BlobException
     */
    public function setBlobMetadata($containerName = '', $blobName = '', $metadata = array(), $leaseId = null, $additionalHeaders = array())
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);
        Assertion::notEmpty($blobName, 'Blob name is not specified.');
        self::assertValidRootContainerBlobName($containerName, $blobName);

        if (count($metadata) == 0) {
            return;
        }

        // Create metadata headers
        $headers = array();
        if (!is_null($leaseId)) {
            $headers['x-ms-lease-id'] = $leaseId;
        }
        $headers = array_merge($headers, $this->generateMetadataHeaders($metadata));

        // Additional headers?
        foreach ($additionalHeaders as $key => $value) {
            $headers[$key] = $value;
        }

        // Perform request
        $response = $this->performRequest($containerName . '/' . $blobName, array('comp' => 'metadata'), 'PUT', $headers, false, null, self::RESOURCE_BLOB, self::PERMISSION_WRITE);
        if ( ! $response->isSuccessful()) {
            throw new BlobException($this->getErrorMessage($response, 'Resource could not be accessed.'));
        }
    }

    /**
     * Set blob properties
     *
     * All available properties are listed at http://msdn.microsoft.com/en-us/library/ee691966.aspx and should be provided in the $additionalHeaders parameter.
     *
     * @param string $containerName      Container name
     * @param string $blobName           Blob name
     * @param string $leaseId            Lease identifier
     * @param array  $additionalHeaders  Additional headers. See http://msdn.microsoft.com/en-us/library/dd179371.aspx for more information.
     * @throws BlobException
     */
    public function setBlobProperties($containerName = '', $blobName = '', $leaseId = null, array $additionalHeaders = array())
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);
        Assertion::notEmpty($blobName, 'Blob name is not specified.');
        self::assertValidRootContainerBlobName($containerName, $blobName);
        Assertion::notEmpty($additionalHeaders, 'No additional headers are specified.');

        // Create headers
        $headers = array();

        // Lease set?
        if (!is_null($leaseId)) {
            $headers['x-ms-lease-id'] = $leaseId;
        }

        // Additional headers?
        foreach ($additionalHeaders as $key => $value) {
            $headers[$key] = $value;
        }

        // Perform request
        $response = $this->performRequest($containerName . '/' . $blobName, array('comp' => 'properties'), 'PUT', $headers, false, null, self::RESOURCE_BLOB, self::PERMISSION_WRITE);
        if ( ! $response->isSuccessful()) {
            throw new BlobException($this->getErrorMessage($response, 'Resource could not be accessed.'));
        }
    }

    /**
     * Get blob properties
     *
     * @param string $containerName      Container name
     * @param string $blobName           Blob name
     * @param string $snapshotId         Snapshot identifier
     * @param string $leaseId            Lease identifier
     * @return BlobInstance
     * @throws BlobException
     */
    public function getBlobProperties($containerName = '', $blobName = '', $snapshotId = null, $leaseId = null)
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);
        Assertion::notEmpty($blobName, 'Blob name is not specified.');
        self::assertValidRootContainerBlobName($containerName, $blobName);

        return $this->getBlobInstance($containerName, $blobName, $snapshotId, $leaseId);
    }

    /**
     * Delete blob
     *
     * @param string $containerName      Container name
     * @param string $blobName           Blob name
     * @param string $snapshotId         Snapshot identifier
     * @param string $leaseId            Lease identifier
     * @param array  $additionalHeaders  Additional headers. See http://msdn.microsoft.com/en-us/library/dd179371.aspx for more information.
     * @throws BlobException
     */
    public function deleteBlob($containerName = '', $blobName = '', $snapshotId = null, $leaseId = null, $additionalHeaders = array())
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);
        Assertion::notEmpty($blobName, 'Blob name is not specified.');
        self::assertValidRootContainerBlobName($containerName, $blobName);

        // Build query string
        $query = array();
        if (!is_null($snapshotId)) {
            $query['snapshot'] = $snapshotId;
        }

        // Additional headers?
        $headers = array();
        if (!is_null($leaseId)) {
            $headers['x-ms-lease-id'] = $leaseId;
        }
        foreach ($additionalHeaders as $key => $value) {
            $headers[$key] = $value;
        }

        // Resource name
        $resourceName = self::createResourceName($containerName , $blobName);

        // Perform request
        $response = $this->performRequest($resourceName, $query, 'DELETE', $headers, false, null, self::RESOURCE_BLOB, self::PERMISSION_WRITE);
        if ( ! $response->isSuccessful()) {
            throw new BlobException($this->getErrorMessage($response, 'Resource could not be accessed.'));
        }
    }

    /**
     * Snapshot blob
     *
     * @param string $containerName      Container name
     * @param string $blobName           Blob name
     * @param array  $metadata           Key/value pairs of meta data
     * @param array  $additionalHeaders  Additional headers. See http://msdn.microsoft.com/en-us/library/dd179371.aspx for more information.
     * @return string Date/Time value representing the snapshot identifier.
     * @throws BlobException
     */
    public function snapshotBlob($containerName = '', $blobName = '', $metadata = array(), $additionalHeaders = array())
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);
        Assertion::notEmpty($blobName, 'Blob name is not specified.');
        self::assertValidRootContainerBlobName($containerName, $blobName);

        // Additional headers?
        $headers = array();
        foreach ($additionalHeaders as $key => $value) {
            $headers[$key] = $value;
        }

        // Resource name
        $resourceName = self::createResourceName($containerName , $blobName);

        // Perform request
        $response = $this->performRequest($resourceName, array('comp' => 'snapshot'), 'PUT', $headers, false, null, self::RESOURCE_BLOB, self::PERMISSION_WRITE);
        if ( ! $response->isSuccessful()) {
            throw new BlobException($this->getErrorMessage($response, 'Resource could not be accessed.'));
        }

        return $response->getHeader('x-ms-snapshot');
    }

    /**
     * Lease blob - See (http://msdn.microsoft.com/en-us/library/ee691972.aspx)
     *
     * @param string $containerName      Container name
     * @param string $blobName           Blob name
     * @param string $leaseAction        Lease action (Blob::LEASE_*)
     * @param string $leaseId            Lease identifier, required to renew the lease or to release the lease.
     * @return Microsoft_WindowsAzure_Storage_LeaseInstance Lease instance
     * @throws BlobException
     */
    public function leaseBlob($containerName = '', $blobName = '', $leaseAction = self::LEASE_ACQUIRE, $leaseId = null)
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);
        Assertion::notEmpty($blobName, 'Blob name is not specified.');
        self::assertValidRootContainerBlobName($containerName, $blobName);

        // Additional headers?
        $headers = array();
        $headers['x-ms-lease-action'] = strtolower($leaseAction);
        if (!is_null($leaseId)) {
            $headers['x-ms-lease-id'] = $leaseId;
        }

        // Resource name
        $resourceName = self::createResourceName($containerName , $blobName);

        // Perform request
        $response = $this->performRequest($resourceName, array('comp' => 'lease'), 'PUT', $headers, false, null, self::RESOURCE_BLOB, self::PERMISSION_WRITE);
        if ( ! $response->isSuccessful()) {
            throw new BlobException($this->getErrorMessage($response, 'Resource could not be accessed.'));
        }

        return new LeaseInstance(
            $containerName,
            $blobName,
            $response->getHeader('x-ms-lease-id'),
            $response->getHeader('x-ms-lease-time')
        );
    }

    /**
     * List blobs
     *
     * @param string $containerName Container name
     * @param string $prefix     Optional. Filters the results to return only blobs whose name begins with the specified prefix.
     * @param string $delimiter  Optional. Delimiter, i.e. '/', for specifying folder hierarchy
     * @param int    $maxResults Optional. Specifies the maximum number of blobs to return per call to Azure storage. This does NOT affect list size returned by this function. (maximum: 5000)
     * @param string $marker     Optional string value that identifies the portion of the list to be returned with the next list operation.
     * @param string $include    Optional. Specifies that the response should include one or more of the following subsets: '', 'metadata', 'snapshots', 'uncommittedblobs'). Multiple values can be added separated with a comma (,)
     * @param int    $currentResultCount Current result count (internal use)
     * @return array
     * @throws BlobException
     */
    public function listBlobs($containerName = '', $prefix = '', $delimiter = '', $maxResults = null, $marker = null, $include = null, $currentResultCount = 0)
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);

        // Build query string
        $query = array('restype' => 'container', 'comp' => 'list');
        if (!is_null($prefix)) {
            $query[] = 'prefix=' . $prefix;
        }
        if ($delimiter !== '') {
            $query['delimiter'] = $delimiter;
        }
        if (!is_null($maxResults)) {
            $query['maxresults'] = $maxResults;
        }
        if (!is_null($marker)) {
            $query['marker'] = $marker;
        }
        if (!is_null($include)) {
            $query['include'] = $include;
        }

        // Perform request
        $response = $this->performRequest($containerName, $query, 'GET', array(), false, null, self::RESOURCE_BLOB, self::PERMISSION_LIST);
        if ( ! $response->isSuccessful()) {
            throw new BlobException($this->getErrorMessage($response, 'Resource could not be accessed.'));
        }

        // Return value
        $blobs = array();

        // Blobs
        $xmlBlobs = $this->parseResponse($response)->Blobs->Blob;
        if (!is_null($xmlBlobs)) {
            for ($i = 0; $i < count($xmlBlobs); $i++) {
                $properties = (array)$xmlBlobs[$i]->Properties;

                $blobs[] = new BlobInstance(
                $containerName,
                (string)$xmlBlobs[$i]->Name,
                (string)$xmlBlobs[$i]->Snapshot,
                (string)$properties['Etag'],
                (string)$properties['Last-Modified'],
                (string)$xmlBlobs[$i]->Url,
                (string)$properties['Content-Length'],
                (string)$properties['Content-Type'],
                (string)$properties['Content-Encoding'],
                (string)$properties['Content-Language'],
                (string)$properties['Cache-Control'],
                (string)$properties['BlobType'],
                (string)$properties['LeaseStatus'],
                false,
                $this->parseMetadataElement($xmlBlobs[$i])
                );
            }
        }

        // Blob prefixes (folders)
        $xmlBlobs = $this->parseResponse($response)->Blobs->BlobPrefix;

        if (!is_null($xmlBlobs)) {
            for ($i = 0; $i < count($xmlBlobs); $i++) {
                $blobs[] = new BlobInstance(
                $containerName,
                (string)$xmlBlobs[$i]->Name,
                null,
                    '',
                    '',
                    '',
                0,
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                true,
                $this->parseMetadataElement($xmlBlobs[$i])
                );
            }
        }

        // More blobs?
        $xmlMarker = (string)$this->parseResponse($response)->NextMarker;
        $currentResultCount = $currentResultCount + count($blobs);
        if (!is_null($maxResults) && $currentResultCount < $maxResults) {
            if (!is_null($xmlMarker) && $xmlMarker != '') {
                $blobs = array_merge($blobs, $this->listBlobs($containerName, $prefix, $delimiter, $maxResults, $marker, $include, $currentResultCount));
            }
        }
        if (!is_null($maxResults) && count($blobs) > $maxResults) {
            $blobs = array_slice($blobs, 0, $maxResults);
        }

        return $blobs;
    }

    /**
     * Generate shared access URL
     *
     * @param string $containerName  Container name
     * @param string $blobName       Blob name
     * @param string $resource       Signed resource - container (c) - blob (b)
     * @param string $permissions    Signed permissions - read (r), write (w), delete (d) and list (l)
     * @param string $start          The time at which the Shared Access Signature becomes valid.
     * @param string $expiry         The time at which the Shared Access Signature becomes invalid.
     * @param string $identifier     Signed identifier
     * @return string
     */
    public function generateSharedAccessUrl($containerName = '', $blobName = '', $resource = 'b', $permissions = 'r', $start = '', $expiry = '', $identifier = '')
    {
        Assertion::notEmpty($containerName, 'Container name is not specified');
        self::assertValidContainerName($containerName);

        // Resource name
        $resourceName = self::createResourceName($containerName , $blobName);

        // Generate URL
        return $this->getBaseUrl() . '/' . $resourceName . '?' .
            $this->sharedAccessSignatureCredentials->createSignedQueryString(
            $resourceName,
                    '',
            $resource,
            $permissions,
            $start,
            $expiry,
            $identifier
        );
    }

    /**
     * Register this object as stream wrapper client
     *
     * @param  string $name Protocol name
     * @return Blob
     */
    public function registerAsClient($name)
    {
        self::$wrapperClients[$name] = $this;
        return $this;
    }

    /**
     * Unregister this object as stream wrapper client
     *
     * @param  string $name Protocol name
     * @return Blob
     */
    public function unregisterAsClient($name)
    {
        unset(self::$wrapperClients[$name]);
        return $this;
    }

    /**
     * Get wrapper client for stream type
     *
     * @param  string $name Protocol name
     * @return Blob
     */
    public static function getWrapperClient($name)
    {
        return self::$wrapperClients[$name];
    }

    /**
     * Register this object as stream wrapper
     *
     * @param  string $name Protocol name
     */
    public function registerStreamWrapper($name = 'azure')
    {
        stream_register_wrapper($name, __NAMESPACE__ . '\\Stream');
        $this->registerAsClient($name);
    }

    /**
     * Unregister this object as stream wrapper
     *
     * @param  string $name Protocol name
     * @return Blob
     */
    public function unregisterStreamWrapper($name = 'azure')
    {
        stream_wrapper_unregister($name);
        $this->unregisterAsClient($name);
    }

    /**
     * Create resource name
     *
     * @param string $containerName  Container name
     * @param string $blobName Blob name
     * @return string
     */
    public static function createResourceName($containerName = '', $blobName = '')
    {
        // Resource name
        $resourceName = $containerName . '/' . $blobName;
        if ($containerName === '' || $containerName === '$root') {
            $resourceName = $blobName;
        }
        if ($blobName === '') {
            $resourceName = $containerName;
        }

        return $resourceName;
    }

    /**
     * Is valid container name?
     *
     * @param string $containerName Container name
     * @return boolean
     */
    public static function isValidContainerName($containerName = '')
    {
        if ($containerName == '$root') {
            return true;
        }

        if (preg_match("/^[a-z0-9][a-z0-9-]*$/", $containerName) === 0) {
            return false;
        }

        if (strpos($containerName, '--') !== false) {
            return false;
        }

        if (strtolower($containerName) != $containerName) {
            return false;
        }

        if (strlen($containerName) < 3 || strlen($containerName) > 63) {
            return false;
        }

        if (substr($containerName, -1) == '-') {
            return false;
        }

        return true;
    }

    public static function assertValidContainerName($containerName)
    {
        if (!self::isValidContainerName($containerName)) {
            throw new BlobException('Container name does not adhere to container naming conventions. See http://msdn.microsoft.com/en-us/library/dd135715.aspx for more information.');
        }
    }

    public static function assertValidRootContainerBlobName($containerName, $blobName)
    {
        if ($containerName === '$root' && strpos($blobName, '/') !== false) {
            throw new BlobException('Blobs stored in the root container can not have a name containing a forward slash (/).');
        }
    }

    /**
     * Get error message from HTTP Response;
     *
     * @param Beberlei\AzureBlobStorage\Http\Response $response Repsonse
     * @param string $alternativeError Alternative error message
     * @return string
     */
    protected function getErrorMessage($response, $alternativeError = 'Unknown error.')
    {
        $xml = $this->parseResponse($response);
        if ($xml && $xml->Message) {
            return "[" . $response->getStatusCode() . "] " . (string)$xml->Message ."\n" . (string)$xml->AuthenticationErrorDetail;
        } else {
            return $alternativeError;
        }
    }

    /**
     * Generate block id
     *
     * @param int $part Block number
     * @return string Windows Azure Blob Storage block number
     */
    protected function generateBlockId($part = 0)
    {
        $returnValue = $part;
        while (strlen($returnValue) < 64) {
            $returnValue = '0' . $returnValue;
        }

        return $returnValue;
    }

    protected function generateMetadataHeaders(array $metadata = array())
    {
        $headers = array();
        foreach ($metadata as $key => $value) {
            if (strpos($value, "\r") !== false || strpos($value, "\n") !== false) {
                throw new BlobException('Metadata cannot contain newline characters.');
            }

            if (!self::isValidMetadataName($key)) {
                throw new BlobException('Metadata name does not adhere to metadata naming conventions. See http://msdn.microsoft.com/en-us/library/aa664670(VS.71).aspx for more information.');
            }

            $headers["x-ms-meta-" . strtolower($key)] = $value;
        }
        return $headers;
    }

    public static function isValidMetadataName($metadataName = '')
    {
        if (preg_match("/^[a-zA-Z0-9_@][a-zA-Z0-9_]*$/", $metadataName) === 0) {
            return false;
        }

        if ($metadataName == '') {
            return false;
        }

        return true;
    }
    /**
     * Get base URL for creating requests
     *
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->host;
    }

    protected function parseResponse($response = null)
    {
        if (is_null($response)) {
            throw new Microsoft_WindowsAzure_Exception('Response should not be null.');
        }

        $xml = @simplexml_load_string($response->getContent());

        if ($xml !== false) {
            // Fetch all namespaces
            $namespaces = array_merge($xml->getNamespaces(true), $xml->getDocNamespaces(true));

            // Register all namespace prefixes
            foreach ($namespaces as $prefix => $ns) {
                if ($prefix != '') {
                    $xml->registerXPathNamespace($prefix, $ns);
                }
            }
        }

        return $xml;
    }

    /**
     * Parse metadata headers
     *
     * @param array $headers HTTP headers containing metadata
     * @return array
     */
    protected function parseMetadataHeaders($headers = array())
    {
        // Validate
        if (!is_array($headers)) {
            return array();
        }

        // Return metadata
        $metadata = array();
        foreach ($headers as $key => $value) {
            if (substr(strtolower($key), 0, 10) == "x-ms-meta-") {
                $metadata[str_replace("x-ms-meta-", '', strtolower($key))] = $value;
            }
        }
        return $metadata;
    }

    /**
     * Parse metadata XML
     *
     * @param SimpleXMLElement $parentElement Element containing the Metadata element.
     * @return array
     */
    protected function parseMetadataElement($element = null)
    {
        // Metadata present?
        if (!is_null($element) && isset($element->Metadata) && !is_null($element->Metadata)) {
            return get_object_vars($element->Metadata);
        }

        return array();
    }
}
