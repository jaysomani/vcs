<?php

namespace Utopia\VCS\Adapter\Git;

class Gogs extends Gitea
{
    protected string $endpoint = 'http://gogs:3000/api/v1';

    /**
     * Get Adapter Name
     *
     * @return string
     */
    public function getName(): string
    {
        return 'gogs';
    }

    protected function getHookType(): string
    {
        return 'gogs';
    }

    /**
     * Get commit statuses
     *
     * Overrides the Gitea implementation to normalise the 'state' field
     * returned by Gogs into the 'status' field used by the rest of the
     * adapter interface (Gitea changed the JSON key from 'state' to
     * 'status', but Gogs still uses 'state').
     *
     * @param string $owner Owner of the repository
     * @param string $repositoryName Name of the repository
     * @param string $commitHash SHA of the commit
     * @return array<mixed> List of commit statuses
     */
    public function getCommitStatuses(string $owner, string $repositoryName, string $commitHash): array
    {
        $statuses = parent::getCommitStatuses($owner, $repositoryName, $commitHash);

        return array_map(function ($status) {
            if (isset($status['state']) && !isset($status['status'])) {
                $status['status'] = $status['state'];
            }
            return $status;
        }, $statuses);
    }
}
