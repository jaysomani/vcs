<?php

namespace Utopia\Tests\VCS\Adapter;

use Utopia\Cache\Adapter\None;
use Utopia\Cache\Cache;
use Utopia\System\System;
use Utopia\VCS\Adapter\Git;
use Utopia\VCS\Adapter\Git\Forgejo;

class ForgejoTest extends GiteaTest
{
    protected static string $accessToken = '';

    protected static string $owner = '';

    protected function createVCSAdapter(): Git
    {
        return new Forgejo(new Cache(new None()));
    }

    public function setUp(): void
    {
        if (empty(static::$accessToken)) {
            $this->setupForgejo();
        }

        $adapter = new Forgejo(new Cache(new None()));
        $forgejoUrl = System::getEnv('TESTS_FORGEJO_URL', 'http://forgejo:3000') ?? '';

        $adapter->initializeVariables(
            installationId: '',
            privateKey: '',
            appId: '',
            accessToken: static::$accessToken,
            refreshToken: ''
        );
        $adapter->setEndpoint($forgejoUrl);
        if (empty(static::$owner)) {
            $orgName = 'test-org-' . \uniqid();
            static::$owner = $adapter->createOrganization($orgName);
        }

        $this->vcsAdapter = $adapter;
    }

    protected function setupForgejo(): void
    {
        $tokenFile = '/forgejo-data/forgejo/token.txt';

        if (file_exists($tokenFile)) {
            $contents = file_get_contents($tokenFile);
            if ($contents !== false) {
                static::$accessToken = trim($contents);
            }
        }
    }

    public function testWebhookPushEvent(): void
    {
        $repositoryName = 'test-webhook-push-' . \uniqid();
        $secret = 'test-webhook-secret-' . \uniqid();

        $this->vcsAdapter->createRepository(static::$owner, $repositoryName, false);

        try {
            $catcherUrl = System::getEnv('TESTS_GITEA_REQUEST_CATCHER_URL', 'http://request-catcher:5000') ?? '';
            $this->deleteLastWebhookRequest();
            $this->vcsAdapter->createWebhook(static::$owner, $repositoryName, $catcherUrl . '/webhook', $secret);

            // Trigger a real push by creating a file
            $this->vcsAdapter->createFile(
                static::$owner,
                $repositoryName,
                'README.md',
                '# Webhook Test',
                'Initial commit'
            );

            // Wait for push webhook to arrive automatically
            $webhookData = [];
            $this->assertEventually(function () use (&$webhookData) {
                $webhookData = $this->getLastWebhookRequest();
                $this->assertNotEmpty($webhookData, 'No webhook received');
                $this->assertNotEmpty($webhookData['data'] ?? '', 'Webhook payload is empty');
                $this->assertSame('push', $webhookData['headers']['X-Forgejo-Event'] ?? '', 'Expected push event');
            }, 15000, 500);

            $payload = $webhookData['data'];
            $headers = $webhookData['headers'] ?? [];
            $signature = $headers['X-Forgejo-Signature'] ?? '';

            $this->assertNotEmpty($signature, 'Missing X-Forgejo-Signature header');
            $this->assertTrue(
                $this->vcsAdapter->validateWebhookEvent($payload, $signature, $secret),
                'Webhook signature validation failed'
            );

            $event = $this->vcsAdapter->getEvent('push', $payload);
            $this->assertIsArray($event);
            $this->assertSame('main', $event['branch']);
            $this->assertSame($repositoryName, $event['repositoryName']);
            $this->assertSame(static::$owner, $event['owner']);
            $this->assertNotEmpty($event['commitHash']);
        } finally {
            $this->vcsAdapter->deleteRepository(static::$owner, $repositoryName);
        }
    }

    public function testWebhookPullRequestEvent(): void
    {
        $repositoryName = 'test-webhook-pr-' . \uniqid();
        $secret = 'test-webhook-secret-' . \uniqid();

        $this->vcsAdapter->createRepository(static::$owner, $repositoryName, false);

        try {
            // Create all files BEFORE configuring webhook
            // so those push events don't pollute the catcher
            $this->vcsAdapter->createFile(static::$owner, $repositoryName, 'README.md', '# Test');
            $this->vcsAdapter->createBranch(static::$owner, $repositoryName, 'feature-branch', 'main');
            $this->vcsAdapter->createFile(static::$owner, $repositoryName, 'feature.txt', 'content', 'Add feature', 'feature-branch');

            $catcherUrl = System::getEnv('TESTS_GITEA_REQUEST_CATCHER_URL', 'http://request-catcher:5000') ?? '';
            $this->vcsAdapter->createWebhook(static::$owner, $repositoryName, $catcherUrl . '/webhook', $secret);

            // Clear after setup so only PR event will arrive
            $this->deleteLastWebhookRequest();

            // Trigger real PR event
            $this->vcsAdapter->createPullRequest(
                static::$owner,
                $repositoryName,
                'Test Webhook PR',
                'feature-branch',
                'main'
            );

            // Wait for pull_request webhook to arrive automatically
            $webhookData = [];
            $this->assertEventually(function () use (&$webhookData) {
                $webhookData = $this->getLastWebhookRequest();
                $this->assertNotEmpty($webhookData, 'No webhook received');
                $this->assertNotEmpty($webhookData['data'] ?? '', 'Webhook payload is empty');
                $this->assertSame('pull_request', $webhookData['headers']['X-Forgejo-Event'] ?? '', 'Expected pull_request event');
            }, 15000, 500);

            $payload = $webhookData['data'];
            $headers = $webhookData['headers'] ?? [];
            $signature = $headers['X-Forgejo-Signature'] ?? '';

            $this->assertNotEmpty($signature, 'Missing X-Forgejo-Signature header');
            $this->assertTrue(
                $this->vcsAdapter->validateWebhookEvent($payload, $signature, $secret),
                'Webhook signature validation failed'
            );

            $event = $this->vcsAdapter->getEvent('pull_request', $payload);
            $this->assertIsArray($event);
            $this->assertSame('feature-branch', $event['branch']);
            $this->assertSame($repositoryName, $event['repositoryName']);
            $this->assertSame(static::$owner, $event['owner']);
            $this->assertContains($event['action'], ['opened', 'synchronized']);
            $this->assertGreaterThan(0, $event['pullRequestNumber']);
        } finally {
            $this->vcsAdapter->deleteRepository(static::$owner, $repositoryName);
        }
    }
}
