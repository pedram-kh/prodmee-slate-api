<?php

namespace App\Support;

class Slate
{
    /** @return array<int,array{id:string,label:string}> */
    public static function checklistFlat(): array
    {
        $out = [];
        foreach (config('slate.checklist') as $phase) {
            foreach ($phase['items'] as $item) {
                $out[] = $item;
            }
        }
        return $out;
    }

    public static function checklistTotal(): int
    {
        return count(self::checklistFlat());
    }

    public static function stageLabel(string $id): string
    {
        foreach (config('slate.stages') as $s) {
            if ($s['id'] === $id) {
                return $s['label'];
            }
        }
        return $id;
    }
}
