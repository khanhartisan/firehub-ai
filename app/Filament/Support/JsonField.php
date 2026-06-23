<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Textarea;

class JsonField
{
    public static function make(string $name, string $helperText = 'JSON payload.', int $rows = 8): Textarea
    {
        return Textarea::make($name)
            ->rows($rows)
            ->columnSpanFull()
            ->formatStateUsing(static function ($state): string {
                if (is_string($state)) {
                    return $state;
                }

                if (is_array($state)) {
                    return (string) json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                }

                if (is_object($state) && method_exists($state, 'toArray')) {
                    return (string) json_encode($state->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                }

                return '';
            })
            ->dehydrateStateUsing(static function (?string $state): ?array {
                $state = trim((string) $state);
                if ($state === '') {
                    return null;
                }

                $decoded = json_decode($state, true);

                return is_array($decoded) ? $decoded : null;
            })
            ->helperText($helperText);
    }
}
