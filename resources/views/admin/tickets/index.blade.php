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
