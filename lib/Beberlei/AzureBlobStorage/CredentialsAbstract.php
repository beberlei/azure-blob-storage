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
abstract class CredentialsAbstract
{
	/**
	 * Development storage account and key
	 */
	const DEVSTORE_ACCOUNT       = "devstoreaccount1";
	const DEVSTORE_KEY           = "Eby8vdM02xNOcqFlqUwJPLlmEtlCDXJ1OUzFT50uSRZ6IFsuFq2UVErCz4I6tq/K1SZFPTOtr/KBHBeksoGMGw==";

	/**
	 * HTTP header prefixes
	 */
	const PREFIX_PROPERTIES      = "x-ms-prop-";
	const PREFIX_METADATA        = "x-ms-meta-";
	const PREFIX_STORAGE_HEADER  = "x-ms-";

	/**
	 * Permissions
	 */
	const PERMISSION_READ        = "r";
	const PERMISSION_WRITE       = "w";
	const PERMISSION_DELETE      = "d";
	const PERMISSION_LIST        = "l";

	/**
	 * Account name for Windows Azure
	 *
	 * @var string
	 */
	protected $_accountName = '';

	/**
	 * Account key for Windows Azure
	 *
	 * @var string
	 */
	protected $_accountKey = '';

	/**
	 * Use path-style URI's
	 *
	 * @var boolean
	 */
	protected $_usePathStyleUri = false;

	/**
	 * Creates a new Microsoft_WindowsAzure_Credentials_CredentialsAbstract instance
	 *
	 * @param string $accountName Account name for Windows Azure
	 * @param string $accountKey Account key for Windows Azure
	 * @param boolean $usePathStyleUri Use path-style URI's
	 */
	public function __construct(
		$accountName = CredentialsAbstract::DEVSTORE_ACCOUNT,
		$accountKey  = CredentialsAbstract::DEVSTORE_KEY,
		$usePathStyleUri = false
	) {
		$this->_accountName = $accountName;
		$this->_accountKey = base64_decode($accountKey);
		$this->_usePathStyleUri = $usePathStyleUri;
	}

	/**
	 * Set account name for Windows Azure
	 *
	 * @param  string $value
	 * @return Microsoft_WindowsAzure_Credentials_CredentialsAbstract
	 */
	public function setAccountName($value = Microsoft_WindowsAzure_Credentials_CredentialsAbstract::DEVSTORE_ACCOUNT)
	{
		$this->_accountName = $value;
		return $this;
	}

	/**
	 * Set account key for Windows Azure
	 *
	 * @param  string $value
	 * @return Microsoft_WindowsAzure_Credentials_CredentialsAbstract
	 */
	public function setAccountkey($value = Microsoft_WindowsAzure_Credentials_CredentialsAbstract::DEVSTORE_KEY)
	{
		$this->_accountKey = base64_decode($value);
		return $this;
	}

	/**
	 * Set use path-style URI's
	 *
	 * @param  boolean $value
	 * @return Microsoft_WindowsAzure_Credentials_CredentialsAbstract
	 */
	public function setUsePathStyleUri($value = false)
	{
		$this->_usePathStyleUri = $value;
		return $this;
	}

	/**
	 * Sign request URL with credentials
	 *
	 * @param string $requestUrl Request URL
	 * @param string $resourceType Resource type
	 * @param string $requiredPermission Required permission
	 * @return string Signed request URL
	 */
	abstract public function signRequestUrl(
		$requestUrl = '',
		$resourceType = Microsoft_WindowsAzure_Storage::RESOURCE_UNKNOWN,
		$requiredPermission = Microsoft_WindowsAzure_Credentials_CredentialsAbstract::PERMISSION_READ
	);

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
	abstract public function signRequestHeaders(
		$httpVerb = Microsoft_Http_Client::GET,
		$path = '/',
		$query = array(),
		$headers = null,
		$forTableStorage = false,
		$resourceType = Microsoft_WindowsAzure_Storage::RESOURCE_UNKNOWN,
		$requiredPermission = Microsoft_WindowsAzure_Credentials_CredentialsAbstract::PERMISSION_READ,
		$rawData = null
	);

	/**
	 * Returns an array value if the key is set, otherwide returns $valueIfNotSet
	 *
	 * @param array $array
	 * @param mixed $key
	 * @param mixed $valueIfNotSet
	 * @return mixed
	 */
	protected function _issetOr($array, $key, $valueIfNotSet)
	{
		return isset($array[$key]) ? $array[$key] : $valueIfNotSet;
	}
}
