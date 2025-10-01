#!/bin/bash

echo "Creating all remaining controllers and views..."

# ========== EVENT RELATED CONTROLLERS ==========

# EventTagController
cat > app/Http/Controllers/Admin/EventTagController.php << 'EOF'
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventTag;
use Illuminate\Http\Request;

class EventTagController extends Controller
{
    public function index()
    {
        $tags = EventTag::withCount('events')->latest()->paginate(20);
        return view('admin.event-tags.index', compact('tags'));
    }

    public function create()
    {
        return view('admin.event-tags.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tag_name' => 'required|string|max:50|unique:event_tags',
            'tag_name_ar' => 'required|string|max:50',
            'color_hex' => 'nullable|string|max:7',
            'is_active' => 'nullable|boolean'
        ]);

        $validated['is_active'] = $request->has('is_active');
        EventTag::create($validated);

        return redirect()->route('admin.event-tags.index')
            ->with('success', 'تم إضافة الوسم بنجاح');
    }

    public function show(EventTag $eventTag)
    {
        $eventTag->load('events');
        return view('admin.event-tags.show', compact('eventTag'));
    }

    public function edit(EventTag $eventTag)
    {
        return view('admin.event-tags.edit', compact('eventTag'));
    }

    public function update(Request $request, EventTag $eventTag)
    {
        $validated = $request->validate([
            'tag_name' => 'required|string|max:50|unique:event_tags,tag_name,' . $eventTag->id,
            'tag_name_ar' => 'required|string|max:50',
            'color_hex' => 'nullable|string|max:7',
            'is_active' => 'nullable|boolean'
        ]);

        $validated['is_active'] = $request->has('is_active');
        $eventTag->update($validated);

        return redirect()->route('admin.event-tags.index')
            ->with('success', 'تم تحديث الوسم بنجاح');
    }

    public function destroy(EventTag $eventTag)
    {
        $eventTag->delete();
        return redirect()->route('admin.event-tags.index')
            ->with('success', 'تم حذف الوسم بنجاح');
    }
}
EOF

# TicketController
cat > app/Http/Controllers/Admin/TicketController.php << 'EOF'
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\Event;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index()
    {
        $tickets = Ticket::with(['event', 'pricingTiers'])->latest()->paginate(20);
        return view('admin.tickets.index', compact('tickets'));
    }

    public function create()
    {
        $events = Event::active()->get();
        return view('admin.tickets.create', compact('events'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'required|exists:events,id',
            'ticket_type' => 'required|string|max:100',
            'description' => 'nullable|string',
            'total_quantity' => 'required|integer|min:1',
            'available_quantity' => 'required|integer|min:0',
            'min_purchase' => 'nullable|integer|min:1',
            'max_purchase' => 'nullable|integer|min:1',
            'sale_start_date' => 'nullable|date',
            'sale_end_date' => 'nullable|date|after:sale_start_date',
            'is_active' => 'nullable|boolean'
        ]);

        $validated['is_active'] = $request->has('is_active');
        Ticket::create($validated);

        return redirect()->route('admin.tickets.index')
            ->with('success', 'تم إضافة التذكرة بنجاح');
    }

    public function show(Ticket $ticket)
    {
        $ticket->load(['event', 'pricingTiers']);
        return view('admin.tickets.show', compact('ticket'));
    }

    public function edit(Ticket $ticket)
    {
        $events = Event::active()->get();
        return view('admin.tickets.edit', compact('ticket', 'events'));
    }

    public function update(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'event_id' => 'required|exists:events,id',
            'ticket_type' => 'required|string|max:100',
            'description' => 'nullable|string',
            'total_quantity' => 'required|integer|min:1',
            'available_quantity' => 'required|integer|min:0',
            'min_purchase' => 'nullable|integer|min:1',
            'max_purchase' => 'nullable|integer|min:1',
            'sale_start_date' => 'nullable|date',
            'sale_end_date' => 'nullable|date|after:sale_start_date',
            'is_active' => 'nullable|boolean'
        ]);

        $validated['is_active'] = $request->has('is_active');
        $ticket->update($validated);

        return redirect()->route('admin.tickets.index')
            ->with('success', 'تم تحديث التذكرة بنجاح');
    }

    public function destroy(Ticket $ticket)
    {
        $ticket->delete();
        return redirect()->route('admin.tickets.index')
            ->with('success', 'تم حذف التذكرة بنجاح');
    }
}
EOF

# ========== EVENT TAG VIEWS ==========
mkdir -p resources/views/admin/event-tags

cat > resources/views/admin/event-tags/index.blade.php << 'EOF'
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">وسوم الفعاليات</h1>
        <a href="{{ route('admin.event-tags.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> إضافة وسم جديد
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>الوسم</th>
                            <th>اللون</th>
                            <th>عدد الفعاليات</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tags as $tag)
                            <tr>
                                <td>{{ $tag->tag_name_ar }}</td>
                                <td>
                                    @if($tag->color_hex)
                                        <span class="badge" style="background-color: {{ $tag->color_hex }}; color: white;">
                                            {{ $tag->color_hex }}
                                        </span>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $tag->events_count }}</td>
                                <td>
                                    @if($tag->is_active)
                                        <span class="badge bg-success">نشط</span>
                                    @else
                                        <span class="badge bg-danger">غير نشط</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('admin.event-tags.show', $tag) }}" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.event-tags.edit', $tag) }}" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('admin.event-tags.destroy', $tag) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center">لا توجد وسوم</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $tags->links() }}
        </div>
    </div>
</div>
@endsection
EOF

# ========== TICKETS VIEWS ==========
mkdir -p resources/views/admin/tickets

cat > resources/views/admin/tickets/index.blade.php << 'EOF'
@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">التذاكر</h1>
        <a href="{{ route('admin.tickets.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> إضافة تذكرة جديدة
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>نوع التذكرة</th>
                            <th>الفعالية</th>
                            <th>الكمية الإجمالية</th>
                            <th>المتاح</th>
                            <th>فترة البيع</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tickets as $ticket)
                            <tr>
                                <td>{{ $ticket->ticket_type }}</td>
                                <td>{{ $ticket->event->title_ar ?? '-' }}</td>
                                <td>{{ $ticket->total_quantity }}</td>
                                <td>{{ $ticket->available_quantity }}</td>
                                <td>
                                    @if($ticket->sale_start_date && $ticket->sale_end_date)
                                        {{ $ticket->sale_start_date->format('Y-m-d') }} - 
                                        {{ $ticket->sale_end_date->format('Y-m-d') }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    @if($ticket->is_active)
                                        <span class="badge bg-success">نشط</span>
                                    @else
                                        <span class="badge bg-danger">غير نشط</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('admin.tickets.show', $ticket) }}" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.tickets.edit', $ticket) }}" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('admin.tickets.destroy', $ticket) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center">لا توجد تذاكر</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $tickets->links() }}
        </div>
    </div>
</div>
@endsection
EOF

echo "All remaining controllers and views created successfully!"