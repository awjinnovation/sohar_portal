@extends('layouts.admin')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">تفاصيل الإعلان</h1>
        <div>
            <a href="{{ route('admin.announcements.edit', $announcement) }}" class="btn btn-warning">
                <i class="fas fa-edit"></i> تعديل
            </a>
            <a href="{{ route('admin.announcements.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i> رجوع
            </a>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table">
                        <tr>
                            <th width="200">النوع:</th>
                            <td>
                                @switch($announcement->type)
                                    @case('info')
                                        <span class="badge bg-info">معلومات</span>
                                        @break
                                    @case('warning')
                                        <span class="badge bg-warning">تحذير</span>
                                        @break
                                    @case('alert')
                                        <span class="badge bg-danger">تنبيه</span>
                                        @break
                                    @case('success')
                                        <span class="badge bg-success">نجاح</span>
                                        @break
                                    @default
                                        {{ $announcement->type }}
                                @endswitch
                            </td>
                        </tr>
                        <tr>
                            <th>الأولوية:</th>
                            <td>{{ $announcement->priority ?? 0 }}</td>
                        </tr>
                        <tr>
                            <th>الجمهور المستهدف:</th>
                            <td>{{ $announcement->target_audience ?? 'الجميع' }}</td>
                        </tr>
                        <tr>
                            <th>مثبت:</th>
                            <td>
                                @if($announcement->is_pinned)
                                    <span class="badge bg-primary">مثبت</span>
                                @else
                                    <span class="badge bg-secondary">غير مثبت</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>الحالة:</th>
                            <td>
                                @if($announcement->is_active)
                                    <span class="badge bg-success">نشط</span>
                                @else
                                    <span class="badge bg-danger">غير نشط</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>تاريخ البداية:</th>
                            <td>{{ $announcement->start_datetime ? $announcement->start_datetime->format('Y-m-d H:i') : '-' }}</td>
                        </tr>
                        <tr>
                            <th>تاريخ النهاية:</th>
                            <td>{{ $announcement->end_datetime ? $announcement->end_datetime->format('Y-m-d H:i') : '-' }}</td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h5 class="font-weight-bold">العنوان</h5>
                    <div class="mb-3">
                        <strong>العربية:</strong>
                        <p>{{ $announcement->title_ar }}</p>
                    </div>
                    <div class="mb-3">
                        <strong>English:</strong>
                        <p>{{ $announcement->title }}</p>
                    </div>
                    
                    <h5 class="font-weight-bold mt-4">المحتوى</h5>
                    <div class="mb-3">
                        <strong>العربية:</strong>
                        <p>{{ $announcement->content_ar }}</p>
                    </div>
                    <div class="mb-3">
                        <strong>English:</strong>
                        <p>{{ $announcement->content }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
