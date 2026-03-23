<?php

namespace Utopia\VCS\Adapter\Git;

use Exception;

class Forgejo extends Gitea
{
    protected string $endpoint = 'http://forgejo:3000/api/v1';

    /**
     * Get Adapter Name
     *
     * @return string
     */
    public function getName(): string
    {
        return 'forgejo';
    }

    /**
     * Create a webhook on a repository
     *
     * @param string $owner Owner of the repository
     * @param string $repositoryName Name of the repository
     * @param string $url Webhook URL to send events to
     * @param string $secret Webhook secret for signature validation
     * @param array<string> $events Events to trigger the webhook
     * @return int Webhook ID
     */
    public function createWebhook(string $owner, string $repositoryName, string $url, string $secret, array $events = ['push', 'pull_request']): int
    {
        $response = $this->call(
            self::METHOD_POST,
            "/repos/{$owner}/{$repositoryName}/hooks",
            ['Authorization' => "token $this->accessToken"],
            [
                'type' => 'forgejo',
                'active' => true,
                'events' => $events,
                'config' => [
                    'url' => $url,
                    'content_type' => 'json',
                    'secret' => $secret,
                ],
            ]
        );

        $responseHeaders = $response['headers'] ?? [];
        $responseHeadersStatusCode = $responseHeaders['status-code'] ?? 0;
        if ($responseHeadersStatusCode >= 400) {
            throw new Exception("Failed to create webhook: HTTP {$responseHeadersStatusCode}");
        }

        return (int) ($response['body']['id'] ?? 0);
    }
}
