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
 */
class Storage
{
    /**
     * Development storage URLS
     */
    const URL_DEV_BLOB      = "http://127.0.0.1:10000";
    const URL_DEV_QUEUE     = "http://127.0.0.1:10001";
    const URL_DEV_TABLE     = "http://127.0.0.1:10002";

    /**
     * Live storage URLS
     */
    const URL_CLOUD_BLOB    = "http://blob.core.windows.net";
    const URL_CLOUD_QUEUE   = "http://queue.core.windows.net";
    const URL_CLOUD_TABLE   = "http://table.core.windows.net";
    const URL_CLOUD_BLOB_HTTPS    = "ssl://blob.core.windows.net";
    const URL_CLOUD_QUEUE_HTTPS   = "ssl://queue.core.windows.net";
    const URL_CLOUD_TABLE_HTTPS   = "ssl://table.core.windows.net";

    /**
     * Resource types
     */
    const RESOURCE_UNKNOWN     = "unknown";
    const RESOURCE_CONTAINER   = "c";
    const RESOURCE_BLOB        = "b";
    const RESOURCE_TABLE       = "t";
    const RESOURCE_ENTITY      = "e";
    const RESOURCE_QUEUE       = "q";

    /**
     * HTTP header prefixes
     */
    const PREFIX_PROPERTIES      = "x-ms-prop-";
    const PREFIX_METADATA        = "x-ms-meta-";
    const PREFIX_STORAGE_HEADER  = "x-ms-";

    /**
     * Protocols
     */
    const PROTOCOL_HTTP  = 'http://';
    const PROTOCOL_HTTPS = 'https://';
    const PROTOCOL_SSL   = 'ssl://';
}

