<?php

namespace App\Filament\Resources\Keywords\Pages;

use App\Filament\Resources\Keywords\KeywordResource;
use App\Models\Keyword;
use App\Utils\Str;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Arr;
use League\Csv\Reader;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

class ManageKeywords extends ManageRecords
{
    /**
     * @var array<string, list<string>>
     */
    private const CSV_HEADER_ALIASES = [
        'keyword' => ['keyword', 'keywords', 'kw', 'query', 'search term', 'term'],
        'global_volume' => ['global volume', 'search volume', 'volume', 'vol', 'avg search volume'],
        'difficulty' => ['difficulty', 'keyword difficulty', 'kd', 'seo difficulty'],
    ];

    protected static string $resource = KeywordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('importCsv')
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    Radio::make('input_method')
                        ->label('Input method')
                        ->options([
                            'upload' => 'Upload file',
                            'paste' => 'Paste CSV text',
                        ])
                        ->default('upload')
                        ->required()
                        ->inline()
                        ->live(),
                    FileUpload::make('csv')
                        ->label('CSV file')
                        ->required(fn (callable $get): bool => $get('input_method') === 'upload')
                        ->acceptedFileTypes(['text/csv', 'text/plain', '.csv'])
                        ->storeFiles(false)
                        ->visible(fn (callable $get): bool => $get('input_method') === 'upload'),
                    Textarea::make('csv_content')
                        ->label('CSV content')
                        ->rows(12)
                        ->placeholder("keyword,global_volume,difficulty\nexample keyword,1200,35")
                        ->required(fn (callable $get): bool => $get('input_method') === 'paste')
                        ->visible(fn (callable $get): bool => $get('input_method') === 'paste'),
                    Toggle::make('has_header')
                        ->label('First row is header')
                        ->default(true),
                    TextInput::make('delimiter')
                        ->default(',')
                        ->maxLength(1)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $inputMethod = (string) ($data['input_method'] ?? 'upload');
                    $delimiter = (string) ($data['delimiter'] ?? ',');
                    $hasHeader = (bool) ($data['has_header'] ?? true);

                    $reader = null;

                    try {
                        if ($inputMethod === 'paste') {
                            $csvContent = (string) ($data['csv_content'] ?? '');

                            if (trim($csvContent) === '') {
                                Notification::make()
                                    ->title('CSV content is empty.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $reader = Reader::createFromString($csvContent);
                        } else {
                            $uploaded = $data['csv'] ?? null;

                            if (is_array($uploaded)) {
                                $uploaded = Arr::first($uploaded);
                            }

                            if (! $uploaded instanceof TemporaryUploadedFile) {
                                Notification::make()
                                    ->title('CSV upload is invalid.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $reader = Reader::createFromPath($uploaded->getRealPath(), 'r');
                        }

                        $reader->setDelimiter($delimiter === '' ? ',' : $delimiter);

                        if ($hasHeader) {
                            $reader->setHeaderOffset(0);
                        }

                        $records = $reader->getRecords();
                    } catch (Throwable) {
                        Notification::make()
                            ->title('Could not read CSV file.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $rowsByHash = [];
                    $skippedRows = 0;
                    $now = now();

                    foreach ($records as $record) {
                        if (! is_array($record)) {
                            $skippedRows++;

                            continue;
                        }

                        $keywordValue = $hasHeader
                            ? $this->extractColumnFromHeaderAliases($record, 'keyword')
                            : ($record[0] ?? Arr::first($record));

                        if (! is_scalar($keywordValue)) {
                            $skippedRows++;

                            continue;
                        }

                        $keyword = Str::sanitizeKeyword((string) $keywordValue);

                        if ($keyword === '') {
                            $skippedRows++;

                            continue;
                        }

                        $hash = Keyword::makeHash($keyword);
                        $globalVolume = $this->parseNullableUnsignedInteger($hasHeader
                            ? $this->extractColumnFromHeaderAliases($record, 'global_volume')
                            : ($record[1] ?? null));
                        $difficulty = $this->parseNullableUnsignedInteger($hasHeader
                            ? $this->extractColumnFromHeaderAliases($record, 'difficulty')
                            : ($record[2] ?? null));

                        $rowsByHash[$hash] = [
                            'keyword' => $keyword,
                            'hash' => $hash,
                            'global_volume' => $globalVolume,
                            'difficulty' => $difficulty,
                            'updated_at' => $now,
                            'created_at' => $now,
                            'deleted_at' => null,
                        ];
                    }

                    if ($rowsByHash === []) {
                        Notification::make()
                            ->title('No valid keywords found in CSV.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $existingHashes = Keyword::query()
                        ->whereIn('hash', array_keys($rowsByHash))
                        ->pluck('hash')
                        ->flip()
                        ->all();
                    $createdCount = 0;
                    $updatedCount = 0;

                    foreach ($rowsByHash as $hash => $_) {
                        if (isset($existingHashes[$hash])) {
                            $updatedCount++;
                        } else {
                            $createdCount++;
                        }
                    }

                    foreach (array_chunk(array_values($rowsByHash), 1000) as $chunk) {
                        Keyword::query()->upsert(
                            $chunk,
                            ['hash'],
                            ['keyword', 'global_volume', 'difficulty', 'updated_at', 'deleted_at']
                        );
                    }

                    Notification::make()
                        ->title('Keywords imported successfully.')
                        ->body(sprintf(
                            'Imported %d unique keywords (%d created, %d updated). Skipped %d invalid/empty rows.',
                            count($rowsByHash),
                            $createdCount,
                            $updatedCount,
                            $skippedRows
                        ))
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * @param  array<int|string, mixed>  $record
     */
    private function extractColumnFromHeaderAliases(array $record, string $column): mixed
    {
        $normalizedRow = [];

        foreach ($record as $header => $value) {
            if (! is_string($header)) {
                continue;
            }

            $normalizedRow[$this->normalizeHeader($header)] = $value;
        }

        foreach (self::CSV_HEADER_ALIASES[$column] ?? [] as $alias) {
            $normalizedAlias = $this->normalizeHeader($alias);

            if (array_key_exists($normalizedAlias, $normalizedRow)) {
                return $normalizedRow[$normalizedAlias];
            }
        }

        return null;
    }

    private function normalizeHeader(string $header): string
    {
        $header = mb_strtolower(trim($header));
        $header = preg_replace('/[^a-z0-9]+/u', ' ', $header) ?? '';

        return trim($header);
    }

    private function parseNullableUnsignedInteger(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        $raw = str_replace([',', ' '], '', $raw);

        if (! is_numeric($raw)) {
            return null;
        }

        return max(0, (int) round((float) $raw));
    }
}
