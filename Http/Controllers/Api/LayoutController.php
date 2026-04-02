<?php

namespace App\Core\AdminOps\Http\Controllers\Api;

use App\Core\Cache\Services\ApiCacheService;
use App\Core\System\Http\Controllers\Api\BaseApiController;
use App\Core\Localization\Models\Language;
use App\Core\Settings\Models\GeneralSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class LayoutController extends BaseApiController
{
    private const LAYOUT_MENU_POSITIONS = [
        'header',
        'header_top',
        'footer',
    ];

    private ApiCacheService $cacheService;

    public function __construct(ApiCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Header bileşenleri için toplu veri sağlar.
     *
     * Dönen format:
     * {
     *   "data": {
     *     "languages": {
     *       "data": [...],
     *       "default_code": "tr"
     *     },
     *     "menus": {
     *       "header": {...},
     *       "header_top": {...},
     *       "footer": {...}
     *     },
     *     "social_medias": {
     *       "data": [...]
     *     },
     *     "contact_info": {...},
     *     "general_settings": {...},
     *     "meta": {
     *       "requested_language": "en"
     *     }
     *   }
     * }
     */
    public function header(Request $request): JsonResponse
    {
        try {

            $languageCode = $request->query('language');

            $payload = $this->cacheService->getCachedHeaderPayload(
                function () use ($languageCode) {
                    return $this->buildHeaderPayload($languageCode);
                },

                $languageCode
            );

            return $this->formatResponse($payload);
        } catch (\Exception $e) {
            \Log::error('Layout Header API Error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'language' => $request->query('language'),
            ]);

            return $this->errorResponse('Bir hata oluştu', 500);
        }
    }

    private function buildHeaderPayload(?string $languageCode = null): array
    {
        $languages = $this->getActiveLanguages();
        $defaultLanguage = $languages->firstWhere('is_default', true);
        $requestedLanguage = $languageCode
            ? $languages->firstWhere('code', $languageCode)
            : null;

        $languagesData = $languages->map(function (Language $language) {
            return [
                'id' => $language->id,
                'attributes' => [
                    'code' => $language->code,
                    'name' => $language->name,
                    'locale' => $language->locale,
                    'is_default' => (bool) $language->is_default,
                    'is_active' => (bool) $language->is_active,
                    'direction' => $language->direction,
                    'date_format' => $language->date_format,
                    'time_format' => $language->time_format,
                    'svg_code' => $language->svg_code,
                ],
            ];
        })->values()->all();

        $menus = $this->getMenusPayload(

            $requestedLanguage,
            $defaultLanguage,
            $languages
        );

        $socialMedias = $this->getSocialMediasPayload(

            $requestedLanguage,
            $defaultLanguage,
            $languages
        );

        $generalSettings = $this->getGeneralSettingsPayload(

            $requestedLanguage,
            $defaultLanguage,
            $languages
        );

        $seoSettings = $this->getSeoSettingsPayload(

            $requestedLanguage,
            $defaultLanguage,
            $languages
        );

        $contactInfo = $this->getContactInfoPayload(

            $requestedLanguage,
            $defaultLanguage,
            $languages
        );

        return [
            'languages' => [
                'data' => $languagesData,
                'default_code' => $defaultLanguage?->code,
            ],
            'menus' => $menus,
            'social_medias' => [
                'data' => $socialMedias,
            ],
            'contact_info' => $contactInfo,
            'general_settings' => $generalSettings,
            'seo_settings' => $seoSettings,
            'meta' => [
                'requested_language' => $requestedLanguage?->code,
            ],
        ];
    }

    /**
     * Aktif dillerin koleksiyonunu döndürür.
     */
    private function getActiveLanguages(): Collection
    {
        return Language::query()
            ->where('is_active', true)
            ->select([
                'id',
                'code',
                'name',
                'region',
                'locale',
                'is_default',
                'is_active',
                'direction',
                'date_format',
                'time_format',
                'svg_code',
            ])
            ->orderBy('sort_order', 'asc')
            ->get();
    }

    /**
     * Menü verisini pozisyon bazlı hazırlar. Dil ilişkisi $languages üzerinden verilir (ek sorgu yok).
     */
    private function getMenusPayload(?Language $requestedLanguage, ?Language $defaultLanguage, Collection $languages): array
    {
        return collect(self::LAYOUT_MENU_POSITIONS)
            ->mapWithKeys(fn (string $position): array => [$position => null])
            ->all();
    }

    /**
     * Sosyal medya bağlantılarını dil önceliğine göre döndürür. Dil ilişkisi $languages üzerinden verilir.
     */
    private function getSocialMediasPayload(?Language $requestedLanguage, ?Language $defaultLanguage, Collection $languages): array
    {
        return [];
    }

    /**
     * Genel ayarları dil önceliğine göre döndürür. Dil ilişkisi $languages üzerinden verilir.
     */
    private function getGeneralSettingsPayload(?Language $requestedLanguage, ?Language $defaultLanguage, Collection $languages): ?array
    {
        $settings = GeneralSetting::query()
            ->where('is_active', true)
            ->get();

        foreach ($settings as $item) {
            $item->setRelation('language', $languages->firstWhere('id', $item->language_id));
        }

        $selected = $this->selectByLanguage(
            $settings,
            $requestedLanguage?->id,
            $defaultLanguage?->id
        );

        if (! $selected) {
            return null;
        }

        return [
            'id' => $selected->id,
            'attributes' => [
                'site_title' => $selected->site_title,
                'logo' => $selected->logo,
                'footer_logo' => $selected->footer_logo,
                'tursab_logo' => $selected->tursab_logo,
                'footer_text' => $selected->footer_text,
                'tursab_certificate_no' => $selected->tursab_certificate_no,
                'tursab_link' => $selected->tursab_link,
                'copyright_text' => $selected->copyright_text,
                'light_favicon' => $selected->light_favicon,
                'dark_favicon' => $selected->dark_favicon,
                'language' => [
                    'code' => $selected->language->code ?? null,
                    'name' => $selected->language->name ?? null,
                ],
            ],
        ];
    }

    private function getSeoSettingsPayload(?Language $requestedLanguage, ?Language $defaultLanguage, Collection $languages): ?array
    {
        return null;
    }

    /**
     * Menü verisini Strapi benzeri formata dönüştürür.
     * Resim path'leri proxy + filename formatına çevrilir (by-filename tek sorgu).
     */
    private function transformMenu(object $menu): array
    {
        $data = $this->parseMenuData($menu->data);
        $data = $this->normalizeMenuMediaUrls($data);

        return [
            'id' => $menu->id,
            'attributes' => [
                'title' => $menu->title,
                'slug' => $menu->slug,
                'position' => $menu->position,
                'is_active' => (bool) $menu->is_active,
                'language' => [
                    'code' => $menu->language->code ?? null,
                    'name' => $menu->language->name ?? null,
                ],
                'data' => $data,
            ],
        ];
    }

    /**
     * Menü verilerini decode eder.
     */
    private function parseMenuData($data): array
    {
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        if (is_array($data)) {
            return $data;
        }

        return ['items' => []];
    }

    /**
     * Menü ağacındaki resim path/URL'lerini /api/media/proxy/{filename} formatına çevirir.
     * Tek toplu Media sorgusu ile filename eşleştirmesi yapılır.
     */
    private function normalizeMenuMediaUrls(array $data): array
    {
        $paths = $this->collectImagePathsFromMenu($data);
        if (empty($paths)) {
            return $data;
        }

        $pathToProxy = $this->resolveMenuPathsToProxyUrls($paths);
        $this->replaceMenuMediaInPlace($data, $pathToProxy);

        return $data;
    }

    /** @return array<string> */
    private function collectImagePathsFromMenu(array $data): array
    {
        $collector = [];
        $imageExtensions = ['webp', 'jpg', 'jpeg', 'png', 'gif', 'svg'];
        foreach ($data as $value) {
            if (is_string($value) && $value !== '') {
                $lower = strtolower($value);
                $hasExtension = false;
                foreach ($imageExtensions as $ext) {
                    if (str_ends_with($lower, '.'.$ext) || str_contains($lower, '.'.$ext.'?')) {
                        $hasExtension = true;
                        break;
                    }
                }
                if ($hasExtension && (str_contains($value, '/') || preg_match('/\.(webp|jpg|jpeg|png|gif|svg)(\?|$)/i', $value))) {
                    $collector[] = $value;
                }
            } elseif (is_array($value)) {
                $collector = array_merge($collector, $this->collectImagePathsFromMenu($value));
            }
        }

        return $collector;
    }

    /**
     * Menü JSON içindeki görsel path'lerini /api/media/proxy/{filename} biçimine çevirir.
     * CMS Media modülü yokken veritabanı eşlemesi yapılmaz; dosya adı proxy üzerinden çözülür.
     * Medya kayıtları CMS tarafında tanımlandığında buraya port veya genişletilmiş eşleme eklenebilir.
     *
     * @param  array<string>  $paths
     * @return array<string, string> path => /api/media/proxy/filename
     */
    private function resolveMenuPathsToProxyUrls(array $paths): array
    {
        $paths = array_unique(array_filter($paths));
        if (empty($paths)) {
            return [];
        }

        $pathToProxy = [];
        foreach ($paths as $path) {
            $clean = explode('?', $path)[0];
            $base = basename($clean);
            $pathToProxy[$path] = '/api/media/proxy/'.rawurlencode($base);
        }

        return $pathToProxy;
    }

    /**
     * @param  array<string, string>  $pathToProxy
     */
    private function replaceMenuMediaInPlace(array &$data, array $pathToProxy): void
    {
        foreach ($data as $key => &$value) {
            if (is_string($value) && isset($pathToProxy[$value])) {
                $value = $pathToProxy[$value];
            } elseif (is_array($value)) {
                $this->replaceMenuMediaInPlace($value, $pathToProxy);
            }
        }
    }

    /**
     * Koleksiyondan dil önceliğine göre ilk uygun kaydı döndürür.
     *
     * @param  Collection<int, mixed>  $items
     */
    private function selectByLanguage(Collection $items, ?int $languageId, ?int $fallbackLanguageId)
    {
        if ($languageId) {
            $match = $items->firstWhere('language_id', $languageId);
            if ($match) {
                return $match;
            }
        }

        if ($fallbackLanguageId) {
            $match = $items->firstWhere('language_id', $fallbackLanguageId);
            if ($match) {
                return $match;
            }
        }

        return $items->first();
    }

    /**
     * Koleksiyonu dil önceliğine göre filtreler.
     *
     * @param  Collection<int, mixed>  $items
     */
    private function filterCollectionByLanguage(Collection $items, ?int $languageId, ?int $fallbackLanguageId): Collection
    {
        if ($languageId) {
            $filtered = $items->where('language_id', $languageId);
            if ($filtered->isNotEmpty()) {
                return $filtered;
            }
        }

        if ($fallbackLanguageId) {
            $filtered = $items->where('language_id', $fallbackLanguageId);
            if ($filtered->isNotEmpty()) {
                return $filtered;
            }
        }

        return $items;
    }

    /**
     * İletişim bilgilerini dil önceliğine göre döndürür. Dil ilişkisi $languages üzerinden verilir.
     */
    private function getContactInfoPayload(?Language $requestedLanguage, ?Language $defaultLanguage, Collection $languages): ?array
    {
        return null;
    }
}
