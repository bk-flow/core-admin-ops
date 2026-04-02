<?php

// Admin namespace için nested translation dosyalarını referans eder
// Laravel parseKey metodu admin.dashboard.title key'ini [null, 'admin', 'dashboard.title'] olarak parse eder
// Bu yüzden admin.php dosyası içinde dashboard key'ini tanımlamak gerekiyor
$moduleLangPath = static function (string $modulePath): string {
    $moduleFile = base_path('Modules/'.$modulePath);
    if (is_file($moduleFile)) {
        return $moduleFile;
    }

    return base_path('app/'.$modulePath);
};

$safeRequire = static function (string $path): array {
    if (! is_file($path)) {
        return [];
    }

    $loaded = require $path;

    return is_array($loaded) ? $loaded : [];
};

$safeKey = static function (array $source, string $key): array {
    $value = $source[$key] ?? [];

    return is_array($value) ? $value : [];
};

$coreAuthLang = base_path('app/Core/Auth/resources/lang/tr');

$mergeAdminMenu = static function () use ($safeRequire) {
    $core = $safeRequire(__DIR__.'/admin/menu.php');
    $paths = array_merge(
        glob(base_path('app/Core/*/resources/lang/tr/admin/menu.php')) ?: [],
        glob(base_path('Modules/*/resources/lang/tr/admin/menu.php')) ?: [],
        glob(base_path('Modules/*/*/resources/lang/tr/admin/menu.php')) ?: [],
    );
    sort($paths);
    foreach ($paths as $path) {
        if (is_file($path)) {
            $core = array_replace_recursive($core, $safeRequire($path));
        }
    }

    return $core;
};

$accountTranslations = $safeRequire($coreAuthLang.'/admin.account.php');
$authTranslations = $safeRequire($coreAuthLang.'/admin.auth.php');
$overallTr = $safeRequire(__DIR__.'/admin.overall.php');

