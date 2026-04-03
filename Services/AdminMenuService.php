<?php

namespace App\Core\AdminOps\Services;

use App\Core\Auth\Models\Admin;
use App\Core\RBAC\Support\AdminPermissionSnapshot;
use App\Core\System\Services\Module\ModuleRegistry;
use Illuminate\Support\Facades\Route as RouteFacade;

class AdminMenuService
{
    /** Ayarlar menüsü altında panel kullanıcıları (yönetici hesapları / roller) */
    private const SETTINGS_SUBGROUP_PANEL_USERS = 'settings_panel_users';

    /** Ayarlar menüsü altında IP / erişim kısıtları (modül `security` katkıları bu gruba eklenir) */
    private const SETTINGS_SUBGROUP_SECURITY = 'settings_security';

    /** E-posta yapılandırması (SMTP + şablonlar) */
    private const SETTINGS_SUBGROUP_MAIL = 'settings_mail';

    /**
     * Request scope boyunca aynı menüyü tekrar üretmemek için basit memoization.
     *
     * @var array<string, list<array<string,mixed>>>
     */
    private array $filteredMenuCache = [];

    /**
     * @var list<array<string,mixed>>|null
     */
    private ?array $baseMenuWithContributions = null;

    /**
     * @return list<array{group:string,title:string,icon:string,url:string,active:bool,children:list<array{title:string,url:string,active:bool}>}>
     */
    public function getLayout3SidebarRail(?Admin $admin = null): array
    {
        $menu = $this->getFilteredMenu($admin);
        $currentRoute = RouteFacade::currentRouteName() ?? '';
        $rail = [];

        foreach ($menu as $item) {
            if (! is_array($item)) {
                continue;
            }

            $children = $this->mergeChildren($item);
            $url = $this->resolveItemUrl($item);
            if ($url === '#') {
                $url = $this->firstRoutableUrlInTree($children) ?? '#';
            }
            if ($url === '#' && $children === []) {
                continue;
            }

            $rail[] = [
                'group' => (string) ($item['group'] ?? 'misc'),
                'title' => $this->translate((string) ($item['title'] ?? '')),
                'icon' => (string) ($item['sidebar_icon'] ?? $item['icon'] ?? 'ki-filled ki-element-11'),
                'url' => $url,
                'active' => $currentRoute !== '' && $this->subtreeMatchesRoute($item, $currentRoute),
                'children' => $this->collectLeafLinks($children, $currentRoute),
            ];
        }

        return $rail;
    }

    /**
     * @return list<array{group:string,title:string,icon:string,url:string,active:bool,children:list<array<string,mixed>>}>
     */
    public function getLayout3NavbarMenu(?Admin $admin = null): array
    {
        $menu = $this->getFilteredMenu($admin);
        $currentRoute = RouteFacade::currentRouteName() ?? '';
        $result = [];

        foreach ($menu as $item) {
            if (! is_array($item)) {
                continue;
            }

            $children = $this->mergeChildren($item);
            $url = $this->resolveItemUrl($item);
            if ($url === '#') {
                $url = $this->firstRoutableUrlInTree($children) ?? '#';
            }
            $navbarChildren = $this->buildNavbarTree($children, $currentRoute);
            if ($url === '#' && $navbarChildren === []) {
                continue;
            }

            $result[] = [
                'group' => (string) ($item['group'] ?? 'misc'),
                'title' => $this->translate((string) ($item['title'] ?? '')),
                'icon' => (string) ($item['sidebar_icon'] ?? $item['icon'] ?? 'ki-filled ki-element-11'),
                'url' => $url,
                'active' => $currentRoute !== '' && $this->subtreeMatchesRoute($item, $currentRoute),
                'children' => $navbarChildren,
            ];
        }

        return $result;
    }

