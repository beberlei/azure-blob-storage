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
class SharedAccessSignature extends CredentialsAbstract
{
    /**
     * Permission set
     *
     * @var array
     */
    protected $permissionSet = array();

	/**
	 * Creates a new Credentials_SharedAccessSignature instance
	 *
	 * @param string $accountName Account name for Windows Azure
	 * @param string $accountKey Account key for Windows Azure
	 * @param boolean $usePathStyleUri Use path-style URI's
	 * @param array $permissionSet Permission set
	 */
	public function __construct(
		$accountName = CredentialsAbstract::DEVSTORE_ACCOUNT,
		$accountKey  = CredentialsAbstract::DEVSTORE_KEY,
		$usePathStyleUri = false, $permissionSet = array()
	) {
	    parent::__construct($accountName, $accountKey, $usePathStyleUri);
	    $this->permissionSet = $permissionSet;
	}

	/**
	 * Get permission set
	 *
	 * @return array
	 */
    public function getPermissionSet()
	{
	    return $this->permissionSet;
	}

	/**
	 * Set permisison set
	 *
	 * Warning: fine-grained permissions should be added prior to coarse-grained permissions.
	 * For example: first add blob permissions, end with container-wide permissions.
	 *
	 * Warning: the signed access signature URL must match the account name of the
	 * Credentials_Credentials_SharedAccessSignature instance
	 *
	 * @param  array $value Permission set
	 * @return void
	 */
    public function setPermissionSet($value = array())
	{
		foreach ($value as $url) {
			if (strpos($url, $this->accountName) === false) {
				throw new Exception('The permission set can only contain URLs for the account name specified in the Credentials_SharedAccessSignature instance.');
			}
		}
	    $this->permissionSet = $value;
	}

    /**
     * Create signature
     *
     * @param string $path 		   Path for the request
     * @param string $resource     Signed resource - container (c) - blob (b)
     * @param string $permissions  Signed permissions - read (r), write (w), delete (d) and list (l)
     * @param string $start        The time at which the Shared Access Signature becomes valid.
     * @param string $expiry       The time at which the Shared Access Signature becomes invalid.
     * @param string $identifier   Signed identifier
     * @return string
     */
    public function createSignature(
    	$path = '/',
    	$resource = 'b',
    	$permissions = 'r',
    	$start = '',
    	$expiry = '',
    	$identifier = ''
    ) {
		// Determine path
		if ($this->usePathStyleUri) {
			$path = substr($path, strpos($path, '/'));
		}

		// Add trailing slash to $path
		if (substr($path, 0, 1) !== '/') {
		    $path = '/' . $path;
		}

		// Build canonicalized resource string
		$canonicalizedResource  = '/' . $this->accountName;
		/*if ($this->usePathStyleUri) {
			$canonicalizedResource .= '/' . $this->accountName;
		}*/
		$canonicalizedResource .= $path;

		// Create string to sign
		$stringToSign   = array();
		$stringToSign[] = $permissions;
    	$stringToSign[] = $start;
    	$stringToSign[] = $expiry;
    	$stringToSign[] = $canonicalizedResource;
    	$stringToSign[] = $identifier;

    	$stringToSign = implode("\n", $stringToSign);
    	$signature    = base64_encode(hash_hmac('sha256', $stringToSign, $this->accountKey, true));

    	return $signature;
    }

    /**
     * Create signed query string
     *
     * @param string $path 		   Path for the request
     * @param string $queryString  Query string for the request
     * @param string $resource     Signed resource - container (c) - blob (b)
     * @param string $permissions  Signed permissions - read (r), write (w), delete (d) and list (l)
     * @param string $start        The time at which the Shared Access Signature becomes valid.
     * @param string $expiry       The time at which the Shared Access Signature becomes invalid.
     * @param string $identifier   Signed identifier
     * @return string
     */
    public function createSignedQueryString(
    	$path = '/',
    	$queryString = '',
    	$resource = 'b',
    	$permissions = 'r',
    	$start = '',
    	$expiry = '',
    	$identifier = ''
    ) {
        // Parts
        $parts = array();
        if ($start !== '') {
            $parts[] = 'st=' . urlencode($start);
        }
        $parts[] = 'se=' . urlencode($expiry);
        $parts[] = 'sr=' . $resource;
        $parts[] = 'sp=' . $permissions;
        if ($identifier !== '') {
            $parts[] = 'si=' . urlencode($identifier);
        }
        $parts[] = 'sig=' . urlencode($this->createSignature($path, $resource, $permissions, $start, $expiry, $identifier));

        // Assemble parts and query string
        if ($queryString != '') {
            $queryString .= '&';
	    }
        $queryString .= implode('&', $parts);

        return $queryString;
    }