return [
    // Root seviye dosyalar
    'dashboard' => $safeRequire(__DIR__.'/admin.dashboard.php'),
    'menu' => $mergeAdminMenu(),
    'media' => $safeRequire($moduleLangPath('CMS/Media/resources/lang/tr/admin/media.php')),
    'account' => $safeKey($accountTranslations, 'account'), // account key'leri account.php içinden
    'common' => $safeKey($accountTranslations, 'common'), // common key'leri account.php içinden
    'auth' => $authTranslations,
    'session' => $safeKey($authTranslations, 'session'), // session key'leri auth.php içinden
    'login' => $safeKey($authTranslations, 'login'), // login key'leri auth.php içinden
    'errors' => $safeKey($authTranslations, 'errors'), // errors key'leri auth.php içinden
    'view' => $safeRequire(__DIR__.'/admin.view.php'),
    'overall' => $overallTr,
    // DataTables dil paketi: bazı Blade'ler doğrudan admin.datatable anahtarını JSON ile kullanır.
    'datatable' => $overallTr['datatable'] ?? [],
    'pages' => $safeRequire($moduleLangPath('CMS/Page/resources/lang/tr/admin/content/pages.php')),
    'page_speed_insights' => $safeRequire($moduleLangPath('CMS/Seo/resources/lang/tr/admin/page_speed_insights.php')),
    'db_operations' => [
        'title' => 'Veritabanı operasyonları',
        'subtitle' => 'PostgreSQL sağlık özeti, gözlemlenebilirlik ve kontrollü bakım işlemleri.',
        'db_summary' => 'Veritabanı özeti',
        'section_kpi' => 'Genel görünüm',
        'section_observability' => 'Gözlemlenebilirlik (salt okunur)',
        'section_observability_hint' => 'Metrikler önbellek veya zamanlanmış snapshot ile gelir; zaman damgasına bakın.',
        'section_actions' => 'Kontrollü işlemler',
        'section_actions_hint' => 'İşlemler kuyrukta asenkron çalışır; durumu aşağıdaki tablolardan izleyin.',
        'section_activity' => 'Son aktivite',
        'section_activity_hint' => 'Panelden tetiklenen son yedek ve bakım işlemleri.',
        'connections' => 'Bağlantılar',
        'locks' => 'Kilitler',
        'extensions' => 'Eklentiler',
        'backups' => 'Yedekler',
        'maintenance' => 'Bakım',
        'controlled_operations' => 'Kontrollü işlemler',
        'manual_backup' => 'Manuel yedek',
        'reason' => 'Gerekçe',
        'reason_help' => 'Denetim kaydı için zorunludur (en az 3 karakter).',
        'run_backup' => 'Yedeği kuyruğa al',
        'maintenance_action' => 'Bakım',
        'action_enable_extension' => 'Eklenti etkinleştir (beyaz liste)',
        'action_vacuum_analyze' => 'VACUUM ANALYZE',
        'action_reindex' => 'REINDEX TABLE',
        'action' => 'İşlem',
        'target' => 'Hedef',
        'target_help' => 'Eklenti adı (ör. pg_trgm) veya vacuum/reindex için şema.tablo.',
        'target_placeholder' => 'ör. pg_trgm veya public.tablo_adi',
        'confirm_controlled' => 'Veritabanında kontrollü bir işlem çalıştırdığımı anlıyorum.',
        'run_maintenance' => 'Bakımı kuyruğa al',
        'read_only_notice' => 'Gözlemlenebilirlik bölümleri salt okunurdur; veritabanını değiştirmez.',
        'badge_read_only' => 'Salt okunur',
        'badge_queued' => 'Kuyruk',
        'badge_write' => 'Yazma',
        'refresh_metrics' => 'Metrikleri yenile',
        'refresh_activity' => 'Tabloları yenile',
        'refresh_all' => 'Verileri yenile',
        'btn_working' => 'İşleniyor…',
        'action_read_only_role' => 'Veritabanı yazma yetkiniz yok; kontrollü işlemler gizlendi.',
        'toast_refreshed' => 'Dashboard verileri güncellendi.',
        'reindex_disabled_hint' => 'REINDEX yapılandırma ile kapalıdır.',
        'whitelist_extensions' => 'İzin verilen eklentiler',
        'connections_locks_title' => 'Bağlantılar ve kilit bekleyişleri',
        'last_collected' => 'Son toplama',
        'active_connections' => 'Aktif bağlantılar',
        'waiting_locks' => 'Bekleyen kilitler',
        'table_sizes_snapshot' => 'En büyük tablolar (snapshot)',
        'backup_history_snapshot' => 'Yedek geçmişi (snapshot)',
        'latest_backups' => 'Son panel yedekleri',
        'latest_runs' => 'Son operasyon çalışmaları',
        'backup_management' => 'Yedek yönetimi (tümü)',
        'no_data' => 'Veri yok.',
        'no_snapshot' => 'Snapshot henüz hazır değil.',
        'no_records' => 'Henüz kayıt yok.',
        'empty_kpi' => 'Metrikler geçici olarak kullanılamıyor.',
        'messages' => [
            'backup_queued' => 'Yedekleme kuyruğa alındı.',
            'maintenance_queued' => 'Bakım işlemi kuyruğa alındı.',
            'backup_deleted' => 'Yedek sunucudan silindi.',
            'confirm_delete_backup' => 'Bu yedeği sunucudan silmek istediğinize emin misiniz?',
        ],
        'errors' => [
            'concurrent_run' => 'Bu işlem ve hedef için zaten aktif bir çalışma var.',
            'extension_not_whitelisted' => 'Bu eklenti izin listesinde değil.',
            'reindex_disabled' => 'REINDEX özelliği kapalı (feature flag).',
            'invalid_target' => 'Geçersiz hedef.',
            'invalid_reason' => 'Geçersiz gerekçe.',
            'generic' => 'İşlem başlatılamadı.',
            'backup_download_unavailable' => 'Bu yedek indirilemez durumda.',
            'backup_file_missing' => 'Yedek dosyası sunucuda bulunamadı.',
        ],
        'labels' => [
            'database' => 'Veritabanı',
            'db_size' => 'Veritabanı boyutu',
            'table_count' => 'Tablo sayısı',
            'index_count' => 'İndeks sayısı',
            'lock_type' => 'Kilit türü',
            'mode' => 'Mod',
            'waiting' => 'Bekleyen',
            'name' => 'Ad',
            'version' => 'Sürüm',
            'table' => 'Tablo',
            'total_size' => 'Toplam boyut',
            'file' => 'Dosya',
            'size' => 'Boyut',
            'status' => 'Durum',
            'date' => 'Tarih',
            'id' => 'ID',
            'started' => 'Başladı',
            'finished' => 'Bitti',
            'duration' => 'Süre',
            'detail' => 'Ayrıntı',
            'actions' => 'İşlemler',
            'download' => 'İndir',
            'delete' => 'Sil',
        ],
        'section_phase3' => 'Öngörüler ve trendler',
        'section_phase3_hint' => 'Zamanlanmış metrik snapshot’larına dayanır — canlı ağır sorgu çalıştırmaz.',
        'phase3_last_snapshot' => 'Son metrik snapshot',
        'phase3_points' => 'Trend noktası',
        'phase3_open_advisories' => 'Açık öneriler',
        'phase3_severity_breakdown' => 'Önem derecesine göre',
        'phase3_trends' => 'Trendler (snapshot serisi)',
        'phase3_advisories' => 'Öneriler',
        'phase3_no_advisories' => 'Açık öneri yok.',
        'phase3_no_trend' => 'Trend için zamanlanmış snapshot toplama çalışmalıdır.',
        'phase3_col_type' => 'Sinyal',
        'phase3_col_summary' => 'Özet',
        'phase3_labels' => [
            'db_size' => 'DB boyutu (bayt)',
            'connections' => 'Aktif bağlantılar',
            'cache_hit' => 'Önbellek isabet oranı',
            'index_hit' => 'İndeks tampon isabet oranı',
        ],
        'phase3' => [
            'advisory_types' => [
                'capability_unavailable' => 'Metrikler kullanılamıyor',
                'capability_pg_stat_database' => 'pg_stat_database kısıtlı',
                'capability_pg_statio' => 'pg_statio_user_indexes kısıtlı',
                'high_sequential_scan' => 'Yüksek sıralı tarama payı',
                'low_cache_hit_ratio' => 'Düşük tampon önbellek isabeti',
                'low_index_buffer_hit_ratio' => 'Düşük indeks tampon isabeti',
                'long_running_queries' => 'Uzun süren sorgular',
                'capability_reindex_disabled' => 'Yapılandırmada REINDEX kapalı',
                'table_growth' => 'Hızlı tablo büyümesi',
                'snapshot_stale' => 'Metrik snapshot’ları eski',
                'repeated_failure' => 'Tekrarlayan başarısız işlemler',
            ],
            'reasons' => [
                'metrics_unavailable' => 'Metrik snapshot toplaması kullanılabilir PostgreSQL metriği döndürmedi.',
                'pg_stat_database_unavailable' => 'pg_stat_database tampon önbellek istatistikleri alınamıyor.',
                'pg_statio_unavailable' => 'pg_statio_user_indexes indeks I/O istatistikleri alınamıyor.',
                'high_seq_scan' => 'Tahmini sıralı tarama payı yüksek (oran :ratio).',
                'low_cache_hit' => 'Tampon önbellek isabet oranı düşük (:ratio).',
                'low_index_hit' => 'İndeks tampon isabet oranı düşük (:ratio).',
                'long_queries' => 'Eşik üzerinde :count uzun süren oturum var.',
                'reindex_flag_off' => 'Panelden REINDEX yapılandırma ile kapalı.',
                'table_growth' => ':table tablosu önceki snapshot’a göre yaklaşık %:percent büyüdü.',
                'snapshot_stale' => 'Yakın zamanda metrik snapshot yok — zamanlanmış toplama eksik olabilir.',
                'repeated_failures' => ':target hedefi için :action işlemi izleme penceresinde :count kez başarısız oldu.',
            ],
            'actions' => [
                'check_driver_pg' => 'Uygulamanın PostgreSQL kullandığını ve pg_catalog okumalarına izin olduğunu doğrulayın.',
                'check_permissions_stats' => 'Rolün pg_stat_database okuyabildiğini ve istatistiklerin açık olduğunu kontrol edin.',
                'index_hit_unavailable' => 'Sürüyorsa pg_statio_user_indexes izinlerini gözden geçirin.',
                'review_indexes_seq' => 'Büyük tablolar için indeks ve planları gözden geçirin; sıralı taramayı azaltın.',
                'tune_cache_workload' => 'İş yükü ve bellek ayarlarını gözden geçirin; bakım penceresi düşünün.',
                'review_index_usage' => 'İndeks kullanımı ve şişmeyi gözden geçirin; ANALYZE için bakım penceresi.',
                'review_activity' => 'Bakım penceresinde pg_stat_activity ile engelleyen sorguları inceleyin.',
                'enable_reindex_if_needed' => 'Operasyon politikası uygunsa REINDEX’i açın.',
                'review_table_growth' => 'Büyümenin beklenen olduğunu doğrulayın; arşiv veya partition planlayın.',
                'ensure_scheduler' => '`php artisan database:metrics:snapshot` komutunun zamanlandığından emin olun.',
                'review_failed_ops' => 'Aktivite tabloları ve uygulama günlüklerindeki başarısız çalışmaları inceleyin.',
            ],
        ],
        'job_errors' => [
            'backup_failed' => 'Yedekleme işi başarısız. Ayrıntıda hata kodu ve teknik özet vardır.',
            'maintenance_failed' => 'Bakım işi başarısız. Ayrıntıda hata kodu ve teknik özet vardır.',
        ],
        'alerts' => [
            'titles' => [
                'stale_snapshot' => 'Metrik snapshot’ları eski',
                'danger_advisories' => 'Danger seviyesinde açık öneri var',
                'metrics_unavailable' => 'Son snapshot kullanılabilir metrik içermiyor',
                'pending_stale' => 'Kuyrukta bekleyen işlemler takılmış görünüyor',
                'repeated_backup' => 'Tekrarlayan yedekleme hataları',
                'repeated_maintenance' => 'Tekrarlayan bakım hataları',
            ],
            'messages' => [
                'stale_snapshot' => 'Son :minutes dakika içinde snapshot yok. Zamanlayıcıda `php artisan database:metrics:snapshot` çalıştığını doğrulayın.',
                'danger_advisories' => 'Danger öneminde :count açık öneri var.',
                'metrics_unavailable' => 'Son snapshot PostgreSQL istatistiklerini okuyamadı (sürücü veya izin).',
                'pending_stale' => ':minutes dakikadan uzun süredir bekleyen :count çalışma var — kuyruk işçilerini kontrol edin.',
                'repeated_backup' => 'Öneri penceresinde yedekleme işlemleri birden fazla başarısız oldu.',
                'repeated_maintenance' => 'Öneri penceresinde bakım işlemleri birden fazla başarısız oldu.',
            ],
        ],
        'operational' => [
            'section_health' => 'Operasyonel sağlık',
            'health_hint' => 'Üstteki KPI kartları birincil veridir. Bu bölüm ortam, kuyruk ve snapshot özetini rozetlerle gösterir. Turuncu kutu yalnızca kuyruk, bekleyen iş veya yapılandırma gibi dikkat gerektiren durumlarda görünür.',
            'section_alerts' => 'Aktif operasyonel uyarılar',
            'no_alerts' => 'Aktif operasyonel uyarı yok.',
            'degraded_title' => 'Modül düşük performans / bozulma durumunda',
            'critical_notice_title' => 'Dikkat: müdahale gerekebilir',
            'meta_snapshot' => 'Son snapshot zamanı',
            'meta_job' => 'Son metrik işi',
            'meta_pending' => 'Uzun süren bekleyen çalışma',
            'snap_short' => 'Snapshot',
            'reindex_short' => 'Reindex',
            'snap_badge_fresh' => 'Güncel',
            'snap_badge_stale' => 'Güncel değil',
            'snap_badge_missing' => 'Henüz oluşturulmadı',
            'reindex_on' => 'Açık',
            'reindex_off' => 'Kapalı',
            'reason_snapshot_stale' => 'Bazı metrikler güncel değil',
            'reason_reindex_flag' => 'Reindex varsayılan olarak kapalı (bilgi)',
            'reason_metrics_job' => 'Metrik zamanlayıcısı üretimde görülmüyor — kuyruk/schedule kontrol edin',
            'reason_pending_stale' => 'Bekleyen işlemler olağan süreyi aştı — kuyruk işçisini kontrol edin',
            'reason_non_pgsql' => 'Üretim ortamında PostgreSQL sürücüsü bekleniyor',
            'reason_backup_disk' => 'Yedek diski yapılandırması eksik veya geçersiz',
            'env' => 'Ortam',
            'db_driver' => 'DB sürücüsü',
            'queue' => 'Kuyruk',
            'snapshot_at' => 'Son snapshot',
            'last_job' => 'Son metrik işi',
            'pending_stale' => 'Eski bekleyen çalışma',
        ],
        'detail_modal' => [
            'title' => 'İşlem ayrıntısı',
            'backup_title' => 'Yedek #',
            'run_title' => 'Operasyon çalışması #',
            'advisory_title' => 'Öneri',
            'fields' => [
                'initiated_by' => 'Initie eden',
                'reason' => 'Gerekçe',
                'duration' => 'Süre',
                'failure_code' => 'Hata kodu',
                'error' => 'Kullanıcı mesajı',
                'detail' => 'Teknik ayrıntı (temizlenmiş)',
                'checksum' => 'Sağlama (önek)',
                'file_size' => 'Dosya boyutu (bayt)',
                'result' => 'Sonuç özeti',
                'recommended' => 'Önerilen aksiyon',
            ],
        ],
    ],

    // CMS ayarları (Settings / Localization çekirdek modülleri kendi admin.php ile birleştirir)
    'general_seo' => $safeRequire($moduleLangPath('CMS/Seo/resources/lang/tr/admin/settings/general_seo.php')),
    'contact_infos' => $safeRequire($moduleLangPath('CMS/ContactInfo/resources/lang/tr/admin/settings/contact_infos.php')),
    'social_medias' => $safeRequire($moduleLangPath('CMS/SocialMedia/resources/lang/tr/admin/settings/social_medias.php')),

    // Content klasöründeki dosyalar
    'content' => [
        'pages' => $safeRequire($moduleLangPath('CMS/Page/resources/lang/tr/admin/content/pages.php')),
        'menus' => $safeRequire($moduleLangPath('CMS/Menu/resources/lang/tr/admin/content/menus.php')),
        'forms' => $safeRequire($moduleLangPath('CMS/Form/resources/lang/tr/admin/content/forms.php')),
        'agreements' => $safeRequire($moduleLangPath('CMS/Agreement/resources/lang/tr/admin/content/agreements.php')),
        'blog' => $safeRequire($moduleLangPath('CMS/Blog/resources/lang/tr/admin/content/blog.php')),
        'blog_categories' => $safeRequire($moduleLangPath('CMS/Blog/resources/lang/tr/admin/content/blog_categories.php')),
        'faq' => $safeRequire($moduleLangPath('CMS/Faq/resources/lang/tr/admin/content/faq.php')),
        'faq_categories' => $safeRequire($moduleLangPath('CMS/Faq/resources/lang/tr/admin/content/faq_categories.php')),
        'reviews' => $safeRequire($moduleLangPath('CMS/Reviews/resources/lang/tr/admin/content/reviews.php')),
        'messages' => $safeRequire($moduleLangPath('CMS/Form/resources/lang/tr/admin/content/messages.php')),
        'meta_tag_definitions' => $safeRequire($moduleLangPath('CMS/Seo/resources/lang/tr/admin/content/meta_tag_definitions.php')),
        'url_patterns' => $safeRequire($moduleLangPath('CMS/Routing/resources/lang/tr/admin/content/url_patterns.php')),
        'url_redirects' => $safeRequire($moduleLangPath('CMS/Routing/resources/lang/tr/admin/content/url_redirects.php')),
        'sitemap' => $safeRequire($moduleLangPath('CMS/Seo/resources/lang/tr/admin/content/sitemap.php')),
    ],

    // Root seviye alias'lar (Blade dosyalarında kullanılan keyler için)
    'messages' => $safeRequire($moduleLangPath('CMS/Form/resources/lang/tr/admin/content/messages.php')),
    'agreements' => $safeRequire($moduleLangPath('CMS/Agreement/resources/lang/tr/admin/content/agreements.php')),
    'url_patterns' => $safeRequire($moduleLangPath('CMS/Routing/resources/lang/tr/admin/content/url_patterns.php')),
    'url_redirects' => $safeRequire($moduleLangPath('CMS/Routing/resources/lang/tr/admin/content/url_redirects.php')),
    'menus' => $safeRequire($moduleLangPath('CMS/Menu/resources/lang/tr/admin/content/menus.php')),
    'forms' => $safeRequire($moduleLangPath('CMS/Form/resources/lang/tr/admin/content/forms.php')),
    'blog' => $safeRequire($moduleLangPath('CMS/Blog/resources/lang/tr/admin/content/blog.php')),
    'blog_categories' => $safeRequire($moduleLangPath('CMS/Blog/resources/lang/tr/admin/content/blog_categories.php')),
    'faq' => $safeRequire($moduleLangPath('CMS/Faq/resources/lang/tr/admin/content/faq.php')),
    'faq_categories' => $safeRequire($moduleLangPath('CMS/Faq/resources/lang/tr/admin/content/faq_categories.php')),
    'reviews' => $safeRequire($moduleLangPath('CMS/Reviews/resources/lang/tr/admin/content/reviews.php')),
    'meta_tag_definitions' => $safeRequire($moduleLangPath('CMS/Seo/resources/lang/tr/admin/content/meta_tag_definitions.php')),
    'sitemap' => $safeRequire($moduleLangPath('CMS/Seo/resources/lang/tr/admin/content/sitemap.php')),

    // Site management
    'site_change' => require __DIR__.'/admin.site_change.php',
    'site_selection' => require __DIR__.'/admin.site_selection.php',

    'seo_mapping' => $safeRequire($moduleLangPath('CMS/Seo/resources/lang/tr/admin/seo_mapping.php')),

];