    /**
     * Çekirdek panel: yalnızca pano + ayarlar. Diğer üst bölümler modül `config/menu.php`
     * içinde `placement: root` veya `group_children` ile eklenir.
     *
     * @return list<array<string,mixed>>
     */
    private function coreBaseMenu(): array
    {
        return [
            [
                'title' => 'admin.menu.dashboard.title',
                'group' => 'dashboard',
                'sort' => 10,
                'sidebar_icon' => 'ki-filled ki-chart-line-star',
                'route' => 'cms.admin.dashboard',
                'permission' => 'dashboard_read',
                'active_routes' => ['cms.admin.dashboard'],
            ],
            [
                'title' => 'admin.menu.settings.title',
                'group' => 'settings',
                'sort' => 10000,
                'sidebar_icon' => 'ki-filled ki-setting-2',
                'children' => [
                    [
                        'id' => self::SETTINGS_SUBGROUP_PANEL_USERS,
                        'title' => 'admin.menu.settings.panel_users.title',
                        'children' => [
                            [
                                'title' => 'admin.menu.user_management.users',
                                'route' => 'cms.admin.admins.index',
                                'permission' => 'admins_read',
                                'active_routes' => ['cms.admin.admins.index', 'cms.admin.admins.data', 'cms.admin.admins.add', 'cms.admin.admins.edit', 'cms.admin.admins.del', 'cms.admin.admins.restore', 'cms.admin.admins.force_del', 'cms.admin.admins.activityLogs'],
                            ],
                            [
                                'title' => 'admin.menu.user_management.roles',
                                'route' => 'cms.admin.admins.roles',
                                'permission' => 'roles_read',
                                'active_routes' => ['cms.admin.admins.roles', 'cms.admin.admins.role.*'],
                            ],
                        ],
                    ],
                    [
                        'title' => 'admin.menu.security.cache_manager.title',
                        'route' => 'cms.admin.cache-manager.status',
                        'permission' => 'cache_read',
                        'active_routes' => ['cms.admin.cache-manager.*'],
                    ],
                    [
                        'title' => 'admin.menu.security.database_operations.title',
                        'route' => 'cms.admin.db-operations.index',
                        'permission' => 'database_read',
                        'active_routes' => ['cms.admin.db-operations.*'],
                    ],
                    [
                        'id' => self::SETTINGS_SUBGROUP_SECURITY,
                        'title' => 'admin.menu.settings.security_section.title',
                        'children' => [
                            [
                                'title' => 'admin.menu.security.ip_management.title',
                                'route' => 'cms.admin.ip-whitelist.index',
                                'permission' => 'ip_whitelist_read',
                                'active_routes' => ['cms.admin.ip-whitelist.*', 'cms.admin.ip-blacklist.*'],
                            ],
                        ],
                    ],
                    [
                        'title' => 'admin.menu.settings.integrations_management.title',
                        'route' => 'cms.admin.integrations.index',
                        'permission' => 'general_settings_read',
                        'active_routes' => ['cms.admin.integrations.*'],
                    ],
                    [
                        'title' => 'admin.menu.settings.lang_management.title',
                        'route' => 'cms.admin.language.index',
                        'permission' => 'languages_read',
                        'active_routes' => ['cms.admin.language.*'],
                    ],
                    [
                        'id' => self::SETTINGS_SUBGROUP_MAIL,
                        'title' => 'admin.menu.settings.mail_config.title',
                        'children' => [
                            [
                                'title' => 'admin.menu.settings.mail_config.smtp',
                                'route' => 'cms.admin.smtp',
                                'permission' => 'mail_read',
                                'active_routes' => ['cms.admin.smtp', 'cms.admin.smtp.*'],
                            ],
                            [
                                'title' => 'admin.menu.settings.mail_config.templates',
                                'route' => 'cms.admin.mail.templates',
                                'permission' => 'mail_template_read',
                                'active_routes' => ['cms.admin.mail.templates*'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function getFilteredMenu(?Admin $admin = null): array
    {
        $cacheKey = $admin
            ? 'admin:'.$admin->id.':'.($admin->role_id ?? 'none')
            : 'guest';

        if (array_key_exists($cacheKey, $this->filteredMenuCache)) {
            return $this->filteredMenuCache[$cacheKey];
        }

        $menu = $this->baseMenuWithContributions
            ??= $this->applyModuleMenuContributions($this->coreBaseMenu());

        if (! $admin) {
            return $this->filteredMenuCache[$cacheKey] = $menu;
        }

        return $this->filteredMenuCache[$cacheKey] = $this->filterByPermission($menu, $admin);
    }

    /**
     * @param  list<array<string,mixed>>  $baseMenu
     * @return list<array<string,mixed>>
     */
    private function applyModuleMenuContributions(array $baseMenu): array
    {
        try {
            /** @var ModuleRegistry $registry */
            $registry = app(ModuleRegistry::class);
            $contributions = $registry->activeMenuContributions();
        } catch (\Throwable) {
            return $this->sortTopLevelMenu($baseMenu);
        }

        $rootContributions = [];
        $groupContributions = [];
        foreach ($contributions as $contribution) {
            $p = (string) ($contribution['placement'] ?? '');
            if ($p === 'root') {
                $rootContributions[] = $contribution;
            } elseif ($p === 'group_children') {
                $groupContributions[] = $contribution;
            }
        }

        if (count($baseMenu) < 2) {
            $menu = $baseMenu;
        } else {
            $dashboard = $baseMenu[0];
            $settings = $baseMenu[1];
            $mergedRoots = $this->mergeRootMenuNodes($rootContributions);
            $menu = array_merge([$dashboard], $mergedRoots, [$settings]);
        }

        foreach ($groupContributions as $contribution) {
            $group = (string) ($contribution['group'] ?? '');
            $item = $contribution['item'] ?? null;
            if ($group === '' || ! is_array($item)) {
                continue;
            }

            $targetGroup = $group;
            $subgroupId = null;
            if ($group === 'security') {
                $targetGroup = 'settings';
                $subgroupId = self::SETTINGS_SUBGROUP_SECURITY;
            }

            $matched = false;
            foreach ($menu as &$menuGroup) {
                if (($menuGroup['group'] ?? '') !== $targetGroup) {
                    continue;
                }

                if ($subgroupId !== null) {
                    if ($this->appendItemToSettingsSubgroup($menuGroup, $subgroupId, $item)) {
                        $matched = true;
                    }
                    break;
                }

                if (! isset($menuGroup['children']) || ! is_array($menuGroup['children'])) {
                    $menuGroup['children'] = [];
                }

                $route = $item['route'] ?? null;
                $alreadyExists = false;
                if (is_string($route) && $route !== '') {
                    foreach ($menuGroup['children'] as $child) {
                        if (($child['route'] ?? null) === $route) {
                            $alreadyExists = true;
                            break;
                        }
                    }
                }

                if (! $alreadyExists) {
                    $menuGroup['children'][] = $item;
                }

                $matched = true;
                break;
            }
            unset($menuGroup);

            if (! $matched) {
                $groupMeta = $this->defaultGroupMeta($group);
                $menu[] = [
                    'title' => $groupMeta['title'],
                    'group' => $group,
                    'sidebar_icon' => $groupMeta['sidebar_icon'],
                    'sort' => $groupMeta['sort'],
                    'children' => [$item],
                ];
            }
        }

        return $this->sortTopLevelMenu($menu);
    }

    /**
     * Üst menü satırlarını `sort` alanına göre sıralar (düşük önce).
     *
     * @param  list<array<string,mixed>>  $menu
     * @return list<array<string,mixed>>
     */
    private function sortTopLevelMenu(array $menu): array
    {
        usort($menu, function (array $a, array $b): int {
            return ($a['sort'] ?? 500) <=> ($b['sort'] ?? 500);
        });

        return array_values($menu);
    }

    /**
     * `placement: root` ile gelen tam ağaçları `group` anahtarına göre birleştirir (aynı rail grubuna çoklu modül).
     *
     * @param  list<array<string, mixed>>  $rootContributions
     * @return list<array<string,mixed>>
     */
    private function mergeRootMenuNodes(array $rootContributions): array
    {
        $byGroup = [];

        foreach ($rootContributions as $contribution) {
            $items = $contribution['items'] ?? null;
            if (! is_array($items)) {
                continue;
            }

            $moduleKey = (string) ($contribution['module_key'] ?? 'misc');
            $entryPriority = (int) ($contribution['priority'] ?? 500);

            foreach ($items as $node) {
                if (! is_array($node)) {
                    continue;
                }

                $group = (string) ($node['group'] ?? '');
                if ($group === '') {
                    $group = 'module_'.$this->slugifyMenuGroup($moduleKey);
                }

                $sort = (int) ($node['sort'] ?? $entryPriority);

                if (! isset($byGroup[$group])) {
                    $node['group'] = $group;
                    $node['sort'] = $sort;
                    $byGroup[$group] = $node;

                    continue;
                }

                $existing = &$byGroup[$group];
                $existing['sort'] = min((int) ($existing['sort'] ?? 500), $sort);

                $newChildren = $node['children'] ?? [];
                $oldChildren = $existing['children'] ?? [];
                if (is_array($newChildren) && $newChildren !== []) {
                    $existing['children'] = $this->mergeMenuChildrenDedupe(
                        is_array($oldChildren) ? $oldChildren : [],
                        $newChildren
                    );
                }

                unset($existing);
            }
        }

        return array_values($byGroup);
    }

    private function slugifyMenuGroup(string $key): string
    {
        $s = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $key) ?? $key);

        return trim($s, '_') !== '' ? trim($s, '_') : 'misc';
    }

    /**
     * @param  list<array<string,mixed>>  $existing
     * @param  list<array<string,mixed>>  $incoming
     * @return list<array<string,mixed>>
     */
    private function mergeMenuChildrenDedupe(array $existing, array $incoming): array
    {
        $seen = [];
        foreach ($existing as $item) {
            if (! is_array($item)) {
                continue;
            }
            $id = $this->menuItemDedupeIdentity($item);
            if ($id !== '') {
                $seen[$id] = true;
            }
        }

        $out = $existing;
        foreach ($incoming as $item) {
            if (! is_array($item)) {
                continue;
            }
            $id = $this->menuItemDedupeIdentity($item);
            if ($id !== '' && isset($seen[$id])) {
                continue;
            }
            if ($id !== '') {
                $seen[$id] = true;
            }
            $out[] = $item;
        }

        return $out;
    }

    /**
     * Aynı seviyede yinelenen yaprakları azaltmak için (route veya id).
     */
    private function menuItemDedupeIdentity(array $item): string
    {
        $route = $item['route'] ?? null;
        if (is_string($route) && $route !== '' && $route !== '#') {
            return 'route:'.$route;
        }

        $id = $item['id'] ?? null;
        if (is_string($id) && $id !== '') {
            return 'id:'.$id;
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $settingsMenu
     */
    private function appendItemToSettingsSubgroup(array &$settingsMenu, string $subgroupId, array $item): bool
    {
        if (! isset($settingsMenu['children']) || ! is_array($settingsMenu['children'])) {
            return false;
        }

        $route = $item['route'] ?? null;

        foreach ($settingsMenu['children'] as &$section) {
            if (! is_array($section) || ($section['id'] ?? '') !== $subgroupId) {
                continue;
            }

            if (! isset($section['children']) || ! is_array($section['children'])) {
                $section['children'] = [];
            }

            if (is_string($route) && $route !== '') {
                foreach ($section['children'] as $existing) {
                    if (is_array($existing) && ($existing['route'] ?? null) === $route) {
                        return true;
                    }
                }
            }

            $section['children'][] = $item;

            return true;
        }
        unset($section);

        return false;
    }

    /**
     * @return array{title:string,sidebar_icon:string,sort:int}
     */
    private function defaultGroupMeta(string $group): array
    {
        return match ($group) {
            'dashboard' => ['title' => 'admin.menu.dashboard.title', 'sidebar_icon' => 'ki-filled ki-chart-line-star', 'sort' => 10],
            'site_content' => ['title' => 'admin.menu.site_content.title', 'sidebar_icon' => 'ki-filled ki-document', 'sort' => 100],
            'billing_hub' => ['title' => 'admin.menu.billing_hub.title', 'sidebar_icon' => 'ki-filled ki-cheque', 'sort' => 200],
            'settings' => ['title' => 'admin.menu.settings.title', 'sidebar_icon' => 'ki-filled ki-setting-2', 'sort' => 10000],
            'users_operations' => ['title' => 'admin.menu.users_operations.title', 'sidebar_icon' => 'ki-filled ki-users', 'sort' => 300],
            'security' => ['title' => 'admin.menu.security.title', 'sidebar_icon' => 'ki-filled ki-security-user', 'sort' => 450],
            'seo_management' => ['title' => 'admin.menu.seo_management.title', 'sidebar_icon' => 'ki-filled ki-search-list', 'sort' => 400],
            default => ['title' => $group, 'sidebar_icon' => 'ki-filled ki-element-11', 'sort' => 650],
        };
    }

    private function filterByPermission(array $items, Admin $admin): array
    {
        return collect($items)->map(function ($item) use ($admin) {
            if (! is_array($item)) {
                return null;
            }

            if (isset($item['permission']) && ! $this->hasPermission($admin, (string) $item['permission'])) {
                return null;
            }

            if (isset($item['children']) && is_array($item['children'])) {
                $item['children'] = $this->filterByPermission($item['children'], $admin);
            }

            if (isset($item['submenu']['children']) && is_array($item['submenu']['children'])) {
                $item['submenu']['children'] = $this->filterByPermission($item['submenu']['children'], $admin);
            }

            $hasRoute = isset($item['route']) && is_string($item['route']) && $item['route'] !== '' && $item['route'] !== '#';
            $hasChildren = ! empty($item['children']) || ! empty($item['submenu']['children']);
            if (! $hasRoute && ! $hasChildren && ! $admin->isRoot()) {
                return null;
            }

            return $item;
        })->filter()->values()->toArray();
    }

    private function hasPermission(Admin $admin, string $permission): bool
    {
        if ($admin->isRoot() || $permission === '') {
            return true;
        }

        $request = request();
        $hasRequest = $request instanceof \Illuminate\Http\Request;
        $permissionSet = [];
        if ($hasRequest) {
            $permissionSet = AdminPermissionSnapshot::permissionsForRequest($request, $admin);
        }

        if (str_contains($permission, '|')) {
            foreach (explode('|', $permission) as $single) {
                $normalized = strtolower(trim($single));
                if ($hasRequest) {
                    if (in_array($normalized, $permissionSet, true)) {
                        return true;
                    }
                    continue;
                }
                if ($admin->hasPermission($normalized)) {
                    return true;
                }
            }

            return false;
        }

        if ($hasRequest) {
            return in_array(strtolower($permission), $permissionSet, true);
        }

        return $admin->hasPermission($permission);
    }

    private function mergeChildren(array $item): array
    {
        $children = [];
        if (! empty($item['children']) && is_array($item['children'])) {
            $children = array_merge($children, $item['children']);
        }

        if (! empty($item['submenu']['children']) && is_array($item['submenu']['children'])) {
            $children = array_merge($children, $item['submenu']['children']);
        }

        return $children;
    }

    private function resolveItemUrl(array $item): string
    {
        $route = $item['route'] ?? null;
        if (! is_string($route) || $route === '' || $route === '#') {
            return '#';
        }

        return $this->safeRoute($route, $item['route_params'] ?? null);
    }

    private function safeRoute(string $routeName, ?array $params = null): string
    {
        if (! RouteFacade::has($routeName)) {
            return '#';
        }

        try {
            return route($routeName, $params ?? []);
        } catch (\Throwable) {
            return '#';
        }
    }

    private function translate(string $key): string
    {
        if ($key === '') {
            return '';
        }

        return __($key) !== $key ? __($key) : $key;
    }

    private function subtreeMatchesRoute(array $item, string $currentRoute): bool
    {
        if ($this->matchesCurrentRoute($item, $currentRoute)) {
            return true;
        }

        foreach ($item['children'] ?? [] as $child) {
            if (is_array($child) && $this->subtreeMatchesRoute($child, $currentRoute)) {
                return true;
            }
        }

        foreach ($item['submenu']['children'] ?? [] as $child) {
            if (is_array($child) && $this->subtreeMatchesRoute($child, $currentRoute)) {
                return true;
            }
        }

        return false;
    }

    private function matchesCurrentRoute(array $item, string $currentRoute): bool
    {
        $route = $item['route'] ?? null;
        if (is_string($route) && $route !== '' && $route !== '#' && $currentRoute === $route) {
            return true;
        }

        if (! empty($item['active_routes']) && is_array($item['active_routes'])) {
            foreach ($item['active_routes'] as $pattern) {
                if (is_string($pattern) && request()->routeIs($pattern)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function firstRoutableUrlInTree(array $nodes): ?string
    {
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $url = $this->resolveItemUrl($node);
            if ($url !== '#') {
                return $url;
            }

            $childUrl = $this->firstRoutableUrlInTree($this->mergeChildren($node));
            if ($childUrl !== null) {
                return $childUrl;
            }
        }

        return null;
    }

    private function collectLeafLinks(array $nodes, string $currentRoute): array
    {
        $links = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $url = $this->resolveItemUrl($node);
            $children = $this->mergeChildren($node);

            if ($url !== '#') {
                $links[] = [
                    'title' => $this->translate((string) ($node['title'] ?? '')),
                    'url' => $url,
                    'active' => $currentRoute !== '' && $this->matchesCurrentRoute($node, $currentRoute),
                ];
                continue;
            }

            if ($children !== []) {
                $links = array_merge($links, $this->collectLeafLinks($children, $currentRoute));
            }
        }

        return $links;
    }

    private function buildNavbarTree(array $nodes, string $currentRoute): array
    {
        $tree = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $children = $this->mergeChildren($node);
            $nested = $children !== [] ? $this->buildNavbarTree($children, $currentRoute) : [];
            $url = $this->resolveItemUrl($node);
            if ($url === '#') {
                $url = $this->firstRoutableUrlInTree($children) ?? '#';
            }

            if ($url === '#' && $nested === []) {
                continue;
            }

            $tree[] = [
                'title' => $this->translate((string) ($node['title'] ?? '')),
                'url' => $url,
                'active' => $currentRoute !== '' && $this->subtreeMatchesRoute($node, $currentRoute),
                'children' => $nested,
            ];
        }

        return $tree;
    }
}
