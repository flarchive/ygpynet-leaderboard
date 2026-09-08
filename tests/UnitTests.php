<?php

/*
 * Offline unit tests for huseyinfiliz/leaderboard — no database, no site boot.
 * Run:  php tests/UnitTests.php
 * Exit code 0 = all pass, 1 = at least one failure.
 */

use Flarum\Post\Exception\FloodingException;
use HuseyinFiliz\Leaderboard\Ranking\RateLimiter;
use HuseyinFiliz\Leaderboard\Support\LeaderboardSettings;

require __DIR__.'/../../../autoload.php';

$failures = 0;
$passed = 0;

function check(string $id, string $desc, bool $condition, string $detail = ''): void
{
    global $failures, $passed;
    if ($condition) {
        $passed++;
        echo "PASS  [$id] $desc\n";
    } else {
        $failures++;
        echo "FAIL  [$id] $desc".($detail !== '' ? " — $detail" : '')."\n";
    }
}

// ── In-memory fakes ──────────────────────────────────────────────────────────

/**
 * Real ArrayStore-backed cache repository — only get/put/forget are used by
 * the limiter, so the genuine Illuminate implementation keeps behaviour honest.
 */
class FakeCache extends Illuminate\Cache\Repository
{
    public function __construct(?Illuminate\Contracts\Cache\Store $store = null)
    {
        parent::__construct($store ?? new Illuminate\Cache\ArrayStore());
    }
}

/**
 * Cache backend outage: every read/write throws.
 */
class BrokenStore extends Illuminate\Cache\ArrayStore
{
    public function get($key): mixed
    {
        throw new RuntimeException('cache down');
    }

    public function put($key, $value, $seconds): bool
    {
        throw new RuntimeException('cache down');
    }
}

function fakeSettings(array $data = []): Flarum\Settings\SettingsRepositoryInterface
{
    return new class($data) implements Flarum\Settings\SettingsRepositoryInterface {
        public function __construct(public array $data = []) {}
        public function all(): array { return $this->data; }
        public function get(string $key, mixed $default = null): mixed { return $this->data[$key] ?? $default; }
        public function set(string $key, mixed $value): void { $this->data[$key] = $value; }
        public function delete(string $keyLike): void { unset($this->data[$keyLike]); }
    };
}

// ── A. LeaderboardSettings ───────────────────────────────────────────────────

echo "== A. LeaderboardSettings ==\n";

$settings = new LeaderboardSettings(fakeSettings([
    'huseyinfiliz-leaderboard.points_daily_login' => '5',
    'huseyinfiliz-leaderboard.excluded_groups' => '[1,"2",3.7,0,-4,"abc"]',
    'huseyinfiliz-leaderboard.excluded_tags' => 'not-json',
]));

check('A-01', 'point value read and cast to int', $settings->pointsFor('daily_login') === 5);
check('A-02', 'unset reason falls back to shared default', $settings->pointsFor('reaction_received') === 1);
check('A-03', 'negative default preserved (downvote = -1)', $settings->pointsFor('downvote_received') === -1);
check('A-04', 'unknown reason falls back to 0', $settings->pointsFor('nope') === 0);

$groups = $settings->excludedGroupIds();
check('A-05', 'excluded ids sanitised to positive ints', $groups === [1, 2, 3], 'got '.json_encode($groups));
check('A-06', 'corrupt JSON degrades to empty array', $settings->excludedTagIds() === []);

check('A-07', 'pointKey builds namespaced key', LeaderboardSettings::pointKey('daily_login') === 'huseyinfiliz-leaderboard.points_daily_login');
check('A-08', 'POINT_DEFAULTS covers all wired reasons', count(LeaderboardSettings::POINT_DEFAULTS) === 7);

// A-09: order/duplicate-insensitive ids → stable exclusion hash input.
$lsA = new LeaderboardSettings(fakeSettings(['huseyinfiliz-leaderboard.excluded_groups' => '[5,1,5,2]']));
$lsB = new LeaderboardSettings(fakeSettings(['huseyinfiliz-leaderboard.excluded_groups' => '[2,1,5]']));
check('A-09', 'excluded ids deduped and order-normalised', $lsA->excludedGroupIds() === [1, 2, 5] && $lsA->excludedGroupIds() === $lsB->excludedGroupIds(),
    'got '.json_encode($lsA->excludedGroupIds()).' vs '.json_encode($lsB->excludedGroupIds()));

// ── B. RateLimiter (per-identity buckets) ────────────────────────────────────

echo "\n== B. RateLimiter ==\n";

function limiter(FakeCache $cache, int $maxUser = 3, int $maxGuest = 2): RateLimiter
{
    return new RateLimiter($cache, $maxUser, $maxGuest);
}

function guestActor(): Flarum\User\User
{
    return new Flarum\User\Guest();
}

$cache = new FakeCache();
$rl = limiter($cache);

// B-01..03: guest bucket keyed by IP, trips at its own limit.
try {
    $rl->assertAllowed(guestActor(), '203.0.113.7');
    $rl->assertAllowed(guestActor(), '203.0.113.7');
    check('B-01', 'guest allowed up to guest limit', true);
} catch (FloodingException) {
    check('B-01', 'guest allowed up to guest limit', false, 'tripped too early');
}
try {
    $rl->assertAllowed(guestActor(), '203.0.113.7');
    check('B-02', 'guest trips beyond guest limit', false, 'no exception');
} catch (FloodingException) {
    check('B-02', 'guest trips beyond guest limit', true);
}

// B-03: a different IP is unaffected (no shared guest bucket).
try {
    $rl->assertAllowed(guestActor(), '198.51.100.9');
    check('B-03', 'guest bucket is per-IP', true);
} catch (FloodingException) {
    check('B-03', 'guest bucket is per-IP', false, 'other IP was throttled');
}

