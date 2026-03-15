<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskMoved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;


     public $activity;
    /**
     * Create a new event instance.
     */
    public function __construct($activity)
    {
        $this->activity = $activity;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->activity->user_id)
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'activity' => [
                'id'         => $this->activity->id,
                'action'     => $this->activity->action, // 'task_moved' or 'task_reordered'
                'meta'       => $this->activity->meta,
                'user_id'    => $this->activity->user_id,
                'created_at' => $this->activity->created_at,
            ]
        ];
    }
}
