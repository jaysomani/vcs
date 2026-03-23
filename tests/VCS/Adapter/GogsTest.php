<?php

namespace Utopia\Tests\Adapter;

use Utopia\Cache\Adapter\None;
use Utopia\Cache\Cache;
use Utopia\System\System;
use Utopia\VCS\Adapter\Git;
use Utopia\VCS\Adapter\Git\Gogs;

class GogsTest extends GiteaTest
{
    protected static string $accessToken = '';

    protected static string $owner = '';

    protected string $webhookEventHeader = 'X-Gogs-Event';
    protected string $webhookSignatureHeader = 'X-Gogs-Signature';
    protected string $avatarDomain = 'gravatar.com';

    protected function createVCSAdapter(): Git
    {
        return new Gogs(new Cache(new None()));
    }

    public function setUp(): void
    {
        if (empty(static::$accessToken)) {
            $this->setupGogs();
        }

        $adapter = new Gogs(new Cache(new None()));
        $gogsUrl = System::getEnv('TESTS_GOGS_URL', 'http://gogs:3000') ?? '';

        $adapter->initializeVariables(
            installationId: '',
            privateKey: '',
            appId: '',
            accessToken: static::$accessToken,
            refreshToken: ''
        );
        $adapter->setEndpoint($gogsUrl);
        if (empty(static::$owner)) {
            $orgName = 'test-org-' . \uniqid();
            static::$owner = $adapter->createOrganization($orgName);
        }

        $this->vcsAdapter = $adapter;
    }

    protected function setupGogs(): void
    {
        $tokenFile = '/gogs-data/gogs/token.txt';

        if (file_exists($tokenFile)) {
            $contents = file_get_contents($tokenFile);
            if ($contents !== false) {
                static::$accessToken = trim($contents);
            }
        }
    }
}