// B-04: missing IP falls into the 'unknown' bucket instead of crashing.
try {
    $rl->assertAllowed(guestActor(), null);
    check('B-04', 'guest without IP handled', true);
} catch (FloodingException) {
    check('B-04', 'guest without IP handled', true); // throttled by 'unknown' bucket is still safe
} catch (Throwable $e) {
    check('B-04', 'guest without IP handled', false, get_class($e));
}

// B-05..07: authenticated user has an independent, larger budget.
$user = new Flarum\User\User();
$user->id = 42;
try {
    $rl->assertAllowed($user);
    $rl->assertAllowed($user);
    $rl->assertAllowed($user);
    check('B-05', 'user allowed up to user limit', true);
} catch (FloodingException) {
    check('B-05', 'user allowed up to user limit', false, 'tripped too early');
}
try {
    $rl->assertAllowed($user);
    check('B-06', 'user trips beyond user limit', false, 'no exception');
} catch (FloodingException) {
    check('B-06', 'user trips beyond user limit', true);
}

// B-07: user id 42 does not collide with guest bucket keys.
$store = $cache->getStore();
$ref = new ReflectionProperty($store, 'storage');
$ref->setAccessible(true);
$keys = array_keys($ref->getValue($store));
check('B-07', 'user and ip scopes use distinct key spaces',
    count(array_filter($keys, fn ($k) => str_contains($k, 'lb_rate_user_42'))) > 0
    && count(array_filter($keys, fn ($k) => str_contains($k, 'lb_rate_ip_'))) > 0,
    json_encode($keys));

// B-08: bypass marker skips limiting entirely.
$cache2 = new FakeCache();
$cache2->put('lb_rate_bypass', true, 60);
$rl2 = limiter($cache2, 1, 1);
$ok = true;
try {
    for ($i = 0; $i < 10; $i++) { $rl2->assertAllowed(guestActor(), '192.0.2.1'); }
} catch (FloodingException) {
    $ok = false;
}
check('B-08', 'bypass marker skips limiting', $ok);

// B-09: cache outage fails open.
$rl3 = limiter(new FakeCache(new BrokenStore()), 1, 1);
$ok = true;
try {
    for ($i = 0; $i < 10; $i++) { $rl3->assertAllowed(guestActor(), '192.0.2.2'); }
} catch (Throwable $e) {
    $ok = false;
}
check('B-09', 'cache outage fails open', $ok);

// ── C. Serializer pagination guards ─────────────────────────────────────────

echo "\n== C. Serializer pagination guards ==\n";

$hasMore = [HuseyinFiliz\Leaderboard\Api\LeaderboardSerializer::class, 'hasMore'];

check('C-01', 'hasMore true when entries remain', $hasMore(100, 0, 20) === true);
check('C-02', 'hasMore false at exact end', $hasMore(100, 80, 20) === false);
check('C-03', 'hasMore false on empty page (infinite-loop guard)', $hasMore(100000, 999, 0) === false, 'empty page must not advertise next');

// ── D. Pagination chain termination at board cap ────────────────────────────

echo "\n== D. Pagination chain termination ==\n";

// Exposes the pure window math of the controller without DI.
$probe = new class extends HuseyinFiliz\Leaderboard\Api\Controller\ListLeaderboardController {
    public function __construct() {}
    public function windowFor(string $section, array $params): array
    {
        return $this->window($section, $params);
    }
};

/**
 * Walks the next-link chain the frontend would follow and asserts it cannot
 * loop: every non-empty page must be unique and the chain must end.
 * Params use the controller's real shape: page[offset]/page[limit].
 */
function simulateChain(object $probe, string $section, int $total, callable $hasMore): array
{
    $seen = [];
    $raw = 0;
    $pages = 0;
    $duplicate = false;
    $terminated = false;

    while (++$pages <= 200) {
        [$query, $link, $limit, $withLinks] = $probe->windowFor($section, ['page' => ['offset' => $raw, 'limit' => 20]]);
        $rows = max(0, min($limit, $total - $query));

        if ($rows > 0 && isset($seen[$query.'|'.$rows])) {
            $duplicate = true;
            break;
        }
        if ($rows > 0) {
            $seen[$query.'|'.$rows] = true;
        }

        if (!$hasMore($total, $link, $rows)) {
            $terminated = true;
            break;
        }

        $raw = $link + $limit;
    }

    return [$terminated, $duplicate, $pages];
}

$board = HuseyinFiliz\Leaderboard\Ranking\RankingRepository::BOARD_LIMIT;

[$terminated, $duplicate, $pages] = simulateChain($probe, 'honorable', $board, $hasMore);
check('D-01', "honorable chain terminates at board cap ($board) without duplicate pages", $terminated && !$duplicate, "terminated=".(int) $terminated." duplicate=".(int) $duplicate." pages=$pages");

[$terminated, $duplicate, $pages] = simulateChain($probe, '', $board, $hasMore);
check('D-02', 'default chain terminates at board cap without duplicate pages', $terminated && !$duplicate, "terminated=".(int) $terminated." duplicate=".(int) $duplicate." pages=$pages");

[$terminated, $duplicate, $pages] = simulateChain($probe, 'honorable', 3, $hasMore);
check('D-03', 'tiny board terminates immediately', $terminated && !$duplicate, "pages=$pages");

// ── Summary ──────────────────────────────────────────────────────────────────

echo "\n$passed/".($passed + $failures)." passed";
if ($failures > 0) {
    echo " — FAILURES: $failures";
}
echo "\n";
exit($failures > 0 ? 1 : 0);
