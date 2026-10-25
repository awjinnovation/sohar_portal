<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One ticket type of a gate box office order (docs/ticket_types_contract.md §2, Order.lines). */
class BoxOfficeOrderLine extends Model
{
    protected $fillable = ['order_id', 'ticket_type_id', 'key', 'name', 'quantity', 'unit_price_minor', 'line_total_minor', 'admits'];

    protected $casts = [
        'name' => 'array',
        'quantity' => 'integer',
        'unit_price_minor' => 'integer',
        'line_total_minor' => 'integer',
        'admits' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(BoxOfficeOrder::class, 'order_id');
    }

    public function ticketType()
    {
        return $this->belongsTo(EventTicketType::class, 'ticket_type_id');
    }

    public function toApi(): array
    {
        return [
            'ticket_type_id' => $this->ticket_type_id,
            'key' => $this->key,
            'name_text' => EventTicketType::pickLocale($this->name) ?: $this->key,
            'quantity' => $this->quantity,
            'unit_price_minor' => $this->unit_price_minor,
            'line_total_minor' => $this->line_total_minor,
            'admits' => max(1, (int) $this->admits),
        ];
    }
}
