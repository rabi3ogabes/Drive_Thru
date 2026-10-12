<?php

declare(strict_types=1);

namespace App\Domain\Staff\Data;

/** What the owner fills in when inviting or editing a team member. */
final readonly class EmployeeData
{
    /**
     * @param  list<int>  $branchIds
     */
    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone,
        public ?int $positionId,
        public bool $allBranches,
        public array $branchIds,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: trim((string) ($data['name'] ?? '')),
            email: mb_strtolower(trim((string) ($data['email'] ?? ''))),
            phone: filled($data['phone'] ?? null) ? (string) $data['phone'] : null,
            positionId: isset($data['position_id']) && is_numeric($data['position_id']) ? (int) $data['position_id'] : null,
            allBranches: (bool) ($data['all_branches'] ?? false),
            branchIds: array_values(array_map('intval', (array) ($data['branch_ids'] ?? []))),
        );
    }
}
