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
 * @property string $Id           Id for the signed identifier
 * @property string $Start        The time at which the Shared Access Signature becomes valid.
 * @property string $Expiry       The time at which the Shared Access Signature becomes invalid.
 * @property string $Permissions  Signed permissions - read (r), write (w), delete (d) and list (l)
 */
class SignedIdentifier
{
    private $data;

    /**
     * Constructor
     *
     * @param string $id           Id for the signed identifier
     * @param string $start        The time at which the Shared Access Signature becomes valid.
     * @param string $expiry       The time at which the Shared Access Signature becomes invalid.
     * @param string $permissions  Signed permissions - read (r), write (w), delete (d) and list (l)
     */
    public function __construct($id = '', $start = '', $expiry = '', $permissions = '')
    {
        $this->data = array(
            'id'           => $id,
            'start'        => $start,
            'expiry'       => $expiry,
            'permissions'  => $permissions
        );
    }

    public function __get($name)
    {
        $name = strtolower($name);
        if (!isset($this->data[$name])) {
            throw new \InvalidArgumentException("No Attribute $name on BlobInstance");
        }

        return $this->data[$name];
    }
}
