<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<int, array{0: string, 1: string}>
     */
    private array $columns = [
        ['users', 'phone_number'],
        ['businesses', 'phone_number'],
        ['financing_partners', 'contact_phone'],
        ['otps', 'phone_number'],
    ];

    public function up(): void
    {
        foreach ($this->columns as [$table, $column]) {
            DB::table($table)
                ->select(['id', $column])
                ->whereNotNull($column)
                ->chunkById(500, function ($rows) use ($table, $column) {
                    foreach ($rows as $row) {
                        $normalized = $this->normalize($row->{$column});

                        if ($normalized !== $row->{$column}) {
                            DB::table($table)->where('id', $row->id)->update([
                                $column => $normalized,
                            ]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        //
    }

    private function normalize(string $phoneNumber): string
    {
        $digits = preg_replace('/\D+/', '', $phoneNumber) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '254')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '254'.substr($digits, 1);
        }

        if (strlen($digits) === 9) {
            return '254'.$digits;
        }

        return $digits;
    }
};
