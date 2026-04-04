<?php

namespace App\Core\AdminOps\Services;

use App\Core\Auth\Models\Admin;
use App\Core\RBAC\Support\AdminPermissionSnapshot;
use App\Core\System\Services\Module\ModuleRegistry;
use Illuminate\Http\Request;
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
     * Çekirdek menü satırları yok: `dashboard`, `settings` ve alt öğeleri ilgili modüllerin `config/menu.php` dosyaları sağlar.
     *
     * @return list<array<string,mixed>>
     */
    private function coreBaseMenu(): array
    {
        return [];
    }

    /**
     * @param  array{shell: array<string, mixed>, entries: array<string, array<string, mixed>>}  $settingsTreePayload
     * @return array<string, mixed>
     */
    private function buildSettingsRailFromTreePayload(array $settingsTreePayload): array
    {
        $shell = $settingsTreePayload['shell'] ?? [];

        return [
            'title' => (string) ($shell['title'] ?? 'admin.menu.settings.title'),
            'group' => 'settings',
            'sidebar_icon' => (string) ($shell['sidebar_icon'] ?? 'ki-filled ki-setting-2'),
            'sort' => (int) ($shell['sort'] ?? 10000),
            'children' => [],
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

        $treeContributions = [];
        $rootContributions = [];
        $groupContributions = [];
        foreach ($contributions as $contribution) {
            $p = (string) ($contribution['placement'] ?? '');
            if ($p === 'tree_group') {
                $treeContributions[] = $contribution;
            } elseif ($p === 'root') {
                $rootContributions[] = $contribution;
            } elseif ($p === 'group_children') {
                $groupContributions[] = $contribution;
            }
        }

        $mergedTreeByGroup = $this->mergeTreeGroupContributionsAcrossModules($treeContributions);

        $settingsTreePayload = null;
        if (isset($mergedTreeByGroup['settings'])) {
            $settingsTreePayload = $mergedTreeByGroup['settings'];
            unset($mergedTreeByGroup['settings']);
        }

        $treeRootItems = $this->treeMergedGroupsToRootItems($mergedTreeByGroup);
        $syntheticTreeRoot = $treeRootItems !== []
            ? [['placement' => 'root', 'items' => $treeRootItems, 'priority' => 500, 'module_key' => '_tree']]
            : [];

        $shellHints = $this->collectGroupShellHints(array_merge($syntheticTreeRoot, $rootContributions), $groupContributions);

        $mergedRoots = $this->mergeRootMenuNodes(array_merge($syntheticTreeRoot, $rootContributions));

        $menu = $mergedRoots;

        if ($settingsTreePayload !== null) {
            $menu[] = $this->buildSettingsRailFromTreePayload($settingsTreePayload);
            $this->mergeTreeGroupIntoExistingRail($menu, 'settings', $settingsTreePayload);
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
                $shell = $this->resolveGroupShellForNewTopLevel($group, $shellHints, $contribution);
                $menu[] = [
                    'title' => $shell['title'],
                    'group' => $group,
                    'sidebar_icon' => $shell['sidebar_icon'],
                    'sort' => $shell['sort'],
                    'children' => [$item],
                ];
            }
        }

        $this->sortSettingsGroupChildren($menu);

        return $this->sortTopLevelMenu($menu);
    }

    /**
     * @param  list<array<string, mixed>>  $menu
     */
    private function sortSettingsGroupChildren(array &$menu): void
    {
        foreach ($menu as &$top) {
            if (($top['group'] ?? '') !== 'settings' || ! isset($top['children']) || ! is_array($top['children'])) {
                continue;
            }

            usort($top['children'], function (array $a, array $b): int {
                return ($a['sort'] ?? 500) <=> ($b['sort'] ?? 500);
            });
        }
        unset($top);
    }

    /**
     * Associatif `config/menu.php` ağaçlarından gelen tree_group katkılarını modüller arası birleştirir (aynı grup + aynı giriş slug'ı).
     *
     * @param  list<array<string, mixed>>  $treeContributions
     * @return array<string, array{shell: array<string, mixed>, entries: array<string, array<string, mixed>>}>
     */
    private function mergeTreeGroupContributionsAcrossModules(array $treeContributions): array
    {
        $byGroup = [];

        foreach ($treeContributions as $c) {
            $g = (string) ($c['group'] ?? '');
            if ($g === '' || ! is_array($c['entries'] ?? null)) {
                continue;
            }

            $shellIn = is_array($c['group_shell'] ?? null) ? $c['group_shell'] : [];

            if (! isset($byGroup[$g])) {
                $byGroup[$g] = [
                    'shell' => [],
                    'entries' => [],
                ];
            }

            foreach (['title', 'sidebar_icon', 'sort', 'icon', 'route', 'permission', 'active_routes', 'route_params'] as $sk) {
                if (! isset($byGroup[$g]['shell'][$sk]) && array_key_exists($sk, $shellIn)) {
                    $byGroup[$g]['shell'][$sk] = $shellIn[$sk];
                }
            }

            foreach ($c['entries'] as $slug => $node) {
                if (! is_string($slug) || ! is_array($node)) {
                    continue;
                }
                if (! isset($byGroup[$g]['entries'][$slug])) {
                    $byGroup[$g]['entries'][$slug] = $node;
                } else {
                    $byGroup[$g]['entries'][$slug] = $this->mergeTreeEntryNodes(
                        $byGroup[$g]['entries'][$slug],
                        $node
                    );
                }
            }
        }

        return $byGroup;
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     * @return array<string, mixed>
     */
    private function mergeTreeEntryNodes(array $a, array $b): array
    {
        $out = $a;
        foreach (['title', 'route', 'permission', 'route_params'] as $k) {
            if ((! isset($out[$k]) || $out[$k] === null || $out[$k] === '') && isset($b[$k])) {
                $out[$k] = $b[$k];
            }
        }
        if (isset($b['active_routes']) && is_array($b['active_routes'])) {
            $out['active_routes'] = isset($out['active_routes']) && is_array($out['active_routes'])
                ? array_values(array_unique(array_merge($out['active_routes'], $b['active_routes'])))
                : $b['active_routes'];
        }
        if (isset($out['children'], $b['children']) && is_array($out['children']) && is_array($b['children'])) {
            $out['children'] = $this->mergeMenuChildrenDedupe($out['children'], $b['children']);
        } elseif (isset($b['children']) && is_array($b['children']) && (! isset($out['children']) || ! is_array($out['children']) || $out['children'] === [])) {
            $out['children'] = $b['children'];
        }

        return $out;
    }

    /**
     * @param  array<string, array{shell: array<string, mixed>, entries: array<string, array<string, mixed>>}>  $merged
     * @return list<array<string, mixed>>
     */
    private function treeMergedGroupsToRootItems(array $merged): array
    {
        $items = [];
        foreach ($merged as $groupSlug => $data) {
            $shell = $data['shell'] ?? [];
            $entries = $data['entries'] ?? [];
            $children = [];
            foreach ($entries as $slug => $node) {
                if (! is_array($node)) {
                    continue;
                }
                $n = $node;
                $n['id'] = (string) ($n['id'] ?? $slug);
                $children[] = $n;
            }
            $row = [
                'group' => (string) $groupSlug,
                'title' => (string) ($shell['title'] ?? $groupSlug),
                'sidebar_icon' => (string) ($shell['sidebar_icon'] ?? $shell['icon'] ?? 'ki-filled ki-element-11'),
                'sort' => isset($shell['sort']) ? (int) $shell['sort'] : 500,
                'children' => $children,
            ];
            foreach (['route', 'permission', 'active_routes', 'route_params'] as $leaf) {
                if (array_key_exists($leaf, $shell)) {
                    $row[$leaf] = $shell[$leaf];
                }
            }
            $items[] = $row;
        }

        return $items;
    }

    /**
     * Associatif ağaçta `settings` kökü, çift rail üretmeden çekirdek Ayarlar satırının `children` listesine eklenir.
     *
     * @param  array<string, array{shell: array<string, mixed>, entries: array<string, array<string, mixed>>}>  $mergedGroupData
     */
    private function mergeTreeGroupIntoExistingRail(array &$menu, string $railGroup, array $mergedGroupData): void
    {
        if ($railGroup !== 'settings') {
            return;
        }
        $entries = $mergedGroupData['entries'] ?? [];
        if ($entries === []) {
            return;
        }
        foreach ($menu as &$group) {
            if (($group['group'] ?? '') !== $railGroup) {
                continue;
            }
            if (! isset($group['children']) || ! is_array($group['children'])) {
                $group['children'] = [];
            }
            foreach ($entries as $slug => $node) {
                if (! is_array($node)) {
                    continue;
                }
                $n = $node;
                $dedupeId = (string) ($n['id'] ?? $slug);
                $route = $n['route'] ?? null;
                $duplicate = false;
                foreach ($group['children'] as $existing) {
                    if (! is_array($existing)) {
                        continue;
                    }
                    if (is_string($route) && $route !== '' && ($existing['route'] ?? null) === $route) {
                        $duplicate = true;
                        break;
                    }
                    if ((string) ($existing['id'] ?? '') === $dedupeId) {
                        $duplicate = true;
                        break;
                    }
                }
                if ($duplicate) {
                    continue;
                }
                $n['id'] = $dedupeId;
                $group['children'][] = $n;
            }
            break;
        }
        unset($group);
    }

    /**
     * Root menü düğümleri ve `group_children` + `group_meta` satırlarından üst grup kabuğu ipuçları (ilk tanım kazanır).
     *
     * @param  list<array<string, mixed>>  $rootContributions
     * @param  list<array<string, mixed>>  $groupContributions
     * @return array<string, array{title: string, sidebar_icon: string, sort: int}>
     */
    private function collectGroupShellHints(array $rootContributions, array $groupContributions): array
    {
        $hints = [];

        foreach ($rootContributions as $c) {
            foreach (($c['items'] ?? []) as $node) {
                if (! is_array($node)) {
                    continue;
                }
                $g = (string) ($node['group'] ?? '');
                if ($g === '' || isset($hints[$g])) {
                    continue;
                }
                $hints[$g] = [
                    'title' => (string) ($node['title'] ?? $g),
                    'sidebar_icon' => (string) ($node['sidebar_icon'] ?? $node['icon'] ?? 'ki-filled ki-element-11'),
                    'sort' => (int) ($node['sort'] ?? $c['priority'] ?? 500),
                ];
            }
        }

        foreach ($groupContributions as $c) {
            $g = (string) ($c['group'] ?? '');
            if ($g === '' || $g === 'security' || isset($hints[$g])) {
                continue;
            }
            $gm = $c['group_meta'] ?? null;
            if (! is_array($gm)) {
                continue;
            }
            $hints[$g] = [
                'title' => (string) ($gm['title'] ?? $g),
                'sidebar_icon' => (string) ($gm['sidebar_icon'] ?? 'ki-filled ki-element-11'),
                'sort' => isset($gm['sort']) ? (int) $gm['sort'] : 650,
            ];
        }

        return $hints;
    }

    /**
     * Hedef üst grup yoksa oluşturulacak satır: önce bu katkıdaki `group_meta`, sonra shell ipuçları, son çare grup slug'ı.
     *
     * @param  array<string, array{title: string, sidebar_icon: string, sort: int}>  $shellHints
     * @param  array<string, mixed>  $contribution
     * @return array{title: string, sidebar_icon: string, sort: int}
     */
    private function resolveGroupShellForNewTopLevel(string $group, array $shellHints, array $contribution): array
    {
        $entryMeta = $contribution['group_meta'] ?? null;
        $map = $shellHints[$group] ?? null;

        $m = is_array($entryMeta) ? $entryMeta : [];
        $h = is_array($map) ? $map : [];

        return [
            'title' => (string) ($m['title'] ?? $h['title'] ?? $group),
            'sidebar_icon' => (string) ($m['sidebar_icon'] ?? $h['sidebar_icon'] ?? 'ki-filled ki-element-11'),
            'sort' => isset($m['sort']) ? (int) $m['sort'] : (isset($h['sort']) ? (int) $h['sort'] : 650),
        ];
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
        $hasRequest = $request instanceof Request;
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
