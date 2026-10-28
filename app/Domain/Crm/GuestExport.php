<?php

declare(strict_types=1);

namespace App\Domain\Crm;

use App\Domain\Crm\Enums\Segment;
use App\Domain\Crm\Models\CustomerSegment;
use App\Domain\Customers\Models\Customer;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The guest list as a CSV (UTF-8 with a BOM so Excel shows Arabic names),
 * following the filters on screen. Phone numbers are masked unless the
 * employee may see guest contacts. Every export is in the audit log.
 */
final class GuestExport
{
    /**
     * @param  Builder<Customer>  $query
     */
    public function download(Builder $query, Restaurant $restaurant, User $by, bool $withPhones): StreamedResponse
    {
        activity()->performedOn($restaurant)->causedBy($by)->event('crm.guests_exported')
            ->withProperties(['restaurant_id' => $restaurant->id, 'with_phones' => $withPhones])->log('crm.guests_exported');

        $name = 'guests-'.$restaurant->slug.'-'.now($restaurant->timezone)->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query, $restaurant, $withPhones): void {
            $out = fopen('php://output', 'wb');

            if ($out === false) {
                return;
            }

            fwrite($out, "\u{FEFF}");
            fputcsv($out, $this->headings(), escape: '');

            $query->clone()->with(['tier', 'segments'])->chunk(500, function ($customers) use ($out, $restaurant, $withPhones): void {
                foreach ($customers as $customer) {
                    fputcsv($out, array_map(self::cell(...), $this->row($customer, $restaurant, $withPhones)), escape: '');
                }
            });

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return list<string>
     */
    private function headings(): array
    {
        return [
            __('crm.guests.fields.guest'),
            __('crm.guests.fields.phone'),
            __('crm.guests.fields.segments'),
            __('crm.guests.fields.tier'),
            __('crm.guests.fields.orders'),
            __('crm.guests.fields.spent_90d'),
            __('crm.guests.fields.avg_order'),
            __('crm.guests.fields.first_order'),
            __('crm.guests.fields.last_order'),
            __('crm.guests.fields.points'),
            __('crm.guests.fields.offers'),
        ];
    }

    /**
     * @return list<string>
     */
    private function row(Customer $customer, Restaurant $restaurant, bool $withPhones): array
    {
        $date = fn ($at): string => $at?->timezone($restaurant->timezone)->format('Y-m-d') ?? '';

        return [
            (string) $customer->name,
            $withPhones ? $customer->phone : $customer->maskedPhone(),
            $customer->segments->map(fn (CustomerSegment $row): Segment => $row->segment)->unique()->map(fn (Segment $segment): string => $segment->getLabel())->implode(', '),
            (string) $customer->tier?->name,
            (string) $customer->picked_up_count,
            Money::toMajor($customer->spent_90d_minor, $restaurant->currency),
            Money::toMajor($customer->avg_order_minor, $restaurant->currency),
            $date($customer->first_order_at),
            $date($customer->last_order_at),
            (string) $customer->points_balance,
            $customer->acceptsOffers() ? __('crm.guests.yes') : __('crm.guests.no'),
        ];
    }

    /**
     * Spreadsheet apps run a cell that starts with = + - @ as a formula, so
     * those get a leading apostrophe. A plain number such as +974… is left
     * as it is, masked or not.
     */
    public static function cell(string $value): string
    {
        if ($value === '' || preg_match('/^\+[\d •]+$/u', $value) === 1 || preg_match('/^-?\d+(\.\d+)?$/', $value) === 1) {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