    /**
	 * Permission matches request?
	 *
	 * @param string $permissionUrl Permission URL
	 * @param string $requestUrl Request URL
	 * @param string $resourceType Resource type
	 * @param string $requiredPermission Required permission
	 * @return string Signed request URL
	 */
    public function permissionMatchesRequest(
    	$permissionUrl = '',
    	$requestUrl = '',
    	$resourceType = Storage::RESOURCE_UNKNOWN,
    	$requiredPermission = CredentialsAbstract::PERMISSION_READ
    ) {
        // Build requirements
        $requiredResourceType = $resourceType;
        if ($requiredResourceType == Storage::RESOURCE_BLOB) {
            $requiredResourceType .= Storage::RESOURCE_CONTAINER;
        }

        // Parse permission url
	    $parsedPermissionUrl = parse_url($permissionUrl);

	    // Parse permission properties
	    $permissionParts = explode('&', $parsedPermissionUrl['query']);

	    // Parse request url
	    $parsedRequestUrl = parse_url($requestUrl);

	    // Check if permission matches request
	    $matches = true;
	    foreach ($permissionParts as $part) {
	        list($property, $value) = explode('=', $part, 2);

	        if ($property == 'sr') {
	            $matches = $matches && (strpbrk($value, $requiredResourceType) !== false);
	        }

	    	if ($property == 'sp') {
	            $matches = $matches && (strpbrk($value, $requiredPermission) !== false);
	        }
	    }

	    // Ok, but... does the resource match?
	    $matches = $matches && (strpos($parsedRequestUrl['path'], $parsedPermissionUrl['path']) !== false);

        // Return
	    return $matches;
    }

    /**
	 * Sign request URL with credentials
	 *
	 * @param string $requestUrl Request URL
	 * @param string $resourceType Resource type
	 * @param string $requiredPermission Required permission
	 * @return string Signed request URL
	 */
	public function signRequestUrl(
		$requestUrl = '',
		$resourceType = Storage::RESOURCE_UNKNOWN,
		$requiredPermission = CredentialsAbstract::PERMISSION_READ
	) {
	    // Look for a matching permission
	    foreach ($this->getPermissionSet() as $permittedUrl) {
	        if ($this->permissionMatchesRequest($permittedUrl, $requestUrl, $resourceType, $requiredPermission)) {
	            // This matches, append signature data
	            $parsedPermittedUrl = parse_url($permittedUrl);

	            if (strpos($requestUrl, '?') === false) {
	                $requestUrl .= '?';
	            } else {
	                $requestUrl .= '&';
	            }

	            $requestUrl .= $parsedPermittedUrl['query'];

	            // Return url
	            return $requestUrl;
	        }
	    }

	    // Return url, will be unsigned...
	    return $requestUrl;
	}

	/**
	 * Sign request with credentials
	 *
	 * @param string $httpVerb HTTP verb the request will use
	 * @param string $path Path for the request
	 * @param array $query Query arguments for the request (key/value pairs)
	 * @param array $headers x-ms headers to add
	 * @param boolean $forTableStorage Is the request for table storage?
	 * @param string $resourceType Resource type
	 * @param string $requiredPermission Required permission
	 * @param mixed  $rawData Raw post data
	 * @return array Array of headers
	 */
	public function signRequestHeaders(
		$httpVerb = 'GET',
		$path = '/',
		$query = array(),
		$headers = null,
		$forTableStorage = false,
		$resourceType = Storage::RESOURCE_UNKNOWN,
		$requiredPermission = CredentialsAbstract::PERMISSION_READ,
		$rawData = null
	) {
	    return $headers;
	}
}
