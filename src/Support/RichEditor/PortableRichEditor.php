<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Support\RichEditor;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Filament\Forms\Components\RichEditor\StateCasts\RichEditorStateCast;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;
use Override;
use Tiptap\Marks\Link;
use Tiptap\Nodes\CodeBlock;
use Tiptap\Nodes\OrderedList;

final class PortableRichEditor extends RichEditor
{
    #[Override]
    public function getTipTapEditor(): PortableTipTapEditor
    {
        $configuration = RichContentRenderer::make()
            ->plugins($this->getPlugins())
            ->linkProtocols($this->getLinkProtocols())
            ->getTipTapPhpConfiguration();

        // Replace the stock node before schema construction: duplicate nodes corrupt nested lists.
        foreach ($configuration['extensions'] as $index => $extension) {
            if ($extension instanceof OrderedList) {
                $configuration['extensions'][$index] = new PortableOrderedList($extension->options);
            } elseif ($extension instanceof Link) {
                $configuration['extensions'][$index] = new PortableLink($extension->options);
            } elseif ($extension instanceof CodeBlock) {
                $configuration['extensions'][$index] = new PortableCodeBlock($extension->options);
            }
        }

        return new PortableTipTapEditor($configuration);
    }

    /** @return array<StateCast> */
    #[Override]
    public function getDefaultStateCasts(): array
    {
        return array_map(
            fn (StateCast $cast): StateCast => $cast instanceof RichEditorStateCast ? new PortableRichEditorStateCast($this) : $cast,
            parent::getDefaultStateCasts(),
        );
    }
}
