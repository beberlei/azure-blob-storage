<?php
/**
 * WindowsAzure BlobStorage Client
 *
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
 * Storage Registry for registering Blob Storage Accounts
 */
class StorageRegistry
{
    /**
     * @var array
     */
    private $accounts;

    /**
     * Register Account
     *
     * @param string $accountName
     * @param string $accountKey
     * @param string $streamName
     * @return void
     */
    public function registerAccount($accountName, $accountKey, $streamName = false)
    {
        if ( isset($this->accounts[$accountName])) {
            throw new \RuntimeException("An account with name $accountName is already registered.");
        }

        $this->accounts[$accountName] = new BlobClient(
            sprintf('https://%s.blob.storage.windows.net', $accountName),
            $accountName,
            $accountKey
        );

        if (! $streamName) {
            return;
        }

        $this->accounts[$accountName]->registerStreamWrapper($streamName);
    }

    /**
     * Get the BlobClient associated with the account name.
     *
     * @param string $name
     * @return BlobClient
     */
    public function get($name)
    {
        if ( ! isset($this->accounts[$name])) {
            throw new \RuntimeException("No account found with " . $name);
        }

        return $this->accounts[$name];
    }
}

