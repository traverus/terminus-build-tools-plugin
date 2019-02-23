<?php
namespace Pantheon\TerminusBuildTools\Utility;

use Pantheon\TerminusBuildTools\ServiceProviders\RepositoryProviders\GitProvider;

/**
 * MultiDevRetention compares the provided list of multidevs to
 * the list of closed pull requests.
 *
 * A multidev is eligible for deletion if there is a closed
 * pull request that matches its name.  All other multidevs
 * will be retained.
 */
class MultiDevRetention
{
    /** @var GitProvider */
    protected $provider;

    /** @var string[]] */
    protected $retain;

    /** @var string[]] */
    protected $eligible;

    /** @var string[]] */
    protected $examining;

    /** @var string */
    protected $pattern;

    /** @var string */
    protected $project;

    public function __construct(GitProvider $provider, $multidevs, $pattern, $project)
    {
        $this->provider = $provider;
        $this->examining = $multidevs;
        $this->retain = [];
        $this->eligible = [];
        $this->pattern = $pattern;
        $this->project = $project;
    }

    /**
     * gitProvider returns a reference to the provider.
     */
    public function gitProvider()
    {
        return $this->provider;
    }

    /**
     * project returns the cached project.
     */
    public function project()
    {
        return $this->project;
    }

    /**
     * isEligible returns whether a given name is eligible for deletion
     */
    public function isEligible($name)
    {
        return isset($this->eligible[$name]);
    }

    /**
     * eligible returns the list of multidevs that are eligible for deletion
     */
    public function eligible()
    {
        return $this->eligible;
    }

    /**
     * retain returns the list of multidevs that will be retained
     */
    public function retain()
    {
        return array_merge($this->retain, $this->examining);
    }

    /**
     * Use the git provider to examine all of the pull requests for the
     * project. Make a multidev eligible for removal if there is a closed
     * PR for it.
     */
    public function eligibleIfClosedPRExists()
    {
        return $this->provider->branchesForPullRequests($this->project, 'all', $this);
    }

    /**
     * Take the oldest multidevs, and make them eligible for deletion.
     * Retain the rest.
     */
    public function eligibleIfOldest($keep)
    {
        $this->retain = array_merge(
            $this->retain,
            array_slice($this->examining, count($this->examining) - $keep)
        );
        $this->eligible = array_merge(
            $this->eligible,
            array_slice($this->examining, 0, count($this->examining) - $keep)
        );

        $this->examining = [];
    }

    /**
     * If this object is used like a function (callback for branchesForPullRequests),
     * determine if we've already seen all of our branches
     */
    public function __invoke($resultData)
    {
        foreach ($resultData as $item) {
            $this->process($item);
        }

        return !empty($this->examining);
    }

    protected function process($item)
    {
        $number = $item['number'];
        $name = $this->pattern . $number;
        unset($this->examining[$name]);

        if ($item['state'] == 'closed') {
            return $this->processClosed($name);
        }
        return $this->processOpen($name);
    }

    protected function processClosed($name)
    {
        if (isset($this->examining[$name])) {
            unset($this->examining[$name]);
            $this->eligible[$name] = $name;
        }
    }

    protected function processOpen($name)
    {
        if (isset($this->examining[$name])) {
            unset($this->examining[$name]);
            $this->retain[$name] = $name;
        }

    }
}