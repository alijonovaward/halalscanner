<?php

namespace App\Console\Commands;

use App\Models\ProductCandidate;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SplFileObject;
use Throwable;

#[Signature('catalog:import-candidates {path : Path to the candidate CSV file}')]
#[Description('Import product candidates into the review staging table')]
class ImportProductCandidates extends Command
{
    private const array REQUIRED_COLUMNS = [
        'barcode',
        'name_raw',
        'brand_raw',
        'ingredients_text_raw',
        'source_url',
        'source_modified_at_utc',
        'source_dataset',
        'source_license',
        'country_tag',
        'catalog_review_status',
        'halal_status',
        'data_quality_flags',
    ];

    public function handle(): int
    {
        try {
            $rows = $this->readRows((string) $this->argument('path'));
            $imported = 0;
            $skipped = 0;

            DB::transaction(function () use ($rows, &$imported, &$skipped): void {
                foreach ($rows as $row) {
                    $existingCandidate = ProductCandidate::query()
                        ->where('barcode', $row['barcode'])
                        ->lockForUpdate()
                        ->first();

                    if ($existingCandidate !== null && $existingCandidate->review_status !== 'pending_review') {
                        $skipped++;

                        continue;
                    }

                    ProductCandidate::query()->updateOrCreate(
                        ['barcode' => $row['barcode']],
                        $this->candidateAttributes($row),
                    );
                    $imported++;
                }
            });
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Imported {$imported} product candidates.");

        if ($skipped > 0) {
            $label = $skipped === 1 ? 'candidate' : 'candidates';
            $this->info("Skipped {$skipped} reviewed product {$label}.");
        }

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, string>>
     */
    private function readRows(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("Candidate CSV is not readable: {$path}");
        }

        $file = new SplFileObject($path, 'rb');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        $header = $file->fgetcsv();

        if (! is_array($header)) {
            throw new RuntimeException('Candidate CSV does not contain a header row.');
        }

        $header = array_map(
            static fn (mixed $column): string => trim((string) $column),
            $header,
        );
        $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
        $missingColumns = array_values(array_diff(self::REQUIRED_COLUMNS, $header));

        if ($missingColumns !== []) {
            throw new RuntimeException(
                'Candidate CSV is missing required columns: '.implode(', ', $missingColumns),
            );
        }

        $rows = [];

        while (! $file->eof()) {
            $values = $file->fgetcsv();

            if (! is_array($values) || $values === [null]) {
                continue;
            }

            if (count($values) !== count($header)) {
                throw new RuntimeException('Candidate CSV contains a row with an unexpected column count.');
            }

            $row = array_combine($header, array_map(
                static fn (mixed $value): string => trim((string) $value),
                $values,
            ));

            if ($row === false) {
                throw new RuntimeException('Candidate CSV row could not be mapped to its header.');
            }

            $this->validateRow($row);
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function validateRow(array $row): void
    {
        $barcode = $row['barcode'] ?? '';

        if (! $this->isValidGtin($barcode)) {
            throw new RuntimeException("Invalid GTIN barcode: {$barcode}");
        }

        if (($row['halal_status'] ?? '') !== 'unknown') {
            throw new RuntimeException('Candidate rows must not contain a halal conclusion.');
        }
    }

    private function isValidGtin(string $barcode): bool
    {
        if (! ctype_digit($barcode) || ! in_array(mb_strlen($barcode), [8, 12, 13, 14], true)) {
            return false;
        }

        $checkDigit = (int) mb_substr($barcode, -1);
        $body = strrev(mb_substr($barcode, 0, -1));
        $sum = 0;

        foreach (str_split($body) as $position => $digit) {
            $sum += (int) $digit * ($position % 2 === 0 ? 3 : 1);
        }

        return (10 - ($sum % 10)) % 10 === $checkDigit;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function candidateAttributes(array $row): array
    {
        return [
            'name' => $row['name_raw'],
            'brand' => $row['brand_raw'] !== '' ? $row['brand_raw'] : null,
            'ingredients_text' => $row['ingredients_text_raw'] !== '' ? $row['ingredients_text_raw'] : null,
            'source_url' => $row['source_url'],
            'source_modified_at' => $row['source_modified_at_utc'] !== ''
                ? CarbonImmutable::parse($row['source_modified_at_utc'])
                : null,
            'source_dataset' => $row['source_dataset'],
            'source_license' => $row['source_license'],
            'market_tags' => $this->splitList($row['country_tag']),
            'review_status' => $row['catalog_review_status'],
            'data_quality_flags' => $this->splitList($row['data_quality_flags']),
        ];
    }

    /**
     * @return list<string>
     */
    private function splitList(string $value): array
    {
        return array_values(array_filter(
            array_map('trim', explode(';', $value)),
            static fn (string $item): bool => $item !== '',
        ));
    }
}
