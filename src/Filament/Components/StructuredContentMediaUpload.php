<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Filament\Components;

use Capell\Admin\Actions\GetMaxUploadSizeInBytes;
use Capell\Admin\Filament\Components\Forms\MediaLibraryFileUpload;
use Capell\Core\Enums\MediaCollectionEnum;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Support\Facades\Config;

/**
 * The picture a record carries: a photo for people, services and stories, a logo for partners
 * and logos. The file is stored on the record itself, so each record keeps its own.
 */
final class StructuredContentMediaUpload
{
    public static function make(MediaCollectionEnum $collection): SpatieMediaLibraryFileUpload
    {
        $field = MediaLibraryFileUpload::make('media_' . $collection->value);

        // The Admin factory carries the standard disk, metadata and image configuration; fall back
        // to a plain Spatie upload if another media backend replaced it.
        if (! $field instanceof SpatieMediaLibraryFileUpload) {
            $field = SpatieMediaLibraryFileUpload::make('media_' . $collection->value)
                ->maxSize(GetMaxUploadSizeInBytes::run());
        }

        return $field
            ->collection($collection->value)
            ->label(__('capell-structured-content-library::admin.media_' . $collection->value))
            ->helperText(__('capell-structured-content-library::admin.media_' . $collection->value . '_help'))
            ->disk(self::uploadDisk())
            ->visibility('public')
            ->image()
            ->acceptedFileTypes(StructuredContentItem::MEDIA_MIME_TYPES)
            ->columnSpanFull();
    }

    /**
     * Filament uploads to its own default disk, which is private on a standard Laravel install.
     * Public pages only show pictures from a publicly visible disk, so store them on one: the media
     * library's configured disk when it is public, otherwise the "public" disk.
     */
    public static function uploadDisk(): string
    {
        $configured = Config::get('media-library.disk_name');

        if (is_string($configured) && $configured !== '' && Config::get(sprintf('filesystems.disks.%s.visibility', $configured)) === 'public') {
            return $configured;
        }

        return 'public';
    }
}
