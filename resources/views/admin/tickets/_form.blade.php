<div class="mb-3">
    <label for="event_id" class="form-label">الفعالية <span class="text-danger">*</span></label>
    <select class="form-control @error('event_id') is-invalid @enderror" id="event_id" name="event_id" required>
        <option value="">اختر الفعالية</option>
        @foreach($events as $event)
            <option value="{{ $event->id }}" {{ old('event_id', $ticket->event_id ?? '') == $event->id ? 'selected' : '' }}>
                {{ $event->title_ar }}
            </option>
        @endforeach
    </select>
    @error('event_id')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="ticket_type" class="form-label">نوع التذكرة <span class="text-danger">*</span></label>
    <input type="text" class="form-control @error('ticket_type') is-invalid @enderror" 
           id="ticket_type" name="ticket_type" 
           value="{{ old('ticket_type', $ticket->ticket_type ?? '') }}" required
           placeholder="مثال: VIP، عادي، طلاب">
    @error('ticket_type')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label for="description" class="form-label">وصف التذكرة</label>
    <textarea class="form-control @error('description') is-invalid @enderror" 
              id="description" name="description" rows="3">{{ old('description', $ticket->description ?? '') }}</textarea>
    @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="row">
    <div class="col-md-6">
        <div class="mb-3">
            <label for="total_quantity" class="form-label">الكمية الإجمالية <span class="text-danger">*</span></label>
            <input type="number" class="form-control @error('total_quantity') is-invalid @enderror" 
                   id="total_quantity" name="total_quantity" 
                   value="{{ old('total_quantity', $ticket->total_quantity ?? '') }}" required min="1">
            @error('total_quantity')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="mb-3">
            <label for="available_quantity" class="form-label">الكمية المتاحة <span class="text-danger">*</span></label>
            <input type="number" class="form-control @error('available_quantity') is-invalid @enderror" 
                   id="available_quantity" name="available_quantity" 
                   value="{{ old('available_quantity', $ticket->available_quantity ?? '') }}" required min="0">
            @error('available_quantity')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="mb-3">
            <label for="min_purchase" class="form-label">الحد الأدنى للشراء</label>
            <input type="number" class="form-control @error('min_purchase') is-invalid @enderror" 
                   id="min_purchase" name="min_purchase" 
                   value="{{ old('min_purchase', $ticket->min_purchase ?? 1) }}" min="1">
            @error('min_purchase')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="mb-3">
            <label for="max_purchase" class="form-label">الحد الأقصى للشراء</label>
            <input type="number" class="form-control @error('max_purchase') is-invalid @enderror" 
                   id="max_purchase" name="max_purchase" 
                   value="{{ old('max_purchase', $ticket->max_purchase ?? 10) }}" min="1">
            @error('max_purchase')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="mb-3">
            <label for="sale_start_date" class="form-label">تاريخ بداية البيع</label>
            <input type="datetime-local" class="form-control @error('sale_start_date') is-invalid @enderror" 
                   id="sale_start_date" name="sale_start_date" 
                   value="{{ old('sale_start_date', isset($ticket) && $ticket->sale_start_date ? $ticket->sale_start_date->format('Y-m-d\TH:i') : '') }}">
            @error('sale_start_date')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="mb-3">
            <label for="sale_end_date" class="form-label">تاريخ نهاية البيع</label>
            <input type="datetime-local" class="form-control @error('sale_end_date') is-invalid @enderror" 
                   id="sale_end_date" name="sale_end_date" 
                   value="{{ old('sale_end_date', isset($ticket) && $ticket->sale_end_date ? $ticket->sale_end_date->format('Y-m-d\TH:i') : '') }}">
            @error('sale_end_date')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="mb-3">
    <div class="form-check">
        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" 
               {{ old('is_active', $ticket->is_active ?? true) ? 'checked' : '' }}>
        <label class="form-check-label" for="is_active">
            نشط
        </label>
    </div>
</div>
