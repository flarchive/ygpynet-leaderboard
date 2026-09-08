<?php

namespace HuseyinFiliz\Leaderboard\Ranking;

use HuseyinFiliz\Leaderboard\Ranking\Category\BestAnswers;
use HuseyinFiliz\Leaderboard\Ranking\Category\CheckinStreak;
use HuseyinFiliz\Leaderboard\Ranking\Category\Discussions;
use HuseyinFiliz\Leaderboard\Ranking\Category\LikesGiven;
use HuseyinFiliz\Leaderboard\Ranking\Category\LikesReceived;
use HuseyinFiliz\Leaderboard\Ranking\Category\Points;
use HuseyinFiliz\Leaderboard\Ranking\Category\Posts;
use HuseyinFiliz\Leaderboard\Ranking\Support\SchemaCache;

/**
 * Registry of leaderboard categories, keyed by their stable API key.
 *
 * Third-party extensions can add their own boards by registering an
 * implementation of {@see CategoryDefinition} in a service provider:
 *
 *     $registry->add(new MyCustomCategory());
 *
 * The controller, frontend icon map and stats pipeline all read from here,
 * so a new category requires no changes to existing code.
 */
class CategoryRegistry
{
    /** @var array<string, CategoryDefinition> */
    protected array $categories = [];

    public function __construct(protected SchemaCache $schema)
    {
        // Built-in boards. Third-party ones are appended via add().
        foreach ([
            new Points($this->schema),
            new LikesReceived(),
            new LikesGiven(),
            new BestAnswers($this->schema),
            new CheckinStreak($this->schema),
            new Posts(),
            new Discussions(),
        ] as $definition) {
            $this->add($definition);
        }
    }

    public function add(CategoryDefinition $definition): void
    {
        $this->categories[$definition->key()] = $definition;
    }

    public function has(string $key): bool
    {
        return isset($this->categories[$key]);
    }

    public function get(string $key): ?CategoryDefinition
    {
        return $this->categories[$key] ?? null;
    }

    /** @return array<string, CategoryDefinition> */
    public function all(): array
    {
        return $this->categories;
    }

    /** @return string[] */
    public function keys(): array
    {
        return array_keys($this->categories);
    }
}
