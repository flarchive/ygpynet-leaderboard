![Leaderboard](https://cdn.discuss.flarum.org/2026-03-02/1772440655-531360-leaderboard.png)

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md) [![Latest Stable Version](https://img.shields.io/packagist/v/ygpynet/leaderboard.svg)](https://packagist.org/packages/ygpynet/leaderboard) [![Total Downloads](https://img.shields.io/packagist/dt/ygpynet/leaderboard.svg)](https://packagist.org/packages/ygpynet/leaderboard)

# Leaderboard

A points-based leaderboard extension for [Flarum](https://flarum.org) forums, powered by [`ygpynet/point-system`](https://github.com/ygpynet/point-system). All points live in point-system's ledger — this extension ranks users from it and adds a few extra point sources.

### 🏆 Podium & Rankings

![Podium Demo](https://cdn.discuss.flarum.org/2026-03-02/1772440801-974877-image.png)

### 📊 Top Contenders & Honorable Mentions

![Contenders Demo](https://cdn.discuss.flarum.org/2026-03-02/1772440801-974877-image.png)

### ⚙️ Admin Settings

![Admin Demo](https://cdn.discuss.flarum.org/2026-03-02/1772440929-15437-image.png)

## Features

- 🥇 **Multiple Rankings**: Separate boards for points, likes received, likes given, best answers, check-in streak, replies, and discussions started
- 🏆 **Podium Display**: Top 3 users shown in a gold/silver/bronze podium with avatars and stats
- 🔥 **Top Contenders**: Ranks #4-#10 displayed in a responsive card grid
- 📋 **Honorable Mentions**: Compact two-column list for remaining users with infinite scroll
- ⏰ **Period Filters**: Daily, weekly, monthly, quarterly, yearly, and all-time rankings for every category
- ⭐ **Single Source of Truth**: Rankings come straight from point-system's ledger and the underlying Flarum tables
- ➕ **Extra Point Sources**: Bridges reactions, best answers, badges, votes, and daily login into point-system
- 🏷️ **Tag Exclusions**: Exclude discussions with specific tags from rankings and earning points
- 👥 **Group Exclusions**: Hide users in selected groups from the leaderboard
- 🦴 **Skeleton Loading**: Smooth loading experience with animated placeholders
- 📱 **Responsive Design**: Optimized layout for mobile, tablet, and desktop

## Installation

```bash
composer require ygpynet/leaderboard:"*"
```

Requires [`ygpynet/point-system`](https://github.com/ygpynet/point-system) — composer installs it automatically.

## Updating

```bash
composer update ygpynet/leaderboard
php flarum migrate
php flarum cache:clear
```

To remove simply run `composer remove ygpynet/leaderboard`.

## Quick Start

### For Users

1. Navigate to the **Leaderboard** page from the sidebar
2. Use **period pills** to filter rankings (Daily, Weekly, Monthly, etc.)
3. Click on any user card to visit their profile

### For Admins

Navigate to **Admin → Leaderboard** to configure the extension. The admin panel is organized into three tabs:

#### General Tab

- **Leaderboard Name**: Customize the page title displayed in the sidebar and header

The points label shown next to each score reuses the currency name configured in point-system's settings.

#### Points Tab

Configure point values for the leaderboard's extra point sources:

| Section | Activities | Default |
|---------|-----------|---------|
| **Core** | Daily login | 1 |
| **Reactions** | Reaction received, Reaction given | 1, 0 |
| **Best Answer** | Best answer selected | 2 |
| **Badges** | Badge earned | 3 |
| **Gamification** | Upvote received, Downvote received | 1, -1 |

Core earning (discussions, posts, likes, check-in) is configured on point-system's own settings page.

> **Tip**: Set a point value to `0` to disable that source. Negative values (e.g., downvotes) deduct points.

#### Exclusions Tab

- **Excluded Groups**: Select user groups to hide from the leaderboard (e.g., Admins, Bots)
- **Excluded Tags**: Select tags whose discussions won't earn points (requires `flarum/tags`)

## How Points Flow

All point movements are recorded in point-system's transaction ledger:

```
Activity (post, like, check-in…)  → point-system awards points natively
Reaction / best answer / badge / vote / daily login → this extension bridges them into point-system
```

## Ranking Categories

Switch categories with the pills at the top of the leaderboard page:

| Category | All-time metric | Period metric |
|----------|----------------|---------------|
| Points | point-system lifetime total | Net credited points in period |
| Likes Received | Self-likes excluded | Likes received in period |
| Likes Given | Self-likes excluded | Likes given in period |
| Best Answers | Best-answer posts (requires `fof/best-answer`) | Set within period |
| Check-in Streak | Current check-in streak (point-system) | Check-in days within period |
| Replies | Comment posts (excl. first post, hidden) | Posted within period |
| Discussions | Discussions started | Started within period |

#### Period Filtering

- **All Time** ranks by each category's cumulative metric
- **Period rankings** (daily/weekly/monthly/quarterly/yearly) only count activity since the period start

## Optional Integrations

The leaderboard automatically integrates with these extensions when they are enabled:

| Extension | Point Sources |
|-----------|--------------|
| [`flarum/tags`](https://github.com/flarum/tags) | Tag-based exclusions |
| [`fof/reactions`](https://github.com/FriendsOfFlarum/reactions) | Reaction received, Reaction given |
| [`fof/best-answer`](https://github.com/FriendsOfFlarum/best-answer) | Best answer selected |
| [`fof/badges`](https://github.com/FriendsOfFlarum/badges) | Badge earned |
| [`fof/gamification`](https://github.com/FriendsOfFlarum/gamification) | Upvote received, Downvote received |

No configuration is needed — install the extension and points will be awarded automatically based on your point settings.

## 🌍 Translations

This extension comes with English translations. Community translations are welcome!

Translate: [Leaderboard at Weblate](https://weblate.rob006.net/projects/flarum/ygpynet-leaderboard/)

## Links

- [Discuss](https://discuss.flarum.org/d/38834-leaderboard-points-based-ranking-system)
- [Packagist](https://packagist.org/packages/ygpynet/leaderboard)
- [GitHub](https://github.com/ygpynet/leaderboard)
- [Issues](https://github.com/ygpynet/leaderboard/issues)

## License

MIT License - see [LICENSE.md](LICENSE.md)

---

Developed with ❤️ by [Hüseyin Filiz](https://github.com/huseyinfiliz)
