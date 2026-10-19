<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\DiscoverForm;
use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use Illuminate\Http\Request;

/** Admin: standalone pages (rich text + cover), permission `manage content`. */
class ContentPageController extends Controller
{
    use DiscoverForm;

    public function index()
    {
        $pages = ContentPage::with('media')->orderBy('sort')->orderBy('id')->get();

        return view('admin.content-pages.index', compact('pages'));
    }

    public function create()
    {
        return view('admin.content-pages.form', ['page' => new ContentPage(['status' => 'draft', 'show_in_more' => true, 'icon' => 'article'])]);
    }

    public function edit(ContentPage $page)
    {
        return view('admin.content-pages.form', ['page' => $page->load('media')]);
    }

    private function rules(): array
    {
        return array_merge(
            $this->localizedRules('title', 150, true),
            $this->localizedRules('body', 50000),
            $this->iconRules(),
            [
                'slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
                'show_in_more' => ['nullable', 'boolean'],
                'sort' => ['nullable', 'integer', 'min:0', 'max:100000'],
                'status' => ['required', 'in:draft,published'],
                'cover' => ['nullable', 'file', ...explode('|', self::IMAGE_RULE)],
                'remove_cover' => ['nullable', 'boolean'],
            ],
        );
    }

    private function fill(ContentPage $page, Request $request): ContentPage
    {
        $title = $this->localized($request, 'title');
        $page->fill([
            'title' => $title,
            'body' => $this->localizedHtml($request, 'body'),
            'icon' => $request->input('icon') ?: null,
            'show_in_more' => $request->boolean('show_in_more'),
            'status' => $request->input('status'),
        ]);
        if ($request->filled('sort')) {
            $page->sort = (int) $request->input('sort');
        } elseif (! $page->exists) {
            $page->sort = (int) ContentPage::max('sort') + 1;
        }
        if ($page->status === 'draft') {
            $page->published_at = null;
        }
        $page->slug = ContentPage::uniqueSlug($request->input('slug') ?: ($page->exists ? $page->slug : null), $title ?? [],
            ['tenant_id' => $this->currentTenantId()], $page->id, 'page');

        return $page;
    }

    public function store(Request $request)
    {
        $request->validate($this->rules());
        $page = $this->fill(new ContentPage, $request);
        $page->save();
        $this->syncMedia($request, $page, 'cover', false);

        return redirect()->route('admin.content-pages.edit', $page)->with('success', 'أُضيفت الصفحة');
    }

    public function update(Request $request, ContentPage $page)
    {
        $request->validate($this->rules());
        $this->fill($page, $request)->save();
        $this->syncMedia($request, $page, 'cover', false);

        return redirect()->route('admin.content-pages.edit', $page)->with('success', 'حُفظت الصفحة');
    }

    public function destroy(ContentPage $page)
    {
        $page->delete();

        return redirect()->route('admin.content-pages.index')->with('success', 'حُذفت الصفحة');
    }

    public function reorder(Request $request)
    {
        return $this->reorderRows($request, ContentPage::class);
    }
}
