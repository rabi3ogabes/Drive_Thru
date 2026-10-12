<?php

declare(strict_types=1);

namespace App\Livewire\Ops\Concerns;

use App\Domain\Branches\Models\Branch;
use App\Domain\Branches\Models\Device;
use App\Domain\Ordering\Exceptions\InvalidTransition;
use App\Domain\Ordering\KitchenLoad;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Operations\OperationRefused;
use App\Domain\Ordering\Operations\Operator;
use App\Domain\Ordering\Operations\OrderDesk;
use App\Domain\Ordering\Operations\ScreenPin;
use App\Domain\Printing\PrintOrder;
use App\Domain\Printing\TicketRenderer;
use App\Http\Middleware\AuthenticateDevice;
use App\Models\User;
use App\Support\Lang;
use Closure;
use Livewire\Attributes\Locked;
use Livewire\Component;
use SensitiveParameter;

/**
 * Shared by every operations screen. A screen runs either on a paired branch
 * device (re-checked from its cookie on every request, so a revoked screen
 * stops at once) or for a signed-in staff member. Actions go through the
 * OrderDesk, which checks permissions and the branch again.
 *
 * @phpstan-require-extends Component
 */
trait OpsScreen
{
    #[Locked]
    public int $branchId;

    #[Locked]
    public ?int $deviceId = null;

    /**
     * Order ids this screen has already seen, so only new ones chime and glow.
     *
     * @var list<int>
     */
    #[Locked]
    public array $seen = [];

    protected function branch(): Branch
    {
        return once(fn (): Branch => Branch::withoutTenancy()->with(['restaurant', 'lanes'])->findOrFail($this->branchId));
    }

    protected function operator(): Operator
    {
        return once(function (): Operator {
            if ($this->deviceId !== null) {
                $device = AuthenticateDevice::resolve(request());
                abort_unless($device instanceof Device && $device->id === $this->deviceId && $device->branch_id === $this->branchId, 401);

                return Operator::device($device);
            }

            $user = auth()->user();
            abort_unless($user instanceof User, 401);
            $operator = Operator::staff($user, $this->branch()->restaurant_id);
            abort_unless($operator->covers($this->branchId), 403);

            return $operator;
        });
    }

    /**
     * Who does a sensitive action (refund, override): on a paired screen the
     * person must enter their PIN and acts as themselves; signed-in staff
     * already are themselves.
     *
     * @throws OperationRefused
     */
    protected function personFor(#[SensitiveParameter] ?string $pin, string $purpose): Operator
    {
        if ($this->deviceId === null) {
            return $this->operator();
        }

        $device = AuthenticateDevice::resolve(request());
        abort_unless($device instanceof Device && $device->id === $this->deviceId && $device->branch_id === $this->branchId, 401);

        return app(ScreenPin::class)->operator($device, $pin, $purpose);
    }

    /** Whether sensitive actions on this screen ask for a staff PIN. */
    protected function asksForPin(): bool
    {
        return $this->deviceId !== null;
    }

    protected function desk(): OrderDesk
    {
        return app(OrderDesk::class);
    }

    protected function load(): KitchenLoad
    {
        return app(KitchenLoad::class);
    }

    /** An order of this branch, or 404. */
    protected function order(int $orderId): Order
    {
        return Order::withoutTenancy()->where('branch_id', $this->branchId)->with(['items', 'lane'])->findOrFail($orderId);
    }

    /**
     * Runs a desk action and turns refusals into a calm message on the screen.
     */
    protected function attempt(Closure $action, ?string $success = null): bool
    {
        try {
            $action();
        } catch (OperationRefused|InvalidTransition $problem) {
            $this->dispatch('ops-toast', message: $problem->getMessage(), tone: 'error');

            return false;
        }

        if ($success !== null) {
            $this->dispatch('ops-toast', message: $success, tone: 'success');
        }

        app(KitchenLoad::class)->flush();

        return true;
    }

    /**
     * Prints a kitchen ticket or receipt: on the branch printer when one is
     * set up, otherwise in this browser's print dialog.
     */
    public function printTicket(int $orderId, string $kind): void
    {
        $order = $this->order($orderId);
        $kind = $kind === TicketRenderer::RECEIPT ? TicketRenderer::RECEIPT : TicketRenderer::KITCHEN;
        $this->operator();

        $result = app(PrintOrder::class)->handle($order, $kind);

        if ($result === PrintOrder::SENT) {
            $this->dispatch('ops-toast', message: Lang::text('print.sent', ['number' => $order->displayNumber()]), tone: 'success');

            return;
        }

        if ($result === PrintOrder::FAILED) {
            $this->dispatch('ops-toast', message: Lang::text('print.failed'), tone: 'error');
        }

        $this->dispatch('ops-print', url: route('print.ticket', ['code' => $order->short_code, 'kind' => $kind]));
    }

    /**
     * Ids that arrived since the last render (for the chime and red glow).
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    protected function newlyArrived(array $ids): array
    {
        $fresh = array_values(array_diff($ids, $this->seen));
        $this->seen = array_values(array_slice(array_unique([...$this->seen, ...$ids]), -300));

        return $fresh;
    }
}
