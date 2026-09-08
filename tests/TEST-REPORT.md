# Leaderboard 扩展测试报告

- **项目**: ygpynet/leaderboard（依赖 ygpynet/point-system）
- **测试环境**: Windows / PHP 8.3.32 / MySQL 8.4（flarum 库）/ Flarum 2.0.0-rc.8 / Node v22.23.0 / 站点默认语言 zh-Hans
- **测试日期**: 2026-09-08（第 3 轮：P1 加固回归 + 系统性调试轮）
- **负责人**: 开发者（AI 辅助）
- **自动化入口**:
  - 单元（离线，免数据库）: `php vendor/ygpynet/leaderboard/tests/UnitTests.php`（或 `composer test:unit`）
  - E2E（需真实站点 + MySQL）: `php vendor/ygpynet/leaderboard/tests/AutomatedTest.php`（或 `composer test:e2e`）
- **本轮结论**: 单元 24/24、E2E 32/32、静态检查全通过、HTTP 层复验通过。调试轮发现并修复 6 处缺陷（BUG-6~10、DEF-1），修正 2 处测试自身问题（DBG-1/2）。

## 依赖说明

| 依赖 | 用途 |
|---|---|
| ygpynet/point-system ≥1.0 | 积分/签到数据源（缺失时相关榜单降级为空） |
| flarum/likes | 点赞类榜单数据（post_likes 表） |
| fof/best-answer（可选） | 最佳答案榜（列缺失时空榜） |
| fof/reactions / fof/badges / fof/gamification（可选） | 积分写入源（本站未安装，契约经上游源码核对） |
| flarum/tags（可选） | 标签排除功能 |
| Web 服务器 + http://localhost | HTTP 集成层用例 |
| Node + npm | 前端类型检查与生产构建 |

---

## 一、单元测试（tests/UnitTests.php — 离线、免数据库）

| 编号 | 描述 | 步骤 | 预期结果 | 实际结果 | 依赖 | 分类 | 负责人 | 自动化 |
|---|---|---|---|---|---|---|---|---|
| U-01~08 (A-01~08) | 设置门面：读取/默认回退/负值/净化/键名/覆盖数 | 内存 Settings 双写入 | 全部符合 | 8/8 通过 | illuminate | 单元 | 开发者（AI） | 是 |
| U-09 (A-09) | 排除 ID 去重与顺序归一化（BUG-9 回归） | `[5,1,5,2]` 与 `[2,1,5]` | 均得 `[1,2,5]` | 通过 | 无 | 单元/健壮性 | 开发者（AI） | 是 |
| U-10~18 (B-01~09) | 限流器：访客/用户配额、按 IP 隔离、无 IP 兜底、键空间隔离、bypass、缓存宕机 fail-open | 直接调用 assertAllowed | 全部符合 | 9/9 通过 | illuminate/cache | 安全/健壮性 | 开发者（AI） | 是 |
| U-19~21 (C-01~03) | 序列化器 hasMore 守卫（BUG-7 回归） | 边界/精确末页/空页 | 空页不得广播 next | 3/3 通过 | 无 | 健壮性 | 开发者（AI） | 是 |
| U-22~24 (D-01~03) | 分页链在榜单上限处终止且无重复页（BUG-6 回归） | 模拟前端沿 next 链走 1000 条榜 | honorable/默认/小榜均终止无重复 | 3/3 通过 | 无 | 健壮性 | 开发者（AI） | 是 |

**运行结果**: 24/24 通过，退出码 0。

## 二、E2E（tests/AutomatedTest.php — 启动真实站点）

