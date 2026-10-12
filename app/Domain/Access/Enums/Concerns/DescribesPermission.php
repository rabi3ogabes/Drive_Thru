<?php

declare(strict_types=1);

namespace App\Domain\Access\Enums\Concerns;

/**
 * Shared label/description lookup for permission enums. Texts live in
 * lang/{locale}/permissions.php under "<scope>.<value>".
 */
trait DescribesPermission
{
    public function getLabel(): string
    {
        return $this->text('label');
    }

    public function getDescription(): string
    {
        return $this->text('description');
    }

    /**
     * Permission values contain dots ("menu.manage"), which dot-notation
     * lookups would split, so the scope array is read and indexed directly.
     */
    private function text(string $field): string
    {
        $entries = __("permissions.{$this->scope()}");

        return is_array($entries) && isset($entries[$this->value][$field])
            ? (string) $entries[$this->value][$field]
            : $this->value;
    }

    public function groupLabel(): string
    {
        return __("permissions.groups.{$this->group()}");
    }

    public function ability(): string
    {
        return "{$this->scope()}.{$this->value}";
    }
}
