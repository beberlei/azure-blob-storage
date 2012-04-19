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
 * @property string  $Container       Container name
 * @property string  $Name            Name
 * @property string  $LeaseId         Lease id
 * @property string  $LeaseTime       Time remaining in the lease period, in seconds. This header is returned only for a successful request to break the lease. It provides an approximation as to when the lease period will expire.
 */
class LeaseInstance
{
    private $data;

    /**
     * Constructor
     *
     * @param string  $containerName   Container name
     * @param string  $name            Name
     * @param string  $leaseId         Lease id
     * @param string  $leaseTime       Time remaining in the lease period, in seconds. This header is returned only for a successful request to break the lease. It provides an approximation as to when the lease period will expire.
     */
    public function __construct($containerName, $name, $leaseId, $leaseTime) 
    {
        $this->data = array(
            'container'        => $containerName,
            'name'             => $name,
        	'leaseid'          => $leaseId,
            'leasetime'        => $leaseTime
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