| 编号 | 描述 | 步骤 | 预期结果 | 实际结果 | 分类 | 负责人 | 自动化 |
|---|---|---|---|---|---|---|---|
| E2E-A01~07 | 7 类别合法 JSON:API | 管理员逐一请求 | 200 + score/rank | 7/7 | 功能 | 开发者（AI） | 是 |
| E2E-A08 | 5 周期 200 | 逐一请求 | 200 | 5/5 | 功能 | 开发者（AI） | 是 |
| E2E-A09/10 | 降序 + 同分同名次 | 全量校验 | 竞技排名 1,1,3… | 通过 | 功能 | 开发者（AI） | 是 |
| E2E-A11/12 | podium/contenders 固定窗口无链接 | section 过滤 | ≤3/≤7 且无 links | 通过 | 功能 | 开发者（AI） | 是 |
| E2E-A13 | honorable 分页语义（本轮重定义） | 大榜→含 next 链接；空榜→无 links | 语义正确 | 通过（本站无 >10 条的榜，走空榜分支） | 功能 | 开发者（AI） | 是 |
| E2E-A16 | 单页榜不输出空 links（BUG-7 回归） | posts 全榜 | 无 links 键 | 通过 | 健壮性 | 开发者（AI） | 是 |
| E2E-A14/15 | 统计属性下发 | included 用户 | topStreak 非负 int | 通过 | 功能 | 开发者（AI） | 是 |
| E2E-B01~06 | 权限/注入降级/深分页/限流触发/按 IP 隔离 | 见用例 | 全部符合 | 6/6 | 安全 | 开发者（AI） | 是 |
| E2E-C01~03 | 注册表完整/缓存一致/数据源缺失空榜 | 见用例 | 全部符合 | 3/3 | 健壮性 | 开发者（AI） | 是 |
| E2E-D00~02 | 设置单源装配/自定义类别注册与服务 | 见用例 | 全部符合 | 3/3 | 扩展性 | 开发者（AI） | 是 |

**运行结果**: 32/32 通过，退出码 0。

## 三、调试轮：发现 → 定位 → 根因 → 修复 → 回归

### 缺陷清单

| 编号 | 缺陷 | 发现方式 | 隔离与定位 | 根因 | 修复 | 回归用例 |
|---|---|---|---|---|---|---|
| BUG-6 | 榜单 ≥970 条时 next 链接分页无限重复同一窗口且永不终止 | 代码审读：offset 钳制与 next 递增的交互推演 | 单元级模拟分页链（探针脚本验证 windowFor 各 raw 返回值） | `offset()` 把客户端偏移钳到 950，而 next 按 limit 递增——超过后恒被钳回，查询窗口恒为 960；且序列化器 `hasMore = total > offset+count` 对重复/空窗口恒真 | 偏移上限改为 `BOARD_LIMIT - 1`（999，与榜单硬上限自洽）；`hasMore()` 增加 `count > 0` 守卫 | 单元 D-01~03 模拟链走完 1000 条榜：52 页终止、零重复 |
| BUG-7 | JSON:API 响应出现 `"links": []`（空数组，形状违规） | 第 2 轮 HTTP 实测观察 | curl 抓包 → 序列化器 `paginationLinks()` 返回空数组仍赋值 | 有 `first`/`prev` 条件不满足、`hasMore` 为 false 时返回 `[]` 仍被序列化 | 仅在非空时写入 `links` | E2E-A16、单元 C-03、HTTP 复验 `links_present=no` |
| BUG-8 | topStreak 平分日的"当日第一"归属取决于 DB 行序 | 代码审读：`GROUP BY` 无 `ORDER BY`，获胜循环先见者赢 | 对照文档承诺"平分归小 user_id" | `dailyScoreQuery` 无排序，MySQL 分组输出顺序未定义 | 查询加 `orderBy('user_id')`，使先见者赢与榜单排序约定一致 | 语义审读（DB 依赖，无离线用例） |
| BUG-9 | 相同排除集合产生不同缓存键（缓存碎片） | 代码审读 | `[1,2]` vs `[2,1]` 的 md5 对照 | `ids()` 保留 JSON 存储顺序进入 `Exclusions::hash()` | `LeaderboardSettings::ids()` 排序+去重（单一解析点） | 单元 A-09 |
| BUG-10 | 前端快速切换周期/类别时慢响应覆盖新选择（竞态） | 代码审读：三个 fetch 无代际守卫 | 时序推演（无需浏览器） | `LeaderboardState` 各 fetch 完成后无条件写状态 | 引入 `currentQueryKey` 查询代际：过期响应丢弃、loading 标志仅在当前代际清除 | 前端构建 + 类型检查通过（行为级验证需 Playwright，列入 NOTE-5） |
| DEF-1 | `$vote->value === 1` 严格比较依赖 PDO 返回类型 | 上游源码核对（FoF/gamification `Vote` 无 int cast） | 契约审查：`PostWasVoted` 派发前 value 为 int 字面量（当前安全），但类型无保证 | 上游模型缺 cast，配置差异可致字符串返回 | 监听器内 `(int)` 防御转换 | 代码审读（fof 未装，不可运行时验证） |

