<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Support\RichEditor;

use Capell\StructuredContentLibrary\Providers\StructuredContentLibraryServiceProvider;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Facades\FilamentAsset;
use Override;
use Tiptap\Core\Extension;

final class PortableContentPlugin implements RichContentPlugin
{
    /** @return array<Extension> */
    #[Override]
    public function getTipTapPhpExtensions(): array
    {
        return [new PortableListSemanticsExtension, new PortableEmptyInline, new PortableNestedInline];
    }

    /** @return array<string> */
    #[Override]
    public function getTipTapJsExtensions(): array
    {
        $script = FilamentAsset::getScriptSrc('portable-list-semantics', StructuredContentLibraryServiceProvider::$name);

        return [$script, $script . '#codeBlock', $script . '#link', $script . '#orderedList', $script . '#emptyInline', $script . '#nestedInline', $script . '#code', $script . '#bulletList'];
    }

    /** @return array<RichEditorTool> */
    #[Override]
    public function getEditorTools(): array
    {
        return [];
    }

    /** @return array<Action> */
    #[Override]
    public function getEditorActions(): array
    {
        return [];
    }
}
