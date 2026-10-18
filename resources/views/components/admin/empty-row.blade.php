@props(['colspan' => 1, 'icon' => 'inbox', 'title' => 'لا توجد نتائج', 'text' => null])
<tr class="empty-row"><td colspan="{{ $colspan }}" class="border-0"><x-admin.empty-state :icon="$icon" :title="$title" :text="$text">{{ $slot }}</x-admin.empty-state></td></tr>
