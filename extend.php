<?php

/*
 * This file is part of huseyinfiliz/leaderboard.
 *
 * Copyright (c) 2026 Hüseyin Filiz.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace HuseyinFiliz\Leaderboard;

use Flarum\Api\Context;
use Flarum\Api\Resource;
use Flarum\Api\Schema;
use Flarum\Extend;

    // Defaults are registered from LeaderboardSettings::POINT_DEFAULTS so the
    // wiring file and the service layer cannot drift apart.
    $settings = (new Extend\Settings())
        ->default(Support\LeaderboardSettings::NAME_KEY, Support\LeaderboardSettings::DEFAULT_NAME)
        ->serializeToForum(
            Support\LeaderboardSettings::NAME_KEY,
            Support\LeaderboardSettings::NAME_KEY
        );

    foreach (Support\LeaderboardSettings::POINT_DEFAULTS as $reason => $points) {
        $settings = $settings->default(Support\LeaderboardSettings::pointKey($reason), $points);
    }

    return [
        (new Extend\ServiceProvider())
            ->register(Provider\LeaderboardServiceProvider::class),

        (new Extend\Frontend('forum'))
            ->js(__DIR__.'/js/dist/forum.js')
            ->css(__DIR__.'/less/forum.less')
            ->route('/leaderboard', 'huseyinfiliz-leaderboard.index'),

        (new Extend\Frontend('admin'))
            ->js(__DIR__.'/js/dist/admin.js')
            ->css(__DIR__.'/less/admin.less'),

        new Extend\Locales(__DIR__.'/locale'),

        (new Extend\Routes('api'))
            ->get('/leaderboard-entries', 'huseyinfiliz-leaderboard.api.index', Api\Controller\ListLeaderboardController::class),

        // [F3] View Leaderboard permission — exposed to forum so the frontend can
        // hide the nav item / show a permission-denied message without an extra request.
        (new Extend\ApiResource(Resource\ForumResource::class))
            ->fields(fn () => [
                Schema\Boolean::make('canViewLeaderboard')
                    ->get(fn ($forum, Context $context) =>
                        $context->getActor()->hasPermission('huseyinfiliz-leaderboard.viewLeaderboard')
                    ),
            ]),

        $settings,

        // Leaderboard-owned point sources. Core earning (discussions, posts,
        // likes) is handled natively by ygpynet/point-system and is NOT wired
        // here — wiring it would double-credit. Daily login is registered by
        // point-system's reason table but not implemented there, so it is wired
        // here instead. Each block requires point-system (the ledger) plus the
        // extension that emits the source event.
        (new Extend\Conditional())
            ->whenExtensionEnabled('ygpynet-point-system', fn () => [
                (new Extend\Event())
                    ->listen(\Flarum\User\Event\LoggedIn::class, Listener\DailyLoginListener::class),

                (new Extend\Conditional())
                    ->whenExtensionEnabled('fof-reactions', fn () => [
                        (new Extend\Event())
                            ->listen(\FoF\Reactions\Event\PostWasReacted::class, Listener\PostReactedListener::class)
                            ->listen(\FoF\Reactions\Event\PostWasUnreacted::class, Listener\PostUnreactedListener::class),
                    ])
                    ->whenExtensionEnabled('fof-best-answer', fn () => [
                        (new Extend\Event())
                            ->listen(\FoF\BestAnswer\Events\BestAnswerSet::class, Listener\BestAnswerSetListener::class)
                            ->listen(\FoF\BestAnswer\Events\BestAnswerUnset::class, Listener\BestAnswerUnsetListener::class),
                    ])
                    ->whenExtensionEnabled('fof-badges', fn () => [
                        (new Extend\Event())
                            ->listen(\FoF\Badges\Event\BadgeAwarded::class, Listener\BadgeAwardedListener::class),
                    ])
                    ->whenExtensionEnabled('fof-gamification', fn () => [
                        (new Extend\Event())
                            ->listen(\FoF\Gamification\Events\PostWasVoted::class, Listener\PostVotedListener::class),
                    ]),
            ]),
    ];
