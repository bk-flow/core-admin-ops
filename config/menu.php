<?php

return [
    'dashboard' => [
        'title' => 'admin.menu.dashboard.title',
        'sidebar_icon' => 'ki-filled ki-chart-line-star',
        'sort' => 10,
        'home' => [
            'title' => 'admin.menu.dashboard.title',
            'route' => 'cms.admin.dashboard',
            'permission' => 'dashboard_read',
            'active_routes' => ['cms.admin.dashboard'],
        ],
    ],
    'settings' => [
        'title' => 'admin.menu.settings.title',
        'sidebar_icon' => 'ki-filled ki-setting-2',
        'sort' => 10000,
    ],
];
