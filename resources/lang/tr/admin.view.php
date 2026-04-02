<?php

return [
    'types' => [
        'root' => 'Root',
        'admin' => 'Yönetici',
        'editor' => 'Editör',
    ],
    'descriptions' => [
        'root' => 'Tüm menüler ve özellikler görünür.',
        'admin' => 'İçerik, Güvenlik ve Varlık yönetimi menüleri görünür.',
        'editor' => 'İçerik ve Varlık yönetimi menüleri görünür.',
    ],
    'changed_successfully' => 'Görünüm başarıyla :view olarak değiştirildi.',
    'change_modal' => [
        'title' => 'Görünümü Değiştir',
        'description' => 'Şu an :view görünümündesiniz.',
        'info' => 'Not: Görünüm değişikliği sadece menü görünümünü etkiler. Yetkilendirme kontrolü değişmez.',
    ],
    'errors' => [
        'no_selection' => 'Lütfen bir görünüm seçin.',
        'change_failed' => 'Görünüm değiştirilemedi.',
    ],
];
