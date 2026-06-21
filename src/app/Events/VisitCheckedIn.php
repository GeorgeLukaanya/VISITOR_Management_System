<?php

namespace App\Events;

use App\Models\Visit;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Real-time arrival alert pushed to the guard tablet over Reverb.
 *
 * Per CLAUDE.md the tablet shows: visitor phone, destination tenant, arrival
 * time. NO registered name (that's Phase 2). Broadcast on a per-building private
 * channel so one building's guards never see another building's arrivals.
 */
class VisitCheckedIn implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Visit $visit) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('guards.building.'.$this->visit->building_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'visit.checked_in';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'visit_id' => $this->visit->id,
            'visitor_phone' => $this->visit->visitor_phone,
            'tenant' => $this->visit->tenant?->name,
            'purpose' => $this->visit->purpose,
            'arrived_at' => $this->visit->checked_in_at?->toIso8601String(),
        ];
    }
}
