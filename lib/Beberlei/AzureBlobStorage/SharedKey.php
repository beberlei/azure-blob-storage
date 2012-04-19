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
class SharedKey extends CredentialsAbstract
{
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
	    return $requestUrl;
	}

	/**
	 * Sign request headers with credentials
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
		// http://github.com/sriramk/winazurestorage/blob/214010a2f8931bac9c96dfeb337d56fe084ca63b/winazurestorage.py


		// Determine path
		if ($this->_usePathStyleUri) {
			$path = substr($path, strpos($path, '/'));
		}

		// Canonicalized headers
		$canonicalizedHeaders = array();

		// Request date
		$requestDate = '';
		if (isset($headers[CredentialsAbstract::PREFIX_STORAGE_HEADER . 'date'])) {
		    $requestDate = $headers[CredentialsAbstract::PREFIX_STORAGE_HEADER . 'date'];
		} else {
            $time = time();
		    $requestDate = gmdate('D, d M Y H:i:s', $time) . ' GMT'; // RFC 1123
		    $canonicalizedHeaders[] = CredentialsAbstract::PREFIX_STORAGE_HEADER . 'date:' . $requestDate;
		}

		// Build canonicalized headers
		if (!is_null($headers)) {
			foreach ($headers as $header => $value) {
				if (is_bool($value)) {
					$value = $value === true ? 'True' : 'False';
				}

				$headers[$header] = $value;
				if (substr($header, 0, strlen(CredentialsAbstract::PREFIX_STORAGE_HEADER)) == CredentialsAbstract::PREFIX_STORAGE_HEADER) {
				    $canonicalizedHeaders[] = strtolower($header) . ':' . $value;
				}
			}
		}
		sort($canonicalizedHeaders);

		// Build canonicalized resource string
		$canonicalizedResource  = '/' . $this->_accountName;
		if ($this->_usePathStyleUri) {
			$canonicalizedResource .= '/' . $this->_accountName;
		}
		$canonicalizedResource .= $path;
		if (count($query) > 0) {
			ksort($query);

		    foreach ($query as $key => $value) {
		    	$canonicalizedResource .= "\n" . strtolower($key) . ':' . rawurldecode($value);
		    }
		}

		// Content-Length header
		$contentLength = '';
		if (strtoupper($httpVerb) != 'GET'
			 && strtoupper($httpVerb) != 'DELETE'
			 && strtoupper($httpVerb) != 'HEAD') {
			$contentLength = 0;

			if (!is_null($rawData)) {
				$contentLength = strlen($rawData);
			}
		}

		// Create string to sign
		$stringToSign   = array();
		$stringToSign[] = strtoupper($httpVerb); 									// VERB
    	$stringToSign[] = $this->_issetOr($headers, 'Content-Encoding', '');		// Content-Encoding
    	$stringToSign[] = $this->_issetOr($headers, 'Content-Language', '');		// Content-Language
    	$stringToSign[] = $contentLength; 											// Content-Length
    	$stringToSign[] = $this->_issetOr($headers, 'Content-MD5', '');				// Content-MD5
    	$stringToSign[] = $this->_issetOr($headers, 'Content-Type', '');			// Content-Type
    	$stringToSign[] = "";														// Date
    	$stringToSign[] = $this->_issetOr($headers, 'If-Modified-Since', '');		// If-Modified-Since
    	$stringToSign[] = $this->_issetOr($headers, 'If-Match', '');				// If-Match
    	$stringToSign[] = $this->_issetOr($headers, 'If-None-Match', '');			// If-None-Match
    	$stringToSign[] = $this->_issetOr($headers, 'If-Unmodified-Since', '');		// If-Unmodified-Since
    	$stringToSign[] = $this->_issetOr($headers, 'Range', '');					// Range

    	if (!$forTableStorage && count($canonicalizedHeaders) > 0) {
    		$stringToSign[] = implode("\n", $canonicalizedHeaders); // Canonicalized headers
    	}

    	$stringToSign[] = $canonicalizedResource;		 			// Canonicalized resource
    	$stringToSign   = implode("\n", $stringToSign);
    	$signString     = base64_encode(hash_hmac('sha256', $stringToSign, $this->_accountKey, true));

    	// Sign request
    	$headers[CredentialsAbstract::PREFIX_STORAGE_HEADER . 'date'] = $requestDate;
    	$headers['Authorization'] = 'SharedKey ' . $this->_accountName . ':' . $signString;

    	// Return headers
    	return $headers;
	}
}
