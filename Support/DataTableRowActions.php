<?php

declare(strict_types=1);

namespace App\Core\AdminOps\Support;

/**
 * HTML for DataTables row action dropdowns (KTMenu trigger + panel).
 */
final class DataTableRowActions
{
    /**
     * @param  string  $minWidthClass  Tailwind min-width (e.g. min-w-[10rem], min-w-[12.5rem])
     */
    public static function menuOpen(string $minWidthClass = 'min-w-[10rem]'): string
    {
        $aria = e(__('admin.overall.processes'));

        return '<div class="flex justify-end shrink-0">'
            .'<button type="button" class="kt-btn kt-btn-sm kt-btn-icon kt-btn-outline" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end" aria-label="'.$aria.'">'
            .'<i class="ki-filled ki-dots-vertical text-lg"></i>'
            .'</button>'
            .'<div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded '.$minWidthClass.' py-2" data-kt-menu="true">';
    }

    public static function menuClose(): string
    {
        return '</div></div>';
    }
}