### 测试自身问题（DBG）

| 编号 | 问题 | 根因 | 修正 |
|---|---|---|---|
| DBG-1 | 新增分页模拟用例 D-01/02 首跑即"重复页" | 模拟传参用了扁平 `['offset'=>..]`，而 `offset()` 契约是 `params['page']['offset']` 嵌套 → 所有窗口恒 raw=0 | 修正传参形状（探针脚本实证 window 返回值后确认控制器无错） |
| DBG-2 | A-13 首跑失败——旧断言断言的是有缺陷行为 | 本站 points 榜仅 2 条，honorable 空页；修复前序列化器对空页仍广播 next（正是 BUG-7），修复后空页正确无链接 | A-13 重定义：大榜验证 next 链接、空榜验证无 links；另补 A-16 |

### 上游契约核对记录（可选依赖，本站未装）

| 扩展 | 核对点 | 结论 |
|---|---|---|
| fof/reactions | `PostWasReacted::$user` 可空性 | 构造器强类型 `User`，非空——监听器直接访问安全 |
| fof/gamification | 取消投票是否触发事件 | `SaveVotesToDatabase::vote()` 在 value=0 时同样派发 `PostWasVoted`——监听器 revoke-both 结构正确 |
| fof/gamification | `Vote::$value` 类型 | 无 int cast（docblock 声称 int）——已加防御转换（DEF-1） |

## 四、静态检查

| 编号 | 描述 | 命令 | 预期结果 | 实际结果 | 自动化 |
|---|---|---|---|---|---|
| S-01 | PHP 语法全量 | `php -l` × 全部文件 | 0 错误 | 通过 | 是 |
| S-02 | 前端类型检查 | `npx tsc --noEmit` | exit 0 | 通过 | 是 |
| S-03 | 生产构建 | `npm run build` | compiled successfully | 通过（webpack 5.105.4） | 是 |
| S-04 | 产物回灌 | 清缓存后 GET /leaderboard | 200 + payload 完整 | 通过 | 是 |
| S-05 | HTTP 形状复验 | curl /api/leaderboard-entries | 无 `"links":[]` | 通过 | 是 |

## 五、已知限制 / 备注

- NOTE-1: 429 响应体含堆栈——站点 debug 模式行为（生产应 debug=false），非扩展问题
- NOTE-2: 周期榜按天缓存，数据最多延迟 60 秒
- NOTE-3: 榜单上限 1000 名
- NOTE-4: phpstan 本机未安装，由 CI 兜底
- NOTE-5: 浏览器视觉/交互项（奖牌配色、移动端、前端竞态行为级验证）待 Playwright 持续化
- NOTE-6: fof/* 可选依赖的事件监听器在本站不可运行时验证，契约已经上游源码核对（见第三节表格）

## 六、汇总

| 层次 | 用例数 | 通过 | 失败 |
|---|---|---|---|
| 单元（离线） | 24 | 24 | 0 |
| E2E（真实站点） | 32 | 32 | 0 |
| 静态检查 | 5 | 5 | 0 |
| **合计** | **61** | **61** | **0** |
