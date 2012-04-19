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

/**
 * @copyright  Copyright (c) 2009 - 2012, RealDolmen (http://www.realdolmen.com)
 * @license    http://phpazure.codeplex.com/license
 *
 * @property string  $Container       The name of the blob container in which the blob is stored.
 * @property string  $Name            The name of the blob.
 * @property string  $SnapshotId      The blob snapshot ID if it is a snapshot blob (= a backup copy of a blob).
 * @property string  $Etag            The entity tag, used for versioning and concurrency.
 * @property string  $LastModified    Timestamp when the blob was last modified.
 * @property string  $Url             The full URL where the blob can be downloaded.
 * @property int     $Size            The blob size in bytes.
 * @property string  $ContentType     The blob content type header.
 * @property string  $ContentEncoding The blob content encoding header.
 * @property string  $ContentLanguage The blob content language header.
 * @property string  $CacheControl    The blob cache control header.
 * @property string  $BlobType        The blob type (block blob / page blob).
 * @property string  $LeaseStatus     The blob lease status.
 * @property boolean $IsPrefix        Is it a blob or a directory prefix?
 * @property array   $Metadata        Key/value pairs of meta data
 */
class BlobInstance
{
    private $data;

    /**
     * Constructor
     *
     * @param string  $containerName   Container name
     * @param string  $name            Name
     * @param string  $snapshotId      Snapshot id
     * @param string  $etag            Etag
     * @param string  $lastModified    Last modified date
     * @param string  $url             Url
     * @param int     $size            Size
     * @param string  $contentType     Content Type
     * @param string  $contentEncoding Content Encoding
     * @param string  $contentLanguage Content Language
     * @param string  $cacheControl    Cache control
     * @param string  $blobType        Blob type
     * @param string  $leaseStatus     Lease status
     * @param boolean $isPrefix        Is Prefix?
     * @param array   $metadata        Key/value pairs of meta data
     */
    public function __construct($containerName, $name, $snapshotId, $etag, $lastModified, $url = '', $size = 0, $contentType = '', $contentEncoding = '', $contentLanguage = '', $cacheControl = '', $blobType = '', $leaseStatus = '', $isPrefix = false, $metadata = array()) 
    {
        $this->data = array(
            'container'        => $containerName,
            'name'             => $name,
        	'snapshotid'	   => $snapshotId,
            'etag'             => $etag,
            'lastmodified'     => $lastModified,
            'url'              => $url,
            'size'             => $size,
            'contenttype'      => $contentType,
            'contentencoding'  => $contentEncoding,
            'contentlanguage'  => $contentLanguage,
            'cachecontrol'     => $cacheControl,
            'blobtype'         => $blobType,
            'leasestatus'      => $leaseStatus,
            'isprefix'         => $isPrefix,
            'metadata'         => $metadata
        );
    }

    public function __get($name)
    {
        $name = strtolower($name);
        if (!array_key_exists($name, $this->data)) {
            throw new \InvalidArgumentException("No Attribute $name on BlobInstance");
        }

        return $this->data[$name];
    }
}
