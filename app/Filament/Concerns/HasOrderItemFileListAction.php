<?php

namespace App\Filament\Concerns;

use Filament\Tables\Actions\Action;

trait HasOrderItemFileListAction
{
    /**
     * One order item can carry several files in `$relation` (up to its qty).
     * Builds a view-only action that opens a modal listing each one as a
     * download link — shared by both relation managers that display order
     * item attachments/results, so a fix here covers both instead of just
     * the one that happened to get reported.
     */
    protected static function fileListAction(string $name, string $label, string $icon, string $relation, string $routeName): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->modalHeading($label)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->modalContent(fn ($record) => view('filament.order-item-file-list', [
                'files' => $record->$relation,
                'routeName' => $routeName,
            ]))
            ->visible(fn ($record) => $record->$relation()->exists());
    }
}
