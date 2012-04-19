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
 * @property int  $start   Page range start
 * @property int  $end     Page range end
 */
class PageRegionInstance
{
    private $data;

    /**
     * Constructor
     *
     * @param int  $start   Page range start
     * @param int  $end     Page range end
     */
    public function __construct($start = 0, $end = 0)
    {
        $this->data = array(
            'start'        => $start,
            'end'             => $end
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
