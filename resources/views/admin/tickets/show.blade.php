@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">تفاصيل التذكرة</h1>
        <div>
            <a href="{{ route('admin.tickets.edit', $item) }}" class="btn btn-warning">
                <i class="fas fa-edit"></i> تعديل
            </a>
            <a href="{{ route('admin.tickets.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <table class="table">
                <tr>
                    <th width="200">المعرف:</th>
                    <td>{{ $item->id }}</td>
                </tr>
                <tr>
                    <th>تاريخ الإنشاء:</th>
                    <td>{{ $item->created_at->format('Y-m-d H:i') }}</td>
                </tr>
                <tr>
                    <th>آخر تحديث:</th>
                    <td>{{ $item->updated_at->format('Y-m-d H:i') }}</td>
                </tr>
            </table>
        </div>
    </div>
</div>
@endsection
