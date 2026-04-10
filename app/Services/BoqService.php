<?php

namespace App\Services;

use App\Models\BoqItem;
use App\Models\BoqSection;
use App\Models\Tender;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Manages Bill of Quantities sections and items for tenders.
 */
class BoqService
{
    /**
     * Create a new BOQ section for a tender.
     */
    public function createSection(Tender $tender, array $data): BoqSection
    {
        $maxSort = $tender->boqSections()->max('sort_order') ?? 0;

        return $tender->boqSections()->create([
            ...$data,
            'sort_order' => $data['sort_order'] ?? $maxSort + 1,
        ]);
    }

    /**
     * Create a new BOQ item within a section.
     */
    public function createItem(BoqSection $section, array $data): BoqItem
    {
        $maxSort = $section->items()->max('sort_order') ?? 0;

        return $section->items()->create([
            ...$data,
            'sort_order' => $data['sort_order'] ?? $maxSort + 1,
        ]);
    }

    /**
     * Import BOQ items from an Excel/CSV file.
     *
     * Expected columns: section_title, item_code, description, unit, quantity
     *
     * @return int Number of items imported
     */
    public function importFromExcel(Tender $tender, UploadedFile $file): int
    {
        $rows = Excel::toArray(null, $file)[0] ?? [];
        $count = 0;
        $sections = [];

        // Skip header row
        array_shift($rows);

        foreach ($rows as $row) {
            if (empty($row[0]) && empty($row[1])) {
                continue;
            }

            $sectionTitle = trim($row[0] ?? 'General');
            $itemCode = trim($row[1] ?? '');
            $description = trim($row[2] ?? '');
            $unit = trim($row[3] ?? '');
            $quantity = floatval($row[4] ?? 0);

            if (! $description) {
                continue;
            }

            // Find or create section
            if (! isset($sections[$sectionTitle])) {
                $sections[$sectionTitle] = $tender->boqSections()
                    ->firstOrCreate(
                        ['title' => $sectionTitle],
                        ['sort_order' => count($sections) + 1]
                    );
            }

            $section = $sections[$sectionTitle];
            $section->items()->create([
                'item_code' => $itemCode,
                'description_en' => $description,
                'unit' => $unit,
                'quantity' => $quantity,
                'sort_order' => $count + 1,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Export BOQ to an Excel file path.
     */
    public function exportToExcel(Tender $tender): string
    {
        $fileName = "boq-{$tender->reference_number}.xlsx";
        $path = "exports/{$fileName}";

        $sections = $tender->boqSections()->with('items')->orderBy('sort_order')->get();

        $rows = [['Section', 'Item Code', 'Description', 'Unit', 'Quantity']];

        foreach ($sections as $section) {
            foreach ($section->items->sortBy('sort_order') as $item) {
                $rows[] = [
                    $section->title,
                    $item->item_code,
                    $item->description_en,
                    $item->unit,
                    $item->quantity,
                ];
            }
        }

        $export = new class($rows) implements FromArray
        {
            public function __construct(private array $rows) {}

            public function array(): array
            {
                return $this->rows;
            }
        };

        Excel::store($export, $path, 's3');

        return $path;
    }
}
