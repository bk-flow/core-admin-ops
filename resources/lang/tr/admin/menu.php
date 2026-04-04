<?php

/**
 * Çekirdek panel menü çevirileri (AdminMenuService + ortak shell).
 * Modül özel başlıklar Modules/... ve app/Core/.../resources/lang/.../admin/menu.php ile birleştirilir.
 * Çekirdek modül admin.xx satırları app/Core/.../resources/lang (CoreModuleTranslationRegistrar) üzerinden yüklenir.
 */
return [
    'dashboard' => [
        'title' => 'Kontrol Paneli',
        'link_account' => 'Hesabım',
        'link_reports' => 'Yönetici raporları',
    ],
    'site_content' => [
        'title' => 'İçerik',
    ],
    'customers' => [
        'title' => 'Müşteriler',
    ],
    'media_library' => [
        'title' => 'Medya kütüphanesi',
    ],
    'users_operations' => [
        'title' => 'Kullanıcılar & İşlemler',
    ],
    'billing_hub' => [
        'title' => 'Ödemeler & Talepler',
        'payments' => 'Ödemeler',
        'orders' => 'Siparişler',
        'invoices' => 'Faturalar',
        'currencies' => 'Para birimleri',
        'coupons' => 'Kuponlar',
        'campaigns' => 'Kampanyalar',
        'bank_accounts' => 'Banka hesapları',
        'accounting' => 'Muhasebe',
        'bank_transfers' => 'Banka transfer bildirimleri',
        'payment_notification_settings' => 'Ödeme bildirim ayarları',
    ],
    'user_management' => [
        'title' => 'Kullanıcı Yönetimi',
        'users' => 'Panel Kullanıcıları',
        'roles' => 'Yönetici Rolleri',
        'permissions' => 'Yönetici İzinleri',
    ],
    'settings' => [
        'title' => 'Ayarlar & Yapılandırma',
        'panel_users' => [
            'title' => 'Kullanıcılar ve işlemler',
        ],
        'security_section' => [
            'title' => 'Güvenlik yönetimi',
        ],
        'mail_config' => [
            'title' => 'E-posta yapılandırması',
            'smtp' => 'E-posta ayarları (SMTP)',
            'templates' => 'E-posta şablonları',
        ],
        'integrations_management' => [
            'title' => 'Entegrasyon Yönetimi',
            'payment' => 'Ödeme Sistemleri',
        ],
        'contact_information' => [
            'title' => 'İletişim bilgileri',
        ],
        'license_ops' => [
            'title' => 'License Ops',
        ],
        'localization' => [
            'title' => 'Yerelleştirme',
        ],
        'lang_management' => [
            'title' => 'Dil Yönetimi',
        ],
        'country_city_management' => [
            'title' => 'Ülke ve şehir',
        ],
        'cookie_management' => [
            'title' => 'Çerez yönetimi',
        ],
        'cookie_services_management' => [
            'title' => 'Çerez hizmetleri',
        ],
        'unknown_cookies_management' => [
            'title' => 'Bilinmeyen çerezler',
        ],
        'mail_management' => [
            'template' => 'E-posta Şablonları',
        ],
    ],
    'content' => [
        'title' => 'İçerik Yönetimi',
        'pages_management' => [
            'title' => 'Sayfa Yönetimi',
            'pages' => 'Sayfalar',
            'page_builder' => 'Blok kütüphanesi',
        ],
        'form_management' => [
            'title' => 'Form Yönetimi',
            'forms' => 'Formlar',
            'analytics' => 'Analitik',
            'audit_logs' => 'Denetim kayıtları',
            'requests' => 'Talepler',
            'newsletter_subscriptions' => 'Bülten abonelikleri',
        ],
        'agreements_management' => [
            'title' => 'Sözleşmeler',
            'agreements' => 'Sözleşme şablonları',
            'agreement_management' => 'Kullanıcı sözleşmeleri',
        ],
        'faq_management' => [
            'title' => 'SSS',
            'categories' => 'Kategoriler',
            'faqs' => 'Sorular',
        ],
        'reviews' => 'Yorumlar',
        'navigation_menus' => 'Menü yönetimi',
        'media_management' => 'Medya yönetimi',
    ],
    'security' => [
        'title' => 'Güvenlik Yönetimi',
        'cache_manager' => [
            'title' => 'Önbellek Yönetimi',
        ],
        'database_operations' => [
            'title' => 'Veritabanı Operasyonları',
        ],
        'ip_management' => [
            'title' => 'IP Yönetimi',
        ],
    ],
    'search_placeholder' => 'Menü ara...',
    'favorites' => [
        'title' => 'Sık Kullanılanlar',
        'remove' => 'Favorilerden çıkar',
    ],
    'category_show' => 'Göster',
    'seo_management' => [
        'title' => 'SEO yönetimi',
    ],
];
